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
$result = $conn->query("SELECT * FROM portfolio ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfólio de Trabalhos - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .portfolio-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .portfolio-header-row h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .btn-admin-manage {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(11, 110, 79, 0.1);
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .btn-admin-manage:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .portfolio-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }

        .portfolio-gallery-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: var(--transition-normal);
        }

        .portfolio-gallery-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(11, 110, 79, 0.15);
        }

        .portfolio-media-box {
            height: 220px;
            background: #f1f5f9;
            overflow: hidden;
            position: relative;
        }

        .portfolio-media-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: var(--transition-normal);
        }

        .portfolio-gallery-card:hover .portfolio-media-box img {
            transform: scale(1.04);
        }

        .no-photo-badge {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            background: var(--bg);
            font-size: 2rem;
            font-weight: 800;
        }

        .portfolio-photo-count {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            backdrop-filter: blur(4px);
        }

        .portfolio-info-box {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
            text-align: center;
            flex: 1;
        }

        .portfolio-info-box h3 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--text-main);
            margin: 0;
        }

        .btn-view-item-detail {
            display: inline-block;
            background: var(--primary);
            color: #ffffff;
            padding: 8px 22px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition-fast);
            margin-top: auto;
        }

        .portfolio-gallery-card:hover .btn-view-item-detail {
            background: var(--primary-700);
            box-shadow: 0 4px 10px rgba(11, 110, 79, 0.15);
        }

        @media (max-width: 600px) {
            .portfolio-header-row {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .btn-admin-manage {
                text-align: center;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="container" style="padding-top: 40px;">
    <div class="portfolio-header-row">
        <h1>O Nosso Portfólio</h1>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="manage_portfolio.php" class="btn-admin-manage">Gerir Portfólio</a>
        <?php endif; ?>
    </div>

    <div class="portfolio-cards-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $images = portfolio_images($row['imagem'] ?? ''); ?>
                <a href="portfolio_item.php?id=<?= intval($row['id']) ?>" class="portfolio-gallery-card">
                    <div class="portfolio-media-box">
                        <?php if (!empty($images)): ?>
                            <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['titulo']) ?>" loading="lazy">
                            <?php if (count($images) > 1): ?>
                                <span class="portfolio-photo-count"><?= count($images) ?> Fotos</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-photo-badge"><?= htmlspecialchars(strtoupper(substr($row['titulo'], 0, 1))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="portfolio-info-box">
                        <h3><?= htmlspecialchars($row['titulo']) ?></h3>
                        <span class="btn-view-item-detail">Ver Detalhes</span>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--muted); border: 1px dashed var(--border-color); border-radius: var(--radius-md);">
                <p>Nenhum exemplo disponível no portfólio de momento.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>
