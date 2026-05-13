<?php
session_start();
require_once __DIR__ . '/../config.php';

// gerar CSRF (deve já existir mas garantir)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$success_message = '';

// processar envio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'register') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($name) < 2) {
            $errors[] = 'Indique o seu nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'A palavra-passe deve ter pelo menos 6 caracteres.';
        }
        if ($password !== $password2) {
            $errors[] = 'As palavras-passe não coincidem.';
        }

        // Verificar se email já existe na base de dados
        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = 'Já existe uma conta com esse email.';
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, ?)');
            $role = 'user';
            $stmt->bind_param('ssss', $email, $hash, $name, $role);
            
            if ($stmt->execute()) {
                $success_message = 'Conta criada com sucesso. Pode agora <a href="login.php">entrar</a>.';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            } else {
                $errors[] = 'Não foi possível gravar a conta no servidor: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?><!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo - Laboratório de Prótese</title>
    <?php require_once __DIR__ . '/../inc/site_head.php'; ?>
    <style>
        /* reuse same styles as login page */
        :root {
            --brand: #0b6e4f;
            --bg: #f7f9fb;
            --card: #ffffff;
            --muted: #6b7280;
            --error: #dc2626;
            --success: #059669;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background-color: var(--bg);
            background-image: linear-gradient(135deg, #0b6e4f 0%, #1a472a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            margin: 20px;
        }
        h1 { text-align: center; color: var(--brand); margin-bottom: 30px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(11,110,79,0.1); }
        .errors { background: #fee2e2; border:1px solid #fecaca; color: var(--error); padding:12px; border-radius:8px; margin-bottom:20px; }
        .success { background: #dcfce7; border:1px solid #bbf7d0; color: var(--success); padding:12px; border-radius:8px; margin-bottom:20px; }
        .btn { width:100%; background:var(--brand); color:white; padding:12px; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; transition:background .3s; }
        .btn:hover { background:#0a5a41; }
        .footer { text-align:center; margin-top:20px; font-size:14px; }
        .footer a { color:var(--brand); text-decoration:none; }
        .footer a:hover { text-decoration:underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registe-se</h1>
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success"><?= $success_message ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="form_type" value="register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <label for="password">Palavra-passe</label>
            <input type="password" id="password" name="password" required>
            <label for="password2">Repita a palavra-passe</label>
            <input type="password" id="password2" name="password2" required>
            <button type="submit" class="btn">Criar Conta</button>
        </form>
        <div class="footer">
            <p>Já tem conta? <a href="login.php">Entrar</a></p>
            <p><a href="home.php">← Voltar ao site</a></p>
        </div>
    </div>
</body>
</html>