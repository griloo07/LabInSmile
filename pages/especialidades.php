<?php
session_start();
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Especialidades - LabInSmile</title>
    <?php require_once __DIR__ . '/../inc/site_head.php'; ?>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #f7f9fb;
        }
        header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 15px;
        }
        .topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .logo {
            font-weight: bold;
            font-size: 18px;
            color: #0b6e4f;
        }
        nav {
            display: flex;
            gap: 5px;
        }
        nav a {
            text-decoration: none;
            color: #111;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        nav a:hover {
            background: #eef2f5;
            color: #0b6e4f;
        }
        /* Auth button styles moved to global style.css for consistent subtle design */
        main {
            min-height: calc(100vh - 280px);
            padding: 40px 15px;
        }
        main h1 {
            color: #0b6e4f;
            margin-bottom: 30px;
        }
        .expertise-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .expertise-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .expertise-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }
        .expertise-card h3 {
            color: #0b6e4f;
            margin-top: 0;
        }
        .expertise-card .description {
            color: #6b7280;
            font-size: 14px;
        }
        .features-list {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .features-list h3 {
            color: #0b6e4f;
            margin-top: 0;
        }
        .features-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .features-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
        }
        .features-list li:last-child {
            border-bottom: none;
        }
        .features-list li:before {
            content: "✓";
            color: #0b6e4f;
            font-weight: bold;
            margin-right: 12px;
            font-size: 18px;
        }
        footer {
            background: #111;
            color: #fff;
            padding: 40px 15px 20px;
            text-align: center;
            font-size: 14px;
        }
        footer p {
            margin: 8px 0;
        }
        @media (max-width: 600px) {
            nav { order: 3; width: 100%; margin-top: 10px; }
            nav a { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="topbar">
            <a href="home.php" class="logo" style="text-decoration: none; color: #0b6e4f; display:flex; align-items:center; gap:8px;"> 
                <img src="../images/logo_labinsmile.png" alt="LabInSmile" style="height:30px; width:auto; border-radius:8px; object-fit:cover"> LabInSmile
            </a>
            <div class="top-right">
                <nav>
                    <a href="servicos.php">Serviços</a>
                    <a href="especialidades.php" style="color: #0b6e4f; font-weight: bold;">Especialidades</a>
                    <a href="contacto.php">Contacto</a>
                </nav>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span style="font-size: 14px; color: #6b7280;">Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
                        <a href="logout.php" class="btn-login">Sair</a>
                    <?php else: ?>
                        <a href="login.php" class="btn-login">Login</a>
                        <a href="registo.php" class="btn-login">Registar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <h1>Nossas Especialidades</h1>
        
        <div class="expertise-grid">
            <div class="expertise-card">
                <h3>🖥️ CAD/CAM</h3>
                <p class="description">Desenho e manufactura assistida por computador para precisão milimétrica em cada projeto. Tecnologia de ponta para resultados impecáveis.</p>
            </div>
            
            <div class="expertise-card">
                <h3>🔧 Modelação Digital</h3>
                <p class="description">Modelos 3D avançados que permitem visualizar e ajustar cada detalhe antes da fabricação. Máxima precisão garantida.</p>
            </div>
            
            <div class="expertise-card">
                <h3>⚡ Prototipagem Rápida</h3>
                <p class="description">Fabrico ágil de protótipos para testes e validação. Reduz tempo de produção sem comprometer a qualidade.</p>
            </div>
            
            <div class="expertise-card">
                <h3>🎨 Colorimetria Avançada</h3>
                <p class="description">Análise precisa de cores para garantir correspondência perfeita com dentes naturais. Acabamento estético personalizado.</p>
            </div>
            
            <div class="expertise-card">
                <h3>✅ Controlo de Qualidade</h3>
                <p class="description">Inspecção rigorosa de cada etapa do processo. Certificações e padrões internacionais respeitados.</p>
            </div>
            
            <div class="expertise-card">
                <h3>💡 Inovação Contínua</h3>
                <p class="description">Investimento em pesquisa e desenvolvimento de técnicas e materiais inovadores para melhores resultados.</p>
            </div>
        </div>

    </div>
</main>

<footer>
    <div class="container">
        <strong>LabInSmile - Próteses Dentárias</strong>
        <p>Telefone: +351 967 544 606</p>
        <p>Email: labinsmile@gmail.com</p>
        <p>Morada: Avenida da República, Nº 74 1.º Andar Sala 1 Paredes</p>
    </div>
</footer>
</body>
</html>
