<?php
// Cabeçalho da página

// Definir redirecionamento login
$__current_uri = $_SERVER['REQUEST_URI'] ?? '';
$__login_next = '';
if ($__current_uri && strpos($__current_uri, '/login.php') === false) {
    $__login_next = '?next=' . urlencode($__current_uri);
}
?>
<header class="main-site-header">
    <div class="container header-container">
        <a href="home.php" class="logo"> 
            <img src="/LabInSmile/images/logo_labinsmile.png" alt="Lab in Smile Logo"> 
            <span>Lab in Smile</span>
        </a>

        <!-- Hamburger Menu Button for Mobile -->
        <button class="mobile-nav-toggle" id="mobile-nav-toggle" aria-label="Abrir Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="top-right" id="nav-menu">
            <nav id="main-nav">
                <a href="home.php" class="nav-link">Início</a>
                <a href="servicos.php" class="nav-link">Serviços</a>
                <a href="portfolio.php" class="nav-link">Portfólio</a>
                <a href="especialidades.php" class="nav-link">Especialidades</a>
                <a href="about.php" class="nav-link">Sobre Nós</a>
                <a href="contacto.php" class="nav-link nav-contact-btn">Contacto</a>
            </nav>

            <!-- Mostrar botões autenticação -->
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="user-profile-badge">
                        <span class="user-info">Olá, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></strong></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin.php" class="btn-admin-panel">Painel Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn-logout-link">Sair</a>
                    </div>
                <?php else: ?>
                    <a href="login.php<?= htmlspecialchars($__login_next, ENT_QUOTES) ?>" class="btn-login-action">Entrar</a>
                    <a href="registo.php" class="btn-register-action">Registar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('mobile-nav-toggle');
    const menu = document.getElementById('nav-menu');
    
    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            toggle.classList.toggle('active');
            menu.classList.toggle('active');
        });
    }
});
</script>

