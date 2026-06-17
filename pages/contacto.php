<?php
// Iniciar a sessão
session_start();
require_once __DIR__ . '/../config.php';

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$success_message = '';

// Processar envio contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'contact') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $nome      = trim($_POST['nome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $telefone  = trim($_POST['telefone'] ?? '');
        $assunto   = trim($_POST['assunto'] ?? '');
        if ($assunto === '') $assunto = 'Pedido de Orçamento';
        $mensagem  = trim($_POST['mensagem'] ?? '');

        // Validar dados formulário
        if (strlen($nome) < 2) $errors[] = 'Indique o seu nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($mensagem) < 10) $errors[] = 'A mensagem deve ter pelo menos 10 caracteres.';

        if (empty($errors)) {
            $body = "Nome: $nome\nEmail: $email\nTelefone: $telefone\nAssunto: $assunto\n\nMensagem:\n$mensagem";
            $mail_ok = false;
            
            // Enviar email notificações
            if (function_exists('send_lab_email')) {
                $mail_ok = send_lab_email($assunto, $body, $email);
            } elseif (function_exists('mail')) {
                $to = defined('LAB_EMAIL') ? LAB_EMAIL : 'labinsmile@gmail.com';
                $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
                $mail_ok = @mail($to, $assunto, $body, $headers);
            }

            // Guardar na base
            $stmt = $conn->prepare('INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $nome, $email, $telefone, $assunto, $mensagem);
            
            if ($stmt->execute()) {
                $success_message = 'Mensagem registada com sucesso. Entraremos em contacto brevemente!';
            } else {
                $errors[] = 'Erro ao gravar a mensagem.';
            }
            $stmt->close();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Contacto - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        /* Estilos do contacto */
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
        main {
            min-height: calc(100vh - 280px);
            padding: 40px 15px;
        }
        main h1 {
            color: #0b6e4f;
            margin-bottom: 30px;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .contact-info, .contact-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .contact-info h3 {
            color: #0b6e4f;
            margin-top: 0;
        }
        .info-item {
            margin-bottom: 20px;
        }
        .info-item strong {
            display: block;
            color: #0b6e4f;
            margin-bottom: 5px;
        }
        .contact-form h3 {
            color: #0b6e4f;
            margin-top: 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #111;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-family: Arial, sans-serif;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #0b6e4f;
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.1);
        }
        button {
            background: #0b6e4f;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        button:hover {
            background: #0a5a41;
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #059669;
        }
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        .alert li {
            margin: 5px 0;
        }
        .map-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-top: 40px;
        }
        .map-container h3 {
            color: #0b6e4f;
            margin-top: 0;
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
        @media (max-width: 800px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 600px) {
            nav { order: 3; width: 100%; margin-top: 10px; }
            nav a { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <div class="container">
        <h1>Contacto</h1>
        
        <!-- Formulário de contacto -->
        <div class="contact-grid">
            <div class="contact-info">
                <h3>Informações de Contacto</h3>
                
                <div class="info-item">
                    <strong>Telefone</strong>
                    <a href="tel:+351967544606" style="color: #0b6e4f; text-decoration: none;">+351 967 544 606</a>
                </div>
                
                <div class="info-item">
                    <strong>Email</strong>
                    <a href="mailto:labinsmile@gmail.com" style="color: #0b6e4f; text-decoration: none;">labinsmile@gmail.com</a>
                </div>
                
                <div class="info-item">
                    <strong>Morada</strong>
                    <p style="margin: 5px 0;">Avenida da República, Nº 74<br>1.º Andar Sala 1<br>Paredes</p>
                </div>

                <div class="info-item">
                    <strong>Horário</strong>
                    <p style="margin: 5px 0;">Segunda a Sexta<br>9:00 - 18:00</p>
                </div>
            </div>

            <div class="contact-form">
                <h3>Envie-nos uma Mensagem</h3>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="form_type" value="contact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" required>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>

                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone">

                    <label for="assunto">Assunto</label>
                    <input type="text" id="assunto" name="assunto">

                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" rows="6" required></textarea>

                    <button type="submit">Enviar Mensagem</button>
                </form>
            </div>
        </div>

        <!-- Mapa de localização -->
        <div class="map-container">
            <h3>Localização</h3>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d48027.26873053879!2d-8.401739778320323!3d41.20642000000002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd248e133bc47247%3A0x35cfd536c5f59b31!2sLab%20in%20Smile%20Pr%C3%B3teses%20Dent%C3%A1rias%20Unipessoal%2C%20lda!5e0!3m2!1spt-PT!2spt!4v1763716313385!5m2!1spt-PT!2spt"
                width="100%"
                height="350"
                style="border:0; border-radius:8px;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

</body>
</html>

