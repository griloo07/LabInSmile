<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab in Smile - Próteses Dentárias</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        /* Hero Section styles */
        .home-hero {
            padding: 60px 0;
            background: linear-gradient(180deg, rgba(11, 110, 79, 0.03) 0%, rgba(248, 250, 252, 0) 100%);
        }

        .home-hero-inner {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .home-hero-text h1 {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.2;
            margin: 0 0 20px;
        }

        .home-hero-text .lead {
            font-size: 1.15rem;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .home-hero-image {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            aspect-ratio: 4/3;
        }

        .home-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-ctas {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .btn-hero-primary {
            background: var(--primary);
            color: #ffffff;
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition-fast);
            box-shadow: 0 10px 15px -3px rgba(11, 110, 79, 0.2);
        }

        .btn-hero-primary:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        .btn-hero-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text);
            padding: 12px 28px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition-fast);
        }

        .btn-hero-secondary:hover {
            background: #ffffff;
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Carousel Section */
        .installations-section {
            padding: 60px 0;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .section-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 12px;
        }

        .section-header p {
            color: var(--muted);
            margin: 0;
            font-size: 1rem;
        }

        .carousel-wrapper {
            position: relative;
            max-width: 860px;
            margin: 0 auto;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .installations-carousel {
            position: relative;
            width: 100%;
            height: 480px;
            overflow: hidden;
        }

        .carousel-track {
            display: flex;
            height: 100%;
            transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide {
            flex: 0 0 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: var(--shadow-md);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            z-index: 10;
            font-size: 1.25rem;
            transition: var(--transition-fast);
        }

        .carousel-btn:hover {
            background: #ffffff;
            transform: translateY(-50%) scale(1.05);
            color: var(--primary-700);
        }

        .carousel-btn.prev { left: 16px; }
        .carousel-btn.next { right: 16px; }

        .carousel-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }

        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            border: none;
            cursor: pointer;
            padding: 0;
            transition: var(--transition-fast);
        }

        .carousel-dot.active {
            background: #ffffff;
            width: 20px;
            border-radius: 4px;
        }

        /* Why Choose Us Grid */
        .why-choose-us {
            padding: 60px 0;
            background: #ffffff;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .why-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 40px;
        }

        .why-card {
            background: var(--bg);
            border-radius: var(--radius-md);
            padding: 30px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: var(--transition-normal);
        }

        .why-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(11, 110, 79, 0.15);
            background: #ffffff;
        }

        .why-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .why-icon svg {
            width: 24px;
            height: 24px;
        }

        .why-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        .why-card p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.6;
        }

        /* Testimonials styles */
        .testimonials-section {
            padding: 60px 0;
        }

        .rating-summary-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 24px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 36px;
            box-shadow: var(--shadow-sm);
        }

        .summary-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .average-big {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .rating-stars-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stars-row {
            display: flex;
            gap: 4px;
        }

        .stars-row span {
            color: #e2e8f0;
            font-size: 1.1rem;
        }

        .stars-row span.filled {
            color: #f59e0b;
        }

        .count-text {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
        }

        .btn-write-review {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(11, 110, 79, 0.1);
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .btn-write-review:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .reviews-cards-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .review-user-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-normal);
        }

        .review-user-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .review-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .review-user-info h4 {
            margin: 0 0 2px;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .review-user-info span {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .review-card-text {
            font-size: 0.9rem;
            color: var(--text);
            line-height: 1.6;
            margin: 0;
        }

        .btn-all-reviews {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition-fast);
        }

        .btn-all-reviews:hover {
            color: var(--primary-700);
        }

        .btn-all-reviews svg {
            width: 16px;
            height: 16px;
            transition: var(--transition-fast);
        }

        .btn-all-reviews:hover svg {
            transform: translateX(3px);
        }

        @media (max-width: 900px) {
            .home-hero-inner {
                grid-template-columns: 1fr;
                gap: 36px;
            }
            .home-hero-text {
                text-align: center;
            }
            .hero-ctas {
                justify-content: center;
            }
            .why-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .reviews-cards-list {
                grid-template-columns: 1fr;
            }
            .installations-carousel {
                height: 360px;
            }
        }

        @media (max-width: 600px) {
            .home-hero-text h1 {
                font-size: 2rem;
            }
            .why-grid {
                grid-template-columns: 1fr;
            }
            .rating-summary-card {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .summary-left {
                flex-direction: column;
            }
            .installations-carousel {
                height: 280px;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <!-- HERO SECTION -->
    <section class="home-hero">
        <div class="container home-hero-inner">
            <div class="home-hero-text">
                <h1>Lab in Smile — Próteses Dentárias de Excelência</h1>
                <p class="lead">Próteses personalizadas, acabamentos premium e controlo de qualidade rigoroso. Soluções de alta precisão projetadas para devolver a estética e o conforto natural ao sorriso.</p>
                <div class="hero-ctas">
                    <a href="servicos.php" class="btn-hero-primary">Consultar Serviços</a>
                </div>
            </div>
            <div class="home-hero-image">
                <img src="/LabInSmile/images/fundo estatico.jpeg" alt="Laboratório Lab in Smile - Próteses Dentárias">
            </div>
        </div>
    </section>

    <!-- CAROUSEL DE INSTALAÇÕES -->
    <section class="installations-section">
        <div class="container">
            <div class="section-header">
                <h2>As Nossas Instalações</h2>
            </div>

            <div class="carousel-wrapper">
                <div class="installations-carousel" id="installations-slider">
                    <div class="carousel-track" id="slider-track">
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3820.JPG" alt="Instalações - Laboratório área principal"></div>
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3822.JPG" alt="Instalações - Sala de gesso"></div>
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3823.JPG" alt="Instalações - Bancadas de trabalho"></div>
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3824.JPG" alt="Instalações - Detalhe bancada prótese acrílica"></div>
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3825.JPG" alt="Instalações - Área de polimento"></div>
                        <div class="carousel-slide"><img src="/LabInSmile/images/IMG_3826.JPG" alt="Instalações - Sala técnica CAD/CAM"></div>
                    </div>

                    <button type="button" class="carousel-btn prev" id="slider-prev" aria-label="Slide anterior">&#10094;</button>
                    <button type="button" class="carousel-btn next" id="slider-next" aria-label="Próximo slide">&#10095;</button>
                    
                    <div class="carousel-dots" id="slider-dots"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- PORQUÊ ESCOLHER A LAB IN SMILE -->
    <section class="why-choose-us">
        <div class="container">
            <div class="section-header">
                <h2>Porquê escolher a Lab in Smile</h2>
                <p>Nossos pilares de trabalho garantem a satisfação total e a precisão em cada solução protésica.</p>
            </div>
            
            <div class="why-grid">
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3>Garantia de qualidade</h3>
                    <p>Controlo minucioso em cada fase de fabricação, assegurando durabilidade e adaptação perfeita.</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3>Prazos confiáveis</h3>
                    <p>Compromisso com o cronograma estabelecido, garantindo pontualidade na entrega dos trabalhos.</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 9.172V5L8 4z"></path></svg>
                    </div>
                    <h3>Materiais premium</h3>
                    <p>Trabalhamos exclusivamente com matérias-primas certificadas dos melhores fornecedores mundiais.</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7h7m-7 0a5 5 0 11-10 0 5 5 0 0110 0z"></path></svg>
                    </div>
                    <h3>Atenção ao detalhe</h3>
                    <p>Escultura e acabamento personalizados que imitam com perfeição a anatomia dental natural.</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <h3>Suporte Técnico Total</h3>
                    <p>Canal de comunicação aberto com o médico dentista para alinhar detalhes anatômicos e estéticos.</p>
                </div>
                <div class="why-card">
                    <div class="why-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3>Satisfação do Paciente</h3>
                    <p>O sorriso final do paciente é o nosso principal indicador de sucesso e qualidade técnica.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTEMUNHOS / AVALIAÇÕES -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-header">
                <h2>O Que Dizem os Nossos Clientes</h2>
                <p>Opiniões de quem confia no nosso laboratório de próteses dentárias.</p>
            </div>

            <div class="rating-summary-card">
                <div class="summary-left">
                    <div class="average-big"><?= htmlspecialchars(number_format($review_average, 1)) ?></div>
                    <div class="rating-stars-container">
                        <div class="stars-row">
                            <?php $full = (int)floor($review_average); for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?= $i <= $full ? 'filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <div class="count-text"><?= intval($review_count) ?> avaliações no total</div>
                    </div>
                </div>
                <a href="avaliacoes.php" class="btn-write-review">Deixar Avaliação</a>
            </div>

            <?php if (!empty($recent_reviews)): ?>
                <div class="reviews-cards-list">
                    <?php foreach ($recent_reviews as $r): ?>
                        <div class="review-user-card">
                            <div class="review-card-header">
                                <div class="review-user-info">
                                    <h4><?= htmlspecialchars($r['nome']) ?></h4>
                                    <span><?= htmlspecialchars(date('d/m/Y', strtotime($r['created_at'] ?? ''))) ?></span>
                                </div>
                                <div class="stars-row">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <span class="<?= $s <= intval($r['classificacao']) ? 'filled' : '' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if (trim($r['observacoes'] ?? '') !== ''): ?>
                                <p class="review-card-text">"<?= nl2br(htmlspecialchars($r['observacoes'])) ?>"</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center;">
                    <a href="avaliacoes_todas.php" class="btn-all-reviews">
                        <span>Ver todas as avaliações</span>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
            <?php else: ?>
                <p style="text-align:center; color: var(--muted);">Ainda não existem avaliações. Seja o primeiro a avaliar!</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
// JS installations slider
document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('slider-track');
    const slides = Array.from(track ? track.children : []);
    const prevBtn = document.getElementById('slider-prev');
    const nextBtn = document.getElementById('slider-next');
    const dotsContainer = document.getElementById('slider-dots');

    if (!track || slides.length === 0) return;

    let index = 0;
    const max = slides.length;

    // Create pagination dots
    slides.forEach((_, i) => {
        const dot = document.createElement('button');
        dot.className = 'carousel-dot';
        if (i === 0) dot.classList.add('active');
        dot.setAttribute('aria-label', `Imagem ${i + 1}`);
        dot.addEventListener('click', () => {
            index = i;
            render();
        });
        dotsContainer.appendChild(dot);
    });

    const dots = Array.from(dotsContainer.children);

    function render() {
        track.style.transform = `translateX(-${index * 100}%)`;
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
    }

    prevBtn && prevBtn.addEventListener('click', () => {
        index = (index - 1 + max) % max;
        render();
    });

    nextBtn && nextBtn.addEventListener('click', () => {
        index = (index + 1) % max;
        render();
    });

    // Auto rotate every 5s
    let timer = setInterval(() => {
        index = (index + 1) % max;
        render();
    }, 5000);

    // Pause timer on user interaction
    const sliderContainer = document.getElementById('installations-slider');
    if (sliderContainer) {
        sliderContainer.addEventListener('mouseenter', () => clearInterval(timer));
        sliderContainer.addEventListener('mouseleave', () => {
            clearInterval(timer);
            timer = setInterval(() => {
                index = (index + 1) % max;
                render();
            }, 5000);
        });
    }
});
</script>
</body>
</html>
