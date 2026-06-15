<?php
session_start();
require_once __DIR__ . '/../config.php';

function portfolio_images($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }

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

$result = $conn->query("SELECT * FROM portfolio ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Portfólio - LabInSmile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        main { padding: 36px 12px; min-height: calc(100vh - 260px); }
        .container { max-width:1100px; margin:0 auto; }

        /* Portfolio layout: Flex container with wrap, center-aligned. */
        .portfolio-grid {
            display:flex;
            flex-wrap:wrap;
            gap:24px;
            justify-content:center;
            align-items:stretch;
        }

        /* Each card is a vertical column, centered internally. Three cards per row on desktop. */
        .portfolio-card {
            background:#fff;
            border-radius:12px;
            padding:20px;
            box-shadow:0 6px 20px rgba(16,24,40,0.06);
            text-decoration:none;
            color:inherit;
            flex: 0 0 calc((100% - 48px) / 3); /* 3 cards per row with 24px gaps */
            max-width:360px;
            display:flex;
            align-items:center;
            justify-content:center;
            box-sizing:border-box;
        }

        /* Inner column layout: icon -> title -> button */
        .card-inner {
            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;
            gap:14px;
            width:100%;
        }

        .card-icon img { width:96px; height:96px; border-radius:50%; object-fit:cover; display:block; }
        .card-icon-placeholder { width:96px; height:96px; border-radius:50%; background:#eef8f4; color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:28px; }

        .card-title { color:var(--primary); font-size:15px; margin:0; font-weight:800; text-transform:uppercase; letter-spacing:2px; }

        .card-btn { display:inline-block; padding:8px 20px; border-radius:999px; background:var(--primary); color:#fff; text-decoration:none; font-weight:700; }
        .card-btn:hover { background:var(--primary-700); }

        /* Modal for "Ver Mais" — larger, grid layout with thumbnails */
        .portfolio-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:9999; padding:28px; }
        .portfolio-modal.open { display:flex; }
        .pm-backdrop { position:absolute; inset:0; background:rgba(2,6,23,0.56); backdrop-filter: blur(2px); }
        .pm-dialog { position:relative; z-index:2; max-width:1100px; width:100%; background:#ffffff; border-radius:14px; padding:18px; box-shadow:0 30px 80px rgba(2,6,23,0.45); display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start; transform:translateY(6px) scale(.99); opacity:0; transition:opacity .25s ease, transform .25s ease; }
        .portfolio-modal.open .pm-dialog { transform:translateY(0) scale(1); opacity:1; }
        .pm-close { position:absolute; top:12px; right:14px; background:transparent; border:0; font-size:26px; line-height:1; color:#374151; cursor:pointer; padding:6px; border-radius:6px; }

        .pm-left { display:flex; flex-direction:column; gap:12px; }
        .pm-carousel { position:relative; overflow:hidden; border-radius:10px; background:#f8faf9; min-height:360px; display:flex; align-items:center; justify-content:center; }
        .pm-carousel img { max-width:100%; max-height:60vh; object-fit:contain; display:block; border-radius:8px; box-shadow: 0 10px 30px rgba(2,6,23,0.12); transition:opacity .28s ease; }
        .pm-thumbs { display:flex; gap:8px; margin-top:10px; justify-content:center; flex-wrap:wrap; }
        .pm-thumb { width:72px; height:52px; border-radius:8px; overflow:hidden; cursor:pointer; opacity:.6; border:2px solid transparent; box-sizing:border-box; }
        .pm-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .pm-thumb.active { opacity:1; border-color:var(--primary); box-shadow:0 6px 18px rgba(11,110,79,0.12); }

        .pm-right { padding:6px 8px; }
        .pm-title { color:var(--primary); font-weight:900; font-size:20px; margin:0 0 6px; text-transform:uppercase; letter-spacing:1px; }
        .pm-desc { color:#374151; font-size:15px; margin-bottom:12px; line-height:1.45; }
        .pm-actions { display:flex; gap:8px; margin-top:12px; align-items:center; }

        .pm-nav { position:absolute; top:50%; transform:translateY(-50%); background:rgba(11,110,79,0.95); color:#fff; border:0; padding:8px 12px; border-radius:8px; cursor:pointer; box-shadow:0 8px 24px rgba(11,110,79,0.12); }
        .pm-prev { left:12px; }
        .pm-next { right:12px; }

        @media (max-width:900px) {
            .pm-dialog { grid-template-columns:1fr; padding:12px; }
            .pm-right { order:2; }
            .pm-left { order:1; }
            .pm-carousel { min-height:240px; }
            .pm-thumbs { justify-content:flex-start; overflow:auto; padding-bottom:4px; }
            .pm-nav { display:none; }
        }

        .manage-link { display:inline-block; margin-bottom:12px; padding:8px 12px; background:#0b6e4f; color:#fff; border-radius:8px; text-decoration:none }

        /* Responsiveness: 2 columns on medium screens, 1 column on small screens */
        @media (max-width: 900px) {
            .portfolio-card { flex: 0 0 calc((100% - 24px) / 2); }
        }

        @media (max-width: 520px) {
            .portfolio-card { flex: 0 0 100%; }
            .portfolio-card { max-width: 100%; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <div class="container">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px;">
            <h1>Portfólio</h1>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="manage_portfolio.php" class="manage-link">Painel Portfolio</a>
            <?php endif; ?>
        </div>

        <div class="portfolio-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $images = portfolio_images($row['imagem'] ?? ''); ?>
                    <div class="portfolio-card" data-id="<?= intval($row['id']) ?>" data-titulo="<?= htmlspecialchars($row['titulo'], ENT_QUOTES) ?>" data-imagem="<?= htmlspecialchars($row['imagem'] ?? '', ENT_QUOTES) ?>" data-descricao="<?= htmlspecialchars($row['descricao'] ?? '', ENT_QUOTES) ?>">
                        <div class="card-inner">
                            <div class="card-icon">
                                <?php if (!empty($images)): ?>
                                    <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['titulo']) ?>">
                                <?php else: ?>
                                    <div class="card-icon-placeholder"><?= htmlspecialchars(strtoupper(substr($row['titulo'], 0, 1))) ?></div>
                                <?php endif; ?>
                            </div>

                            <h3 class="card-title"><?= htmlspecialchars($row['titulo']) ?></h3>

                            <a href="portfolio_item.php?id=<?= intval($row['id']) ?>" class="card-btn" aria-label="Ver mais sobre <?= htmlspecialchars($row['titulo']) ?>">Ver Mais</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Sem exemplos no portefólio por enquanto.</p>
            <?php endif; ?>
        </div>
    </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>
