<?php
session_start();
require_once __DIR__ . '/../config.php';

$errors = [];
$success_message = '';

if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['redirect_after_login'] ?? 'servicos.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

if (isset($_GET['next']) && $_GET['next'] !== '') {
    $next = filter_var($_GET['next'], FILTER_SANITIZE_URL);
    $parsed = parse_url($next);
    if (empty($parsed['scheme']) && empty($parsed['host']) && strpos($next, '//') === false) {
        $_SESSION['redirect_after_login'] = $next;
    }
} elseif (!isset($_SESSION['redirect_after_login']) && !empty($_SERVER['HTTP_REFERER'])) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'login') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $errors[] = 'O email é obrigatório.';
        }
        if (empty($password)) {
            $errors[] = 'A palavra-passe é obrigatória.';
        }

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

                    session_regenerate_id(true);

                    $redirect = $_SESSION['redirect_after_login'] ?? (isset($_POST['next']) ? $_POST['next'] : 'servicos.php');
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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        :root {
            --brand: #0b6e4f;
            --brand-dark: #074a35;
            --brand-light: #eefbf6;
            --bg: #f8fafc;
            --card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --error: #ef4444;
            --success: #10b981;
            --radius: 16px;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background: linear-gradient(135deg, #0d2219 0%, #050d09 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 28px;
            animation: cardFadeIn 0.4s ease-out;
        }

        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .login-header img {
            height: 52px;
            border-radius: 10px;
            margin-bottom: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .login-header h1 {
            margin: 0;
            color: var(--brand);
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            background: #f8fafc;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        input:focus {
            background: #ffffff;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .errors {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .errors ul {
            margin: 0;
            padding-left: 20px;
        }

        .errors li {
            margin-bottom: 4px;
        }

        .errors li:last-child {
            margin-bottom: 0;
        }

        .btn-login {
            width: 100%;
            background: var(--brand);
            color: white;
            padding: 13px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
            transition: var(--transition);
        }

        .btn-login:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: scale(0.99);
        }

        .login-footer {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            font-size: 0.85rem;
        }

        .login-footer p {
            margin: 0;
            color: var(--text-muted);
        }

        .login-footer a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 700;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .btn-back-home {
            color: var(--text-muted) !important;
            font-weight: 600 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-back-home:hover {
            color: var(--text-main) !important;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            .login-header h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="/LabInSmile/images/logo_labinsmile.png" alt="Lab in Smile Logo">
            <h1>Lab in Smile</h1>
            <p>Aceda à sua conta profissional</p>
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
        
        <form method="POST">
            <input type="hidden" name="form_type" value="login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($_SESSION['redirect_after_login'] ?? '') ?>">
            
            <div class="form-group">
                <label for="email">Endereço de Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="exemplo@email.com"
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
            
            <button type="submit" class="btn-login">Entrar na Área de Cliente</button>
        </form>
        
        <div class="login-footer">
            <p>Ainda não está registado? <a href="registo.php">Criar uma conta</a></p>
            <a href="home.php" class="btn-back-home">
                <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Voltar ao website</span>
            </a>
        </div>
    </div>
</body>
</html>
