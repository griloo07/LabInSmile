<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .about-hero {
            padding: 80px 0 50px;
            background: linear-gradient(180deg, rgba(11, 110, 79, 0.04) 0%, rgba(248, 250, 252, 0) 100%);
            text-align: center;
        }

        .about-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 16px;
        }

        .about-hero p {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .about-content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
            padding: 40px 0 80px;
        }

        .about-text {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .about-text h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 8px;
        }

        .about-text p {
            font-size: 1rem;
            color: var(--text);
            line-height: 1.7;
            margin: 0;
        }

        .about-image {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            aspect-ratio: 16/10;
        }

        .about-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Team Section styles */
        .team-section {
            background: #ffffff;
            border-top: 1px solid var(--border-color);
            padding: 80px 0;
            margin-top: 20px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 36px;
            max-width: 860px;
            margin: 40px auto 0;
        }

        .team-card {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition-normal);
        }

        .team-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-md);
            border-color: rgba(11, 110, 79, 0.2);
        }

        .team-photo-wrapper {
            width: 100%;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .team-photo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .avatar-f1 {
            transform: scale(1.0);
            transform-origin: center;
        }

        .avatar-f2 {
            transform: scale(1.16);
            transform-origin: center 25%;
        }

        .team-card:hover .avatar-f1 {
            transform: scale(1.04);
        }

        .team-card:hover .avatar-f2 {
            transform: scale(1.20);
            transform-origin: center 25%;
        }

        .team-info {
            padding: 24px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
        }

        .team-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .team-info .role {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .team-info .license {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: -4px;
            margin-bottom: 2px;
        }

        .team-info p.bio {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 900px) {
            .about-content-grid {
                grid-template-columns: 1fr;
                gap: 36px;
            }
        }

        @media (max-width: 768px) {
            .team-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
        }

        @media (max-width: 600px) {
            .about-hero h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <!-- HERO -->
    <section class="about-hero">
        <div class="container">
            <h1>Sobre a Lab in Smile</h1>
            <p>Conheça a história, os valores e o compromisso técnico do nosso laboratório de próteses dentárias.</p>
        </div>
    </section>

    <!-- CONTENT GRID -->
    <section class="container">
        <div class="about-content-grid">
            <div class="about-text">
                <h2>Tecnologia e Arte Dental</h2>
                <p>A Lab in Smile nasceu com o compromisso de aliar a tecnologia protética mais recente ao trabalho manual detalhado de técnicos de prótese dentária altamente qualificados. Desenvolvemos soluções personalizadas que satisfazem tanto as exigências de adaptação dos profissionais de saúde oral quanto o desejo estético dos pacientes.</p>
                <p>Localizados em Paredes, servimos clínicas de toda a região com rigor e dedicação. Cada trabalho de reabilitação oral que confecionamos reflete o nosso profissionalismo e o respeito pela saúde dos pacientes.</p>
            </div>
            <div class="about-image">
                <img src="/LabInSmile/images/IMG_3820.JPG" alt="Instalações do laboratório de prótese Lab in Smile">
            </div>
        </div>
    </section>

    <!-- EQUIPA -->
    <section class="team-section">
        <div class="container">
            <div class="section-header" style="text-align: center; max-width: 600px; margin: 0 auto 20px;">
                <h2 style="font-size: 2rem; font-weight: 800; color: var(--primary); margin: 0 0 12px;">A Nossa Equipa</h2>
                <p style="color: var(--muted); margin: 0; font-size: 1rem;">Profissionais dedicados a moldar e devolver o sorriso ideal a cada paciente.</p>
            </div>

            <div class="team-grid">
                <div class="team-card">
                    <div class="team-photo-wrapper">
                        <img src="/LabInSmile/images/f1.jpeg" alt="Membro da Equipa 1" class="avatar-f1">
                    </div>
                    <div class="team-info">
                        <h3>Sílvia Silva</h3>
                        <span class="role">Administrativa/Auxiliar de Prótese Dentária</span>
                        <p class="bio">Co-fundadora da Lab in Smile e Responsável Administrativa.
Com um percurso iniciado em 2016 na área da saúde, contando com mais de 20 anos de experiência em gestão de empresas e 10 anos como Auxiliar de Prótese Dentária. </p>
                    </div>
                </div>

                <div class="team-card">
                    <div class="team-photo-wrapper">
                        <img src="/LabInSmile/images/f2.jpeg" alt="Membro da Equipa 2" class="avatar-f2">
                    </div>
                    <div class="team-info">
                        <h3>Joel Nogueira</h3>
                        <span class="role">Técnico de Prótese Dentária</span>
                        <span class="license">Cédula profissional nº C-059643137</span>
                        <p class="bio">Fundador da Lab in Smile e Responsável Técnico.
Com um percurso iniciado em 1997 e Licenciatura em Prótese Dentária (2013-2016), lidero a equipa da Lab in Smile unindo décadas de experiência prática à inovação clínica, garantindo a máxima excelência em cada trabalho.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>
