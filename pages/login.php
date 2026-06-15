<?php
session_start();
require_once __DIR__ . '/../config.php';

$errors = [];
$success_message = '';

// Se o utilizador já está autenticado, redireciona para home
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['redirect_after_login'] ?? 'servicos.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

// Guardar página de origem passada por GET para redirecionamento pós-login
if (isset($_GET['next']) && $_GET['next'] !== '') {
    $next = filter_var($_GET['next'], FILTER_SANITIZE_URL);
    $parsed = parse_url($next);
    // aceitar apenas paths locais (sem scheme/host) e sem '//' para evitar open-redirects
    if (empty($parsed['scheme']) && empty($parsed['host']) && strpos($next, '//') === false) {
        $_SESSION['redirect_after_login'] = $next;
    }
} elseif (!isset($_SESSION['redirect_after_login']) && !empty($_SERVER['HTTP_REFERER'])) {
    // fallback para HTTP_REFERER quando o referer pertence ao mesmo host
    $referer = $_SERVER['HTTP_REFERER'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $ref_host = parse_url($referer, PHP_URL_HOST);
    if ($ref_host === $host) {
        $ref_path = parse_url($referer, PHP_URL_PATH) ?? '';
        $ref_query = parse_url($referer, PHP_URL_QUERY);
        if ($ref_query) $ref_path .= '?' . $ref_query;
        if ($ref_path) {
            $_SESSION['redirect_after_login'] = $ref_path;
        }
    }
}

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'login') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validação
        if (empty($email)) {
            $errors[] = 'O email é obrigatório.';
        }
        if (empty($password)) {
            $errors[] = 'A palavra-passe é obrigatória.';
        }

        // Autenticação baseada em base de dados MySQL
        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id, password_hash, name, role FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    // Regenerar ID da sessão para segurança
                    session_regenerate_id(true);

                    // Redirecionar para página protegida ou para o `next` passado pelo formulário/GET
                    $redirect = $_SESSION['redirect_after_login'] ?? (isset($_POST['next']) ? $_POST['next'] : 'servicos.php');
                    // sanitizar fallback para evitar open-redirect
                    $parsed_r = parse_url($redirect);
                    if (isset($parsed_r['scheme']) || isset($parsed_r['host']) || strpos($redirect, '//') !== false) {
                        $redirect = 'servicos.php';
                    }
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $errors[] = 'Email ou palavra-passe incorretos.';
                }
            } else {
                $errors[] = 'Email ou palavra-passe incorretos.';
            }
            $stmt->close();
        }
    }
}

// Gerar CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Laboratório de Prótese</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
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
        
        .login-container {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            margin: 20px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            margin: 0;
            color: var(--brand);
            font-size: 28px;
            font-weight: bold;
        }
        
        .login-header p {
            color: var(--muted);
            margin-top: 8px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #111;
            font-size: 14px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.1);
        }
        
        .errors {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .errors ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .errors li {
            margin-bottom: 4px;
        }
        
        .success-message {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: var(--success);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .btn-login {
            width: 100%;
            background: var(--brand);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
        }
        
        .btn-login:hover {
            background: #0a5a41;
        }
        
        .btn-login:active {
            transform: scale(0.98);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .login-footer a {
            color: var(--brand);
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .demo-credentials {
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            color: #166534;
        }
        
        .demo-credentials strong {
            display: block;
            margin-bottom: 6px;
        }
        
        @media (max-width: 600px) {
            .login-container {
                padding: 30px;
                max-width: 100%;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>LabInSmile</h1>
            <p>Sistema de Acesso</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="form_type" value="login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($_SESSION['redirect_after_login'] ?? '') ?>">
            
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="seu@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password">Palavra-passe</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">Entrar</button>
        </form>
        <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                // Debug removed in cleanup: console.log('login form submitted');
            });
        </script>
        <div class="login-footer">
            <p>Não tem conta? <a href="registo.php">Registe-se aqui</a></p>
            <p><a href="home.php">← Voltar ao site</a></p>
        </div>
    </div>
</body>
</html>
