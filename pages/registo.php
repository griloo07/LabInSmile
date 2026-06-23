<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$success_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: servicos.php');
    exit;
}

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
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['role'] = $role;
                session_regenerate_id(true);

                unset($_SESSION['redirect_after_login']);
                header('Location: servicos.php');
                exit;
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
    <title>Registar Conta - Lab in Smile</title>
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
            max-width: 440px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 24px;
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
            margin-bottom: 12px;
        }

        .form-group:last-of-type {
            margin-bottom: 18px;
        }

        label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        input {
            width: 100%;
            padding: 11px 14px;
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

        .invalid-field {
            border-color: var(--error) !important;
            background-color: #fef2f2 !important;
        }
        .invalid-field:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12) !important;
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
            <p>Registe uma nova conta profissional</p>
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
        
        <form method="POST" novalidate>
            <input type="hidden" name="form_type" value="register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="form-group">
                <label for="name">Nome Completo</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    placeholder="Ex: Gabriel Silva"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    required
                >
            </div>

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
                    placeholder="Pelo menos 6 caracteres"
                    required
                >
                <small class="field-help-text" style="color: var(--text-muted); font-size: 0.78rem; font-weight: 500; margin-top: 4px; display: block;">A palavra-passe deve ter no mínimo 6 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="password2">Confirmar Palavra-passe</label>
                <input 
                    type="password" 
                    id="password2" 
                    name="password2" 
                    placeholder="Repita a palavra-passe"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">Criar a minha conta</button>
        </form>
        
        <div class="login-footer">
            <?php
                $__login_next = '';
                if (!empty($_SESSION['redirect_after_login'])) {
                    $__login_next = '?next=' . urlencode($_SESSION['redirect_after_login']);
                }
            ?>
            <p>Já tem uma conta? <a href="login.php<?= $__login_next ?>">Entrar</a></p>
            <a href="home.php" class="btn-back-home">
                <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>Voltar ao website</span>
            </a>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    
    function setFieldError(field, isValid, message) {
        let errorEl = field.parentNode.querySelector('.field-error-text');
        let helpEl = field.parentNode.querySelector('.field-help-text');
        if (!isValid) {
            field.classList.add('invalid-field');
            if (helpEl) {
                helpEl.style.display = 'none';
            }
            if (!errorEl) {
                errorEl = document.createElement('span');
                errorEl.className = 'field-error-text';
                errorEl.style.color = 'var(--error)';
                errorEl.style.fontSize = '0.78rem';
                errorEl.style.fontWeight = '600';
                errorEl.style.marginTop = '4px';
                errorEl.style.display = 'block';
                field.parentNode.appendChild(errorEl);
            }
            errorEl.textContent = message;
        } else {
            field.classList.remove('invalid-field');
            if (helpEl) {
                helpEl.style.display = 'block';
            }
            if (errorEl) {
                errorEl.remove();
            }
        }
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            const fields = form.querySelectorAll('input[required]');
            const passwordField = document.getElementById('password');
            
            fields.forEach(field => {
                let isValid = true;
                let errorMsg = '';
                
                if (field.value.trim() === '') {
                    isValid = false;
                    if (field.id === 'password') {
                        errorMsg = 'A palavra-passe é obrigatória e deve ter pelo menos 6 caracteres.';
                    } else {
                        errorMsg = 'Este campo é de preenchimento obrigatório.';
                    }
                } else if (field.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        isValid = false;
                        errorMsg = 'Introduza um endereço de email válido (exemplo@email.com).';
                    }
                } else if (field.id === 'name' && field.value.trim().length < 2) {
                    isValid = false;
                    errorMsg = 'O nome deve conter pelo menos 2 caracteres.';
                } else if (field.id === 'password' && field.value.length < 6) {
                    isValid = false;
                    errorMsg = 'A palavra-passe deve conter pelo menos 6 caracteres.';
                } else if (field.id === 'password2' && field.value !== passwordField.value) {
                    isValid = false;
                    errorMsg = 'As palavras-passe não coincidem.';
                }
                
                setFieldError(field, isValid, errorMsg);
                if (!isValid) {
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.invalid-field');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });

        const fields = form.querySelectorAll('input');
        const passwordField = document.getElementById('password');
        const password2Field = document.getElementById('password2');

        fields.forEach(field => {
            field.addEventListener('input', function() {
                let isValid = true;
                let errorMsg = '';
                
                if (this.hasAttribute('required') && this.value.trim() === '') {
                    isValid = false;
                    if (this.id === 'password') {
                        errorMsg = 'A palavra-passe é obrigatória e deve ter pelo menos 6 caracteres.';
                    } else {
                        errorMsg = 'Este campo é de preenchimento obrigatório.';
                    }
                } else if (this.type === 'email' && this.value.trim() !== '') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(this.value.trim())) {
                        isValid = false;
                        errorMsg = 'Introduza um endereço de email válido (exemplo@email.com).';
                    }
                } else if (this.id === 'name' && this.value.trim() !== '' && this.value.trim().length < 2) {
                    isValid = false;
                    errorMsg = 'O nome deve conter pelo menos 2 caracteres.';
                } else if (this.id === 'password' && this.value !== '' && this.value.length < 6) {
                    isValid = false;
                    errorMsg = 'A palavra-passe deve conter pelo menos 6 caracteres.';
                } else if (this.id === 'password2' && this.value !== '' && this.value !== passwordField.value) {
                    isValid = false;
                    errorMsg = 'As palavras-passe não coincidem.';
                }

                setFieldError(this, isValid, errorMsg);
                
                if (this.id === 'password' && password2Field && password2Field.value !== '') {
                    const p2Valid = password2Field.value === this.value;
                    const p2Msg = p2Valid ? '' : 'As palavras-passe não coincidem.';
                    setFieldError(password2Field, p2Valid, p2Msg);
                }
            });
        });
    }
});
</script>
</body>
</html>
