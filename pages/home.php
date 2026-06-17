<?php
// Iniciar a sessão
session_start();
require_once __DIR__ . '/../config.php';

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Buscar estatísticas avaliações
$review_count = 0;
$review_average = 0.0;
$recent_reviews = [];
@$res_stats = $conn->query("SELECT COUNT(*) AS cnt, AVG(classificacao) AS avg_rating FROM avaliacoes");
if ($res_stats) {
    $stats = $res_stats->fetch_assoc();
    $review_count = intval($stats['cnt'] ?? 0);
    $review_average = isset($stats['avg_rating']) && $stats['avg_rating'] !== null ? floatval($stats['avg_rating']) : 0.0;
}

// Buscar avaliações recentes
@$res_reviews = $conn->query("SELECT nome, classificacao, observacoes, created_at FROM avaliacoes ORDER BY created_at DESC LIMIT 4");
if ($res_reviews) {
    while ($r = $res_reviews->fetch_assoc()) { $recent_reviews[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Lab in Smile - Próteses Dentárias</title>

    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        /* Estrelas douradas personalizadas */
        body.has-bg .reviews-stars .star,
        body.has-bg .reviews-list .star,
        body.has-bg .review-item .star {
            color: #f59e0b !important;
            text-shadow: 0 1px 0 rgba(255,255,255,0.75), 0 6px 18px rgba(0,0,0,0.12);
            transform: translateY(-1px);
        }
        body.has-bg .reviews-stars .star { font-size:18px }
        body.has-bg .reviews-list .star { font-size:16px }
    </style>
</head>

<body class="has-bg">

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>

    <!-- Apresentação do laboratório -->
    <section class="home-hero" aria-label="Apresentação">
        <div class="container home-hero-inner">
            <div class="home-hero-image">
                <img src="/LabInSmile/images/fundo estatico.jpeg" alt="Laboratório Lab in Smile - próteses dentárias">
            </div>

            <div class="home-hero-text">
                <h1>Lab in Smile — Próteses Dentárias de Excelência</h1>
                <p class="lead">Próteses personalizadas, acabamentos premium e controlo de qualidade rigoroso — soluções pensadas para devolver conforto e estética ao sorriso.</p>
            </div>
        </div>
    </section>

    <!-- Vantagens do laboratório -->
    <section class="why-choose-us">
        <div class="container">
            <div class="why-box">
                <h2>Porquê escolher a Lab in Smile</h2>
                <div class="expertise-grid">
                    <div class="expertise-card">
                        <p class="description">Experiência de anos na indústria de próteses dentárias</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Atenção ao detalhe em cada projeto</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Prazos de entrega justos e confiáveis</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Suporte total ao cliente durante o processo</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Materiais premium de fornecedores conhecidos</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Garantia de satisfação nos trabalhos realizados</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Apelo à avaliação -->
    <section class="cta-box" aria-label="Avaliações">
        <div class="container">
            <h2>Gostou do nosso trabalho?</h2>
            <p>Partilhe a avaliação — é rápida e ajuda-nos a melhorar.</p>
            <a href="avaliacoes.php" class="btn-orcamento">Deixe uma avaliação</a>
        </div>
    </section>

    <!-- Mostrar avaliações recentes -->
    <section class="reviews-section" aria-label="Avaliações recentes">
        <div class="container">
            <div class="reviews-header">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="reviews-average"><?= htmlspecialchars(number_format($review_average, 1)) ?> <small style="font-size:14px;color:var(--muted);margin-left:6px">/5</small></div>
                    <div class="reviews-stars">
                        <?php $full = (int)floor($review_average); for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $full ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="small-muted"><?= intval($review_count) ?> avaliações</div>
            </div>

            <?php if (!empty($recent_reviews)): ?>
                <div class="reviews-list">
                    <?php foreach ($recent_reviews as $r): ?>
                        <div class="review-item">
                            <div class="review-meta">
                                <div class="review-name"><?= htmlspecialchars($r['nome']) ?></div>
                                <div class="small-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($r['created_at'] ?? ''))) ?></div>
                            </div>
                            <div class="reviews-stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <span class="star <?= $s <= intval($r['classificacao']) ? 'filled' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <?php if (trim($r['observacoes'] ?? '') !== ''): ?>
                                <div class="review-text"><?= nl2br(htmlspecialchars($r['observacoes'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="small-muted" style="text-align:center;margin-top:12px">Ainda não existem avaliações — seja o primeiro a avaliar.</p>
            <?php endif; ?>

            <div class="reviews-cta">
                <a href="avaliacoes_todas.php" class="btn-small" aria-label="Ver todas as avaliações">Ver todas as avaliações</a>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>

