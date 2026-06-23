<?php
session_start();
require_once __DIR__ . '/../config.php';

function portfolio_images($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) return array_values(array_filter($decoded, 'is_string'));
    return [$value];
}

function ensure_portfolio_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS portfolio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT NOT NULL,
        imagem TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_portfolio_table($conn);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: portfolio.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM portfolio WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: portfolio.php');
    exit;
}

$images = portfolio_images($item['imagem'] ?? '');
$title = $item['titulo'] ?? '';
$desc = $item['descricao'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Portfólio</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .portfolio-detail-container {
            padding: 40px 0;
        }

        .btn-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 24px;
            transition: var(--transition-fast);
        }

        .btn-back-link:hover {
            color: var(--primary-700);
            transform: translateX(-2px);
        }

        .btn-back-link svg {
            width: 16px;
            height: 16px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 36px;
            align-items: start;
        }

        /* Gallery Layout */
        .detail-media-wrap {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .main-media-frame {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            aspect-ratio: 4/3;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-media-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            opacity: 1;
            transition: opacity 0.25s ease-in-out;
        }

        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: var(--shadow-md);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            z-index: 10;
            font-size: 1.2rem;
            transition: var(--transition-fast);
        }

        .gallery-nav-btn:hover {
            background: #ffffff;
            transform: translateY(-50%) scale(1.05);
        }

        .gallery-nav-btn.prev { left: 16px; }
        .gallery-nav-btn.next { right: 16px; }

        .thumbnail-grid-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 10px;
        }

        .thumb-btn-card {
            border: 2px solid transparent;
            border-radius: var(--radius-sm);
            overflow: hidden;
            aspect-ratio: 4/3;
            cursor: pointer;
            padding: 0;
            background: #f1f5f9;
            transition: var(--transition-fast);
        }

        .thumb-btn-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .thumb-btn-card:hover {
            opacity: 0.9;
        }

        .thumb-btn-card.active {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        /* Description Details Card */
        .info-detail-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .info-detail-card h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-detail-card hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 0;
        }

        .info-detail-card p.description {
            color: var(--text-main);
            font-size: 0.95rem;
            line-height: 1.7;
            margin: 0;
            white-space: pre-wrap;
        }

        @media (max-width: 900px) {
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="container portfolio-detail-container">
    <a href="portfolio.php" class="btn-back-link">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        <span>Voltar ao Portfólio</span>
    </a>

    <div class="detail-grid">
        <!-- GALLERY LEFT -->
        <div class="detail-media-wrap">
            <div class="main-media-frame">
                <?php if (!empty($images)): ?>
                    <button type="button" id="img-prev" class="gallery-nav-btn prev" aria-label="Imagem anterior">&#10094;</button>
                    <img id="main-image" src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($title) ?>">
                    <button type="button" id="img-next" class="gallery-nav-btn next" aria-label="Próxima imagem">&#10095;</button>
                <?php else: ?>
                    <div style="color: var(--muted); font-size: 0.9rem;">Nenhuma imagem carregada</div>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="thumbnail-grid-row" id="thumbs-row">
                    <?php foreach ($images as $idx => $img): ?>
                        <button type="button" class="thumb-btn-card <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>" aria-label="Ver imagem <?= $idx + 1 ?>">
                            <img src="/LabInSmile/images/<?= htmlspecialchars($img) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- INFO CARD RIGHT -->
        <aside class="info-detail-card">
            <h1><?= htmlspecialchars($title) ?></h1>
            <hr>
            <?php if (trim($desc) !== ''): ?>
                <p class="description"><?= nl2br(htmlspecialchars($desc)) ?></p>
            <?php else: ?>
                <p class="description" style="color: var(--text-muted); font-style: italic;">Sem descrição para este trabalho.</p>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
(() => {
    const images = <?= json_encode(array_values($images)) ?> || [];
    if (images.length === 0) return;

    let current = 0;
    const mainImg = document.getElementById('main-image');
    const prevBtn = document.getElementById('img-prev');
    const nextBtn = document.getElementById('img-next');
    const thumbsRow = document.getElementById('thumbs-row');
    const thumbBtns = thumbsRow ? Array.from(thumbsRow.children) : [];

    function showImage(index) {
        current = (index + images.length) % images.length;
        
        // Simple fade animation
        if (mainImg) {
            mainImg.style.opacity = '0.3';
            setTimeout(() => {
                mainImg.src = '/LabInSmile/images/' + images[current];
                mainImg.style.opacity = '1';
            }, 100);
        }

        // Toggle thumb active styles
        thumbBtns.forEach((btn, i) => {
            btn.classList.toggle('active', i === current);
        });
    }

    if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); showImage(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); showImage(current + 1); });

    if (thumbsRow) {
        thumbsRow.addEventListener('click', (e) => {
            const btn = e.target.closest('.thumb-btn-card');
            if (!btn) return;
            const idx = parseInt(btn.dataset.index || '0', 10);
            showImage(idx);
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') showImage(current + 1);
        if (e.key === 'ArrowLeft') showImage(current - 1);
        if (e.key === 'Escape') window.location.href = 'portfolio.php';
    });
})();
</script>
</body>
</html>
