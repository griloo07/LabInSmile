<?php
session_start();
require_once __DIR__ . '/../config.php';

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$errors = [];
$success_message = '';

$nome      = '';
$email     = '';
$telefone  = '';
$assunto   = '';
$mensagem  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'contact') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $nome      = trim($_POST['nome'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $telefone  = trim($_POST['telefone'] ?? '');
        $assunto   = trim($_POST['assunto'] ?? '');
        $mensagem  = trim($_POST['mensagem'] ?? '');

        if (strlen($nome) < 2) $errors[] = 'Indique o seu nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (strlen($mensagem) < 10) $errors[] = 'A mensagem deve ter pelo menos 10 caracteres.';

        if (empty($errors)) {
            $assunto_envio = $assunto === '' ? 'Pedido de Orçamento' : $assunto;
            $body = "Nome: $nome\nEmail: $email\nTelefone: $telefone\nAssunto: $assunto_envio\n\nMensagem:\n$mensagem";
            
            // Enviar email notificações
            if (function_exists('send_lab_email')) {
                send_lab_email($assunto_envio, $body, $email, $nome);
            } elseif (function_exists('mail')) {
                $to = defined('LAB_EMAIL') ? LAB_EMAIL : 'labinsmile@gmail.com';
                $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
                @mail($to, $assunto_envio, $body, $headers);
            }

            // Guardar na base
            $stmt = $conn->prepare('INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $nome, $email, $telefone, $assunto_envio, $mensagem);
            
            if ($stmt->execute()) {
                $success_message = 'Mensagem registada com sucesso. Entraremos em contacto brevemente!';
                // Limpar campos após submissão bem-sucedida
                $nome      = '';
                $email     = '';
                $telefone  = '';
                $assunto   = '';
                $mensagem  = '';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .contact-hero-wrap {
            padding: 80px 0 50px;
            background: linear-gradient(180deg, rgba(11, 110, 79, 0.04) 0%, rgba(248, 250, 252, 0) 100%);
            text-align: center;
        }

        .contact-hero-wrap h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 16px;
        }

        .contact-hero-wrap p {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .contact-layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 36px;
            padding: 40px 0 60px;
            align-items: start;
        }

        .contact-info-panel {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .contact-info-panel h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .info-card-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .info-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .info-card-content h3 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--text-main);
        }

        .info-card-content p, .info-card-content a {
            font-size: 0.9rem;
            color: var(--muted);
            margin: 0;
            line-height: 1.5;
            text-decoration: none;
        }

        .info-card-content a:hover {
            color: var(--primary);
        }

        /* Contact Form styles */
        .contact-form-panel {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .contact-form-panel h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .contact-html-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .field-label-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-label-group label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .contact-html-form input,
        .contact-html-form textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.9rem;
            background: var(--bg);
            color: var(--text-main);
            outline: none;
            transition: var(--transition-fast);
        }

        .contact-html-form input:focus,
        .contact-html-form textarea:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .contact-html-form textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn-send-message {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
            transition: var(--transition-fast);
        }

        .btn-send-message:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        .alert-banner {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .alert-banner.success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #047857;
        }

        .alert-banner.error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .alert-banner ul {
            margin: 0;
            padding-left: 18px;
        }

        /* Map styling */
        .map-card-outer {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 60px;
        }

        .map-card-outer h2 {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 16px;
        }

        @media (max-width: 900px) {
            .contact-layout-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }

        .invalid-field {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
        .invalid-field:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12) !important;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
    <!-- HERO -->
    <section class="contact-hero-wrap">
        <div class="container">
            <h1>Fale Connosco</h1>
            <p>Entre em contacto para solicitar orçamentos, esclarecer dúvidas ou iniciar uma parceria protética.</p>
        </div>
    </section>

    <!-- CONTENT GRID -->
    <section class="container">
        <div class="contact-layout-grid">
            
            <!-- LEFT INFO PANEL -->
            <aside class="contact-info-panel">
                <h2>Canais de Contacto</h2>

                <div class="info-card-item">
                    <div class="info-card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    </div>
                    <div class="info-card-content">
                        <h3>Telefone</h3>
                        <a href="tel:+351967544606">+351 967 544 606</a>
                    </div>
                </div>

                <div class="info-card-item">
                    <div class="info-card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <div class="info-card-content">
                        <h3>Email</h3>
                        <a href="mailto:labinsmile@gmail.com">labinsmile@gmail.com</a>
                    </div>
                </div>

                <div class="info-card-item">
                    <div class="info-card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div class="info-card-content">
                        <h3>Morada</h3>
                        <p>Avenida da República, Nº 74<br>1.º Andar Sala 1<br>4580-058 Paredes, Portugal</p>
                    </div>
                </div>

                <div class="info-card-item">
                    <div class="info-card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="info-card-content">
                        <h3>Horário de Atendimento</h3>
                        <p>Segunda a Sexta-feira<br>9:00 - 13:00 / 14:00 - 18:00</p>
                    </div>
                </div>
            </aside>

            <!-- RIGHT FORM PANEL -->
            <div class="contact-form-panel">
                <h2>Envie uma Mensagem</h2>

                <?php if (!empty($errors)): ?>
                    <div class="alert-banner error">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert-banner success">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="contact-html-form" novalidate>
                    <input type="hidden" name="form_type" value="contact">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="field-label-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="O seu nome" required value="<?= htmlspecialchars($nome) ?>">
                    </div>

                    <div class="field-label-group">
                        <label for="email">Endereço de Email</label>
                        <input type="email" id="email" name="email" placeholder="seu@email.com" required value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <div class="field-label-group">
                        <label for="telefone">Telefone / Telemóvel</label>
                        <input type="text" id="telefone" name="telefone" placeholder="Ex: 910 000 000" value="<?= htmlspecialchars($telefone) ?>">
                    </div>

                    <div class="field-label-group">
                        <label for="assunto">Assunto</label>
                        <input type="text" id="assunto" name="assunto" placeholder="Ex: Pedido de orçamento" value="<?= htmlspecialchars($assunto) ?>">
                    </div>

                    <div class="field-label-group">
                        <label for="mensagem">Mensagem</label>
                        <textarea id="mensagem" name="mensagem" placeholder="Insira o conteúdo da sua mensagem..." required><?= htmlspecialchars($mensagem) ?></textarea>
                    </div>

                    <button type="submit" class="btn-send-message">Enviar Mensagem</button>
                </form>
            </div>
        </div>

        <!-- LOCATION MAP -->
        <div class="map-card-outer">
            <h2>Nossa Localização</h2>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d48027.26873053879!2d-8.401739778320323!3d41.20642000000002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd248e133bc47247%3A0x35cfd536c5f59b31!2sLab%20in%20Smile%20Pr%C3%B3teses%20Dent%C3%A1rias%20Unipessoal%2C%20lda!5e0!3m2!1spt-PT!2spt!4v1763716313385!5m2!1spt-PT!2spt"
                width="100%"
                height="380"
                style="border:0; border-radius:8px;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.contact-html-form');
    
    function setFieldError(field, isValid, message) {
        let errorEl = field.parentNode.querySelector('.field-error-text');
        if (!isValid) {
            field.classList.add('invalid-field');
            if (!errorEl) {
                errorEl = document.createElement('span');
                errorEl.className = 'field-error-text';
                errorEl.style.color = '#ef4444';
                errorEl.style.fontSize = '0.78rem';
                errorEl.style.fontWeight = '600';
                errorEl.style.marginTop = '4px';
                errorEl.style.display = 'block';
                field.parentNode.appendChild(errorEl);
            }
            errorEl.textContent = message;
        } else {
            field.classList.remove('invalid-field');
            if (errorEl) {
                errorEl.remove();
            }
        }
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            let hasError = false;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                let isValid = true;
                let errorMsg = '';
                
                if (field.value.trim() === '') {
                    isValid = false;
                    errorMsg = 'Este campo é de preenchimento obrigatório.';
                } else if (field.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        isValid = false;
                        errorMsg = 'Introduza um endereço de email válido (exemplo@email.com).';
                    }
                } else if (field.id === 'mensagem' && field.value.trim().length < 10) {
                    isValid = false;
                    errorMsg = 'A mensagem deve conter pelo menos 10 caracteres.';
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

        const fields = form.querySelectorAll('input, textarea');
        fields.forEach(field => {
            field.addEventListener('input', function() {
                let isValid = true;
                let errorMsg = '';
                
                if (this.hasAttribute('required') && this.value.trim() === '') {
                    isValid = false;
                    errorMsg = 'Este campo é de preenchimento obrigatório.';
                } else if (this.type === 'email' && this.value.trim() !== '') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(this.value.trim())) {
                        isValid = false;
                        errorMsg = 'Introduza um endereço de email válido (exemplo@email.com).';
                    }
                } else if (this.id === 'mensagem' && this.value.trim() !== '' && this.value.trim().length < 10) {
                    isValid = false;
                    errorMsg = 'A mensagem deve conter pelo menos 10 caracteres.';
                }

                setFieldError(this, isValid, errorMsg);
            });
        });
    }
});
</script>

</body>
</html>
