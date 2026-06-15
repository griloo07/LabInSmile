<?php
session_start();
require_once __DIR__ . '/../config.php';

function portfolio_images($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }

    return [$value];
}

function ensure_portfolio_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS portfolio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT NOT NULL,
        imagem TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_portfolio_table($conn);

$result = $conn->query("SELECT * FROM portfolio ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Portfólio - LabInSmile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        main { padding: 36px 12px; min-height: calc(100vh - 260px); }
        .container { max-width:1100px; margin:0 auto; }
        .portfolio-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:18px; }
        .portfolio-card { background:#fff; border-radius:10px; padding:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); text-decoration:none; color:inherit; }
        .portfolio-card img { width:100%; aspect-ratio:4/3; object-fit:cover; border-radius:8px; display:block; }
        .portfolio-card h3 { color: #0b6e4f; font-size:16px; margin:10px 0 6px; }
        .portfolio-card p { color:#374151; font-size:14px; margin:0; }
        .manage-link { display:inline-block; margin-bottom:12px; padding:8px 12px; background:#0b6e4f; color:#fff; border-radius:8px; text-decoration:none }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <div class="container">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px;">
            <h1>Portfólio</h1>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="manage_portfolio.php" class="manage-link">Painel Portfolio</a>
            <?php endif; ?>
        </div>

        <div class="portfolio-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $images = portfolio_images($row['imagem'] ?? ''); ?>
                    <div class="portfolio-card">
                        <?php if (!empty($images)): ?>
                            <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['titulo']) ?>">
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($row['titulo']) ?></h3>
                        <p><?= nl2br(htmlspecialchars(substr($row['descricao'], 0, 240))) ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Sem exemplos no portefólio por enquanto.</p>
            <?php endif; ?>
        </div>
    </div>
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

<a href="https://wa.me/351967544606?text=Olá,%20gostaria%20de%20obter%20mais%20informações." class="whatsapp-float" target="_blank">
    <img src="/LabInSmile/images/whatsapp.png" alt="WhatsApp">
</a>

</body>
</html>
