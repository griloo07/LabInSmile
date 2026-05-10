<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>LabInSmile - Próteses Dentárias</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="has-bg">
<header>
    <div class="container">
        <div class="topbar">
            <div class="logo">
                <img src="../images/logo labinsmile.jpeg" alt="LabInSmile">
                LabInSmile
            </div>
            <div class="top-right">
                <nav>
                    <a href="produtos.php">Produtos</a>
                    <a href="especialidades.php">Especialidades</a>
                    <a href="contacto.php">Contacto</a>
                </nav>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="user-info">Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="manage_users.php" class="btn-login btn-admin">Gerir Utilizadores</a>
                        <?php endif; ?>
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
    <!-- Hero section removed as requested -->

    <section class="why-choose-us">
        <div class="container">
            <div class="why-box">
                <h2>Porquê Escolher a LabInSmile</h2>
                <div class="expertise-grid">
                    <div class="expertise-card">
                        <p class="description">Experiência de anos na indústria de próteses dentárias</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Equipamento de última geração e atualizado regularmente</p>
                    </div>
                    <div class="expertise-card">
                        <p class="description">Equipa de profissionais altamente qualificados</p>
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

    <!-- Seção de tópicos removida conforme pedido (Precisão, Qualidade, Profissionalismo) -->

    <section class="cta-section">
        <div class="container">
            <div class="cta-box">
                <h2>Próteses Dentárias Personalizadas</h2>
                <p>Qualidade, precisão e acabamento artesanal com tecnologia avançada</p>
                <a href="contacto.php" class="btn-orcamento">Pedir Orçamento</a>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        <strong>LabInSmile - Próteses Dentárias</strong>
        <p>Telefone: +351 967 544 606</p>
        <p>Email: labinsmile@gmail.com</p>
        <p>Morada: Avenida da República, Nº 74 1.º Andar Sala 1 Paredes</p>
        <p class="copyright">© 2026 LabInSmile. Todos os direitos reservados.</p>
    </div>
</footer>
</body>
</html>