<?php
session_start();
require_once __DIR__ . '/../database.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Serviços - LabInSmile</title>
    <?php require_once __DIR__ . '/../inc/site_head.php'; ?>
    <style>
        * { box-sizing: border-box; }
        body {
            <?php require_once __DIR__ . '/../inc/site_header.php'; ?>
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

        /* Page-specific tweaks: slightly larger lab name and button text, ensure vertical alignment */
        header .logo {
            display: inline-flex;
            align-items: center;
            font-weight: 700;
            font-size: 20px;
            color: #0b6e4f;
            cursor: pointer;
            line-height: 1;
            height: 48px;
            padding: 0 6px;
        }

        /* Slightly larger auth buttons on this page to match request (use global styles) */

          /* Ensure nav and controls align horizontally with logo */
          .topbar { align-items: center; }

          /* Force the right-side container in header to align contents vertically
              target the immediate div following the logo anchor */
          .topbar > div { display: flex; align-items: center; gap: 20px; margin-left: auto; width: auto !important; }
        main {
            min-height: calc(100vh - 280px);
            padding: 40px 15px;
        }
        main h1 {
            color: #0b6e4f;
            margin-bottom: 30px;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .service-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .service-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }
        .service-card h3 {
            color: #0b6e4f;
            margin-top: 0;
        }
        .service-card img {
            margin-top: 10px;
            width: 100%;
            border-radius: 8px;
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
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

    <a href="/LabInSmile/pages/admin.php" class="btn-admin">
        ⚙️ Painel Admin
    </a>

<?php endif; ?>
</head>
<body>

<header>
    <div class="container">
        <div class="topbar">
            <a href="home.php" class="logo" style="text-decoration: none; color: #0b6e4f; display:inline-flex; align-items:center; gap:8px;"> 
                <img src="../images/logo_labinsmile.png" alt="LabInSmile" style="height:30px; width:auto; border-radius:8px; object-fit:cover"> LabInSmile
            </a>

            <div class="top-right">
                
                <nav>
                    <a href="servicos.php" style="color: #0b6e4f; font-weight: bold;">Serviços</a>
                    <a href="especialidades.php">Especialidades</a>
                    <a href="contacto.php">Contacto</a>
                </nav>

                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span style="font-size: 14px; color: #6b7280;">
                            Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                        </span>
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
        <h1>Nossos Serviços</h1>
        
        <div class="services-grid">

        <?php
        $sql = "SELECT * FROM services";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {

            while ($row = $result->fetch_assoc()) {
        ?>

            <a href="servico.php?id=<?= $row['id'] ?>" style="text-decoration:none; color:inherit;">
    <div class="service-card">

        <?php if (!empty($row['imagem'])): ?>
            <img src="/laboratorio/images/<?= htmlspecialchars($row['imagem']) ?>">
        <?php endif; ?>
    </div>
</a>

        <?php
            }

        } else {
            echo "<p>Sem serviços disponíveis.</p>";
        }
        ?>

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
