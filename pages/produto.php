<?php
session_start();
require_once __DIR__ . '/../database.php';

// garantir token CSRF para formulários (usado apenas para aparência compatível com contacto)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// validar ID
if (!isset($_GET['id'])) {
    die("Produto não encontrado.");
}

$id = intval($_GET['id']);

// buscar produto
$sql = "SELECT * FROM services WHERE id = $id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Produto não encontrado.");
}

$produto = $result->fetch_assoc();

// enviar pedido
$mensagem_sucesso = "";
$errors = [];

// enviar pedido
$mensagem_sucesso = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $data_marcacao = $_POST['data_marcacao'] ?? '';
    $hora_marcacao = $_POST['hora_marcacao'] ?? '';

    if (strlen($nome) < 2) $errors[] = 'Indique o seu nome.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (strlen($mensagem) < 5) $errors[] = 'A mensagem é demasiado curta.';

    if (empty($errors)) {

        // 🔍 VERIFICAR SE JÁ EXISTE MARCAÇÃO
        $check = $conn->prepare("
            SELECT id FROM pedidos 
            WHERE data_marcacao = ? AND hora_marcacao = ?
        ");

        $check->bind_param("ss", $data_marcacao, $hora_marcacao);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {

            $errors[] = "Este horário já está ocupado.";

        } else {

            // ✅ GUARDAR NA BD
            $stmt = $conn->prepare("
                INSERT INTO pedidos (service_id, nome, email, telefone, mensagem, data_marcacao, hora_marcacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("issssss", $id, $nome, $email, $telefone, $mensagem, $data_marcacao, $hora_marcacao);

            if ($stmt->execute()) {
                $mensagem_sucesso = "Marcação feita com sucesso!";
            } else {
                $errors[] = "Erro ao guardar a marcação.";
            }

            $stmt->close();
        }
    }
}

    // preservar valores submetidos para repopular o formulário
    $old_nome = htmlspecialchars($nome ?? '');
    $old_email = htmlspecialchars($email ?? '');
    $old_telefone = htmlspecialchars($telefone ?? '');
    $old_mensagem = htmlspecialchars($mensagem ?? '');
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title><?= htmlspecialchars($produto['nome']) ?> - LabInSmile</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .product-grid { display: grid; grid-template-columns: 1fr 420px; gap: 28px; align-items:start; }
        .product-image { background: var(--surface); padding: 18px; border-radius: 12px; box-shadow: var(--shadow-sm); }
        .product-image img { width:100%; height:auto; border-radius:8px; cursor: zoom-in; }
        .product-details { background: var(--surface); padding: 20px; border-radius: 12px; box-shadow: var(--shadow-sm); }
        .product-price { font-size: 22px; color: var(--primary); font-weight: 800; margin-top: 10px; }
        @media (max-width: 900px) { .product-grid { grid-template-columns: 1fr; } }

        /* Simple image modal */
        .img-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:2000; }
        .img-modal img { max-width:90%; max-height:90%; border-radius:8px; box-shadow:var(--shadow-md); }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div class="topbar">
            <a href="home.php" class="logo" style="text-decoration: none; color: var(--primary);">LabInSmile</a>
            <div style="display:flex;align-items:center;gap:20px;margin-left:auto;width:100%;justify-content:flex-end;">
                <nav>
                    <a href="produtos.php">Produtos</a>
                    <a href="especialidades.php">Especialidades</a>
                    <a href="contacto.php">Contacto</a>
                </nav>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span style="font-size:14px;color:var(--muted);">Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?></span>
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
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:18px;">
            <h1 style="margin:0; color:var(--primary);"><?= htmlspecialchars($produto['nome']) ?></h1>
        </div>

        <div class="product-grid">
            <div>
                <div class="product-image">
                    <?php if (!empty($produto['imagem'])): ?>
                        <img id="main-image" src="/laboratorio/images/<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>">
                    <?php else: ?>
                        <div style="padding:80px;text-align:center;color:var(--muted);">Sem imagem</div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:18px;" class="product-details">
                    <h3>Descrição</h3>
                    <p><?= nl2br(htmlspecialchars($produto['descricao'])) ?></p>
                </div>
            </div>

            <aside>
                <div class="contact-form">
                    <h3 id="formulario">Pedido de Orçamento</h3>

                        <?php if ($mensagem_sucesso): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($mensagem_sucesso) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-error">
                                <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="form-orcamento" id="orcamento-form">
                            <input type="hidden" name="form_type" value="contact">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="service_id" value="<?= $id ?>">
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome" required value="<?= $old_nome ?>">

                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required value="<?= $old_email ?>">

                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" value="<?= $old_telefone ?>">

                            <label for="mensagem">Mensagem</label>
                            <textarea id="mensagem" name="mensagem" rows="5"><?= $old_mensagem ?></textarea>
                            <label>Data da marcação:</label>
<label>Data:</label>
<input type="date" id="data_marcacao" name="data_marcacao" required>

<label>Hora:</label>
<select id="hora_marcacao" name="hora_marcacao" required>
    <option value="">Escolha primeiro uma data</option>
</select>

                            <button type="submit">Enviar Pedido</button>
                        </form>
                </div>
            </aside>
        </div>
    </div>
</main>

<div id="imgModal" class="img-modal" aria-hidden="true">
    <img id="modalImg" src="" alt="">
</div>

<footer>
    <div class="container">
        <strong>LabInSmile - Próteses Dentárias</strong>
        <p>Telefone: +351 967 544 606</p>
        <p>Email: labinsmile@gmail.com</p>
        <p>Morada: Avenida da República, Nº 74 1.º Andar Sala 1 Paredes</p>
    </div>
</footer>

<script>
// Image modal
const mainImg = document.getElementById('main-image');
const imgModal = document.getElementById('imgModal');
const modalImg = document.getElementById('modalImg');
if (mainImg) {
    mainImg.addEventListener('click', () => {
        modalImg.src = mainImg.src;
        imgModal.style.display = 'flex';
        imgModal.setAttribute('aria-hidden', 'false');
    });
}
imgModal.addEventListener('click', () => { imgModal.style.display = 'none'; imgModal.setAttribute('aria-hidden', 'true'); });

// Simple client-side validation and UX
const form = document.getElementById('orcamento-form');
if (form) {
    form.addEventListener('submit', (e) => {
        const nome = document.getElementById('nome').value.trim();
        const email = document.getElementById('email').value.trim();
        const mensagem = document.getElementById('mensagem').value.trim();
        if (nome.length < 2 || !email.includes('@') || mensagem.length < 3) {
            e.preventDefault();
            alert('Preencha corretamente os campos: Nome, Email e Mensagem.');
            return false;
        }
        // allow normal submit (server will handle persistence)
    });
}

// Smooth scroll to form if anchor present
if (location.hash === '#formulario') {
    document.getElementById('formulario')?.scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>