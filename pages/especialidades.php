<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Especialidades - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .especialidades-hero {
            padding: 80px 0 50px;
            background: linear-gradient(180deg, rgba(11, 110, 79, 0.04) 0%, rgba(248, 250, 252, 0) 100%);
            text-align: center;
        }

        .especialidades-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 16px;
        }

        .especialidades-hero p {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .especialidades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            padding: 40px 0 80px;
        }

        .especialidade-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            align-items: center;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-normal);
        }

        .especialidade-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(11, 110, 79, 0.15);
        }

        .especialidade-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .especialidade-icon svg {
            width: 28px;
            height: 28px;
        }

        .especialidade-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            line-height: 1.4;
        }

        .especialidade-card p {
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 600px) {
            .especialidades-hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <!-- HERO -->
    <section class="especialidades-hero">
        <div class="container">
            <h1>As Nossas Especialidades</h1>
            <p>Trabalhos protéticos e ortodônticos confecionados com rigor técnico avançado e matérias-primas premium.</p>
        </div>
    </section>

    <!-- GRID -->
    <section class="container">
        <div class="especialidades-grid">
            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                </div>
                <h3>Consertos acrílica e esquelética</h3>
                <p>Reparos rápidos e reforços em próteses danificadas para restaurar a integridade funcional imediata.</p>
            </div>
            
            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v16M5 12H2a10 10 0 0 0 20 0h-3"></path>
                        <circle cx="12" cy="5" r="3"></circle>
                    </svg>
                </div>
                <h3>Prótese sobre implantes</h3>
                <p>Reabilitações implanto-suportadas unitárias ou múltiplas com alto rigor estético e ajuste preciso.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 6c3-2 11-2 14 0"></path>
                        <path d="M5 10c3-2 11-2 14 0"></path>
                        <path d="M5 6v4M8 5v4M12 4v4M16 5v4M19 6v4" stroke-dasharray="1 1"></path>
                        <path d="M12 13v6M9 16l3-3 3 3"></path>
                    </svg>
                </div>
                <h3>Ortodontia removível</h3>
                <p>Aparelhos removíveis funcionais e placas de contenção confecionados sob medida.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 9c4-3 12-3 16 0v2c-2.5 3-13.5 3-16 0V9z" fill="currentColor" opacity="0.1"></path>
                        <path d="M4 9c4-3 12-3 16 0v3c-2.5 3-13.5 3-16 0V9z"></path>
                        <path d="M8 10v2M12 9.5v3M16 10v2"></path>
                    </svg>
                </div>
                <h3>Prótese acrílica</h3>
                <p>Próteses totais e parciais acrílicas confecionadas com dentes de alta estética e caracterização de gengiva.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 16c4 3 12 3 16 0" stroke-width="2.5"></path>
                        <path d="M8 16v-6M16 16v-6M12 16v-4"></path>
                        <path d="M8 9c0-2-3-2-3 1"></path>
                        <path d="M16 9c0-2 3-2 3 1"></path>
                    </svg>
                </div>
                <h3>Prótese esquelética</h3>
                <p>Estruturas metálicas finas e confortáveis (Cr-Co) com ganchos perfeitamente planejados.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h18"></path>
                        <rect x="5" y="10" width="4" height="4" rx="0.5"></rect>
                        <rect x="10" y="10" width="4" height="4" rx="0.5"></rect>
                        <rect x="15" y="10" width="4" height="4" rx="0.5"></rect>
                        <path d="M7 9v6M12 9v6M17 9v6M5 12h14"></path>
                    </svg>
                </div>
                <h3>Ortodontia fixa</h3>
                <p>Preparação de bandas, arcos e acessórios de soldagem de alta precisão para aparelhos fixos.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h3>Goteira de desporto</h3>
                <p>Placas de proteção e alinhadores em material termomoldável resiliente, ideais para atletas.</p>
            </div>

            <div class="especialidade-card">
                <div class="especialidade-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        <line x1="9" y1="10" x2="15" y2="10"></line>
                        <line x1="9" y1="14" x2="15" y2="14"></line>
                        <line x1="9" y1="18" x2="13" y2="18"></line>
                    </svg>
                </div>
                <h3>Estudo e Planeamento</h3>
                <p>Modelos de estudo anatômicos e enceramentos diagnósticos para previsibilidade clínica total.</p>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>
