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
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — Portfólio</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        body { background:#f6f7f9; }
        .container { max-width:1200px; margin:0 auto; padding:36px 18px; }
        .back-link { display:inline-block; margin-bottom:18px; color:var(--primary); text-decoration:none; font-weight:800 }

        /* Single card containing media + info */
        .detail-grid {
            display:grid;
            grid-template-columns: 1fr 360px;
            gap:28px;
            align-items:start;
            background:#ffffff;
            border-radius:14px;
            padding:18px;
            box-shadow:0 20px 60px rgba(2,6,23,0.06);
        }

        .detail-media { background:transparent; border-radius:10px; padding:6px; }
        .main-wrap { position:relative; }
        .main-image { width:100%; height:min(78vh,820px); object-fit:contain; display:block; border-radius:12px; background:linear-gradient(180deg,#fbfdfc,#ffffff); box-shadow:0 12px 36px rgba(2,6,23,0.06); }

        .nav-arrow { position:absolute; top:50%; transform:translateY(-50%); background:rgba(2,6,23,0.6); color:#fff; border:0; width:44px; height:44px; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:18px; }
        .nav-prev { left:12px; }
        .nav-next { right:12px; }

        .thumbs { display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
        .thumb { width:88px; height:58px; border-radius:8px; overflow:hidden; cursor:pointer; opacity:.7; border:2px solid transparent; box-sizing:border-box; }
        .thumb img { width:100%; height:100%; object-fit:cover; display:block }
        .thumb.active { opacity:1; border-color:var(--primary); box-shadow:0 8px 24px rgba(11,110,79,0.08); }

        .detail-info { padding:18px; background:transparent; border-radius:6px; }
        .detail-title { font-size:28px; margin:6px 0 12px; color:var(--primary); text-transform:uppercase; letter-spacing:1px; font-weight:900 }
        .detail-desc { color:#374151; line-height:1.7; font-size:16px; white-space:pre-wrap }

        @media (max-width:980px) {
            .detail-grid { grid-template-columns:1fr; padding:14px }
            .detail-info { order:2 }
            .detail-media { order:1 }
            .main-image { height:60vh }
            .thumb { width:72px; height:48px }
            .nav-arrow { display:none }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <div class="container">
        <a href="portfolio.php" class="back-link">← Voltar ao Portfólio</a>

        <div class="detail-grid">
            <div class="detail-media">
                <div class="main-wrap">
                    <button id="img-prev" class="nav-arrow nav-prev" aria-label="Imagem anterior">&larr;</button>
                    <img id="main-image" class="main-image" src="/LabInSmile/images/<?= htmlspecialchars($images[0] ?? '') ?>" alt="<?= htmlspecialchars($title) ?>">
                    <button id="img-next" class="nav-arrow nav-next" aria-label="Próxima imagem">&rarr;</button>
                </div>
                <div id="thumbs" class="thumbs">
                    <?php foreach ($images as $i => $img): ?>
                        <button type="button" class="thumb<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>" aria-label="Ver imagem <?= $i+1 ?>">
                            <img src="/LabInSmile/images/<?= htmlspecialchars($img) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="detail-info">
                <h1 class="detail-title"><?= htmlspecialchars($title) ?></h1>
                    <?php if (trim($desc) !== ''): ?>
                        <div class="detail-desc"><?= nl2br(htmlspecialchars($desc)) ?></div>
                    <?php endif; ?>
            </aside>
        </div>
    </div>
</main>

<script>
(() => {
    const images = <?= json_encode(array_values($images)) ?> || [];
    let current = 0;
    const main = document.getElementById('main-image');
    const thumbs = document.getElementById('thumbs');
    const prevBtn = document.getElementById('img-prev');
    const nextBtn = document.getElementById('img-next');

    function show(index){
        if(!images.length) return;
        current = (index + images.length) % images.length;
        main.src = '/LabInSmile/images/' + images[current];
        Array.from(thumbs.children).forEach((b, i) => b.classList.toggle('active', i === current));
    }

    thumbs.addEventListener('click', (e) => {
        const btn = e.target.closest('.thumb');
        if(!btn) return;
        const idx = parseInt(btn.dataset.index || '0', 10);
        show(idx);
    });

    if(prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); show(current - 1); });
    if(nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); show(current + 1); });

    main.addEventListener('click', () => { show(current + 1); });

    document.addEventListener('keydown', (e) => {
        if(e.key === 'ArrowRight') show(current + 1);
        if(e.key === 'ArrowLeft') show(current - 1);
        if(e.key === 'Escape') window.location.href = 'portfolio.php';
    });

    // Initialize
    show(0);
})();
</script>

</body>
</html>
