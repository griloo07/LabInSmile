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
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Todas as Avaliações — LabInSmile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .all-reviews { padding: 36px 16px; max-width:1100px; margin:0 auto }
        .all-reviews-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px }
        .all-reviews-title { font-size:22px; font-weight:800; color:var(--primary) }
        .reviews-summary { color:var(--muted); font-size:14px }
        .all-list { display:flex; flex-direction:column; gap:12px }
        .review-card { background:var(--surface); padding:18px; border-radius:12px; box-shadow:var(--shadow-sm); display:flex; gap:12px; align-items:flex-start }
        .review-card .meta { min-width:140px }
        .review-name { font-weight:800; color:var(--dark) }
        .review-date { color:var(--muted); font-size:13px }
        .review-body { flex:1 }
        .review-body .reviews-stars { margin-bottom:8px }
        .no-reviews { text-align:center; color:var(--muted); margin-top:24px }
        .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
        .btn-ghost { background:transparent; border:1px solid #eef5f2; padding:8px 12px; border-radius:8px; color:var(--primary); text-decoration:none; font-weight:700 }
        .btn-ghost:hover { background: rgba(11,110,79,0.04); transform:translateY(-1px) }
        @media (max-width:900px) { .review-card { flex-direction:column } .meta { min-width:auto } .actions { justify-content:flex-start } }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="all-reviews">
    <div class="all-reviews-header">
        <div style="display:flex;flex-direction:column;gap:10px">
            <a href="#" class="card-btn ghost" onclick="(function(){ if (history.length>1) { history.back(); } else if (document.referrer) { window.location = document.referrer; } else { window.location = 'home.php'; } })(); return false;" aria-label="Voltar">← Voltar</a>
            <div>
                <div class="all-reviews-title">Avaliações</div>
                <div class="reviews-summary"><?= intval($review_count) ?> avaliações — média <?= htmlspecialchars(number_format($review_average,1)) ?>/5</div>
            </div>
        </div>
        <div class="actions" aria-hidden="true"></div>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="no-reviews">Ainda não existem avaliações — seja o primeiro a avaliar.</div>
    <?php else: ?>
        <div class="all-list">
            <?php foreach ($reviews as $r): ?>
                <article class="review-card" aria-label="Avaliação de <?= htmlspecialchars($r['nome']) ?>">
                    <div class="meta">
                        <div class="review-name"><?= htmlspecialchars($r['nome']) ?></div>
                        <div class="review-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['created_at'] ?? '')))?></div>
                    </div>
                    <div class="review-body">
                        <div class="reviews-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <span class="star <?= $s <= intval($r['classificacao']) ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <?php if (trim($r['observacoes'] ?? '') !== ''): ?>
                            <div class="review-text"><?= nl2br(htmlspecialchars($r['observacoes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

</body>
</html>
