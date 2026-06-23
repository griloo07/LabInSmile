<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function ensure_avaliacoes_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        classificacao TINYINT NOT NULL,
        observacoes TEXT,
        ip VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_avaliacoes_table($conn);

$review_count = 0;
$review_average = 0.0;
$reviews = [];
@$res_stats = $conn->query("SELECT COUNT(*) AS cnt, AVG(classificacao) AS avg_rating FROM avaliacoes");
if ($res_stats) {
    $stats = $res_stats->fetch_assoc();
    $review_count = intval($stats['cnt'] ?? 0);
    $review_average = isset($stats['avg_rating']) && $stats['avg_rating'] !== null ? floatval($stats['avg_rating']) : 0.0;
}

@$res_reviews = $conn->query("SELECT nome, classificacao, observacoes, created_at FROM avaliacoes ORDER BY created_at DESC");
if ($res_reviews) {
    while ($r = $res_reviews->fetch_assoc()) { $reviews[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas as Avaliações - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .all-reviews-container {
            padding: 40px 0 60px;
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

        .reviews-summary-board {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }

        .summary-left-side {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .average-numeric {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .summary-stars-column {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stars-row {
            display: flex;
            gap: 4px;
        }

        .stars-row span {
            color: #e2e8f0;
            font-size: 1.25rem;
            line-height: 1;
        }

        .stars-row span.filled {
            color: #f59e0b;
        }

        .total-counter-text {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 600;
        }

        .btn-write-review-top {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--transition-fast);
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
        }

        .btn-write-review-top:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        /* Reviews Stack list */
        .reviews-cards-list-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .review-row-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 20px;
            align-items: flex-start;
            transition: var(--transition-normal);
        }

        .review-row-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .reviewer-avatar-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            border: 1px solid rgba(11, 110, 79, 0.1);
        }

        .reviewer-body-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .reviewer-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .reviewer-meta-row h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .reviewer-date-badge {
            font-size: 0.8rem;
            color: var(--muted);
        }

        .reviewer-comment-text {
            font-size: 0.95rem;
            color: var(--text-main);
            line-height: 1.6;
            margin: 0;
            white-space: pre-wrap;
        }

        .empty-reviews-box {
            background: #ffffff;
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 60px 20px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 768px) {
            .reviews-summary-board {
                flex-direction: column;
                align-items: stretch;
            }
            
            .summary-left-side {
                flex-direction: column;
                text-align: center;
            }

            .btn-write-review-top {
                text-align: center;
            }

            .review-row-card {
                flex-direction: column;
                gap: 14px;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="container all-reviews-container">
    
    <a href="home.php" class="btn-back-link" onclick="(function(){ if (history.length>1) { history.back(); } else if (document.referrer) { window.location = document.referrer; } else { window.location = 'home.php'; } })(); return false;">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        <span>Voltar ao Início</span>
    </a>

    <!-- SUMMARY BOARD -->
    <div class="reviews-summary-board">
        <div class="summary-left-side">
            <div class="average-numeric"><?= htmlspecialchars(number_format($review_average, 1)) ?></div>
            <div class="summary-stars-column">
                <div class="stars-row">
                    <?php $full = (int)floor($review_average); for ($i = 1; $i <= 5; $i++): ?>
                        <span class="<?= $i <= $full ? 'filled' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div class="total-counter-text"><?= intval($review_count) ?> avaliações registadas</div>
            </div>
        </div>
        <a href="avaliacoes.php" class="btn-write-review-top">Deixar Minha Avaliação</a>
    </div>

    <!-- LIST OF REVIEWS -->
    <?php if (empty($reviews)): ?>
        <div class="empty-reviews-box">
            <p>Nenhuma avaliação disponível de momento. Seja o primeiro a opinar!</p>
        </div>
    <?php else: ?>
        <div class="reviews-cards-list-stack">
            <?php foreach ($reviews as $r): ?>
                <?php 
                $r_name = $r['nome'] ?: 'Anónimo';
                $r_name_parts = explode(' ', trim($r_name));
                $r_initials = '';
                if (count($r_name_parts) >= 2) {
                    $r_initials = strtoupper(substr($r_name_parts[0], 0, 1) . substr($r_name_parts[count($r_name_parts)-1], 0, 1));
                } else {
                    $r_initials = strtoupper(substr($r_name, 0, 2));
                }
                ?>
                <article class="review-row-card" aria-label="Avaliação de <?= htmlspecialchars($r_name) ?>">
                    <div class="reviewer-avatar-circle"><?= htmlspecialchars($r_initials) ?></div>
                    <div class="reviewer-body-right">
                        <div class="reviewer-meta-row">
                            <h3><?= htmlspecialchars($r_name) ?></h3>
                            <span class="reviewer-date-badge"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? ''))) ?></span>
                        </div>
                        <div class="stars-row">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <span class="<?= $s <= intval($r['classificacao']) ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <?php if (trim($r['observacoes'] ?? '') !== ''): ?>
                            <p class="reviewer-comment-text">"<?= nl2br(htmlspecialchars($r['observacoes'])) ?>"</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>
