<?php
// Shared header (logo, nav, auth buttons). Expects session already started.
?>
<header>
    <div class="container">
        <div class="topbar">
            <a href="home.php" class="logo" style="text-decoration: none; color: var(--primary); display:flex; align-items:center; gap:8px;"> 
                <img src="/LabInSmile/images/logo_labinsmile.png" alt="LabInSmile" style="height:30px; width:auto; border-radius:8px; object-fit:cover"> LabInSmile
            </a>

            <div class="top-right">
                <nav id="main-nav">
                    <a href="servicos.php">Serviços</a>
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
<style>
header nav a,
header .auth-buttons a,
header .auth-buttons .btn-login,
header .auth-buttons .btn-admin {
    color: var(--primary) !important;
    background: transparent !important;
    border: 0 !important;
    font-weight: 700 !important;
    text-decoration: none !important;
}
</style>
