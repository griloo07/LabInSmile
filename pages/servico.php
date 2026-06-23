<?php
session_start();
require_once __DIR__ . '/../config.php';

// Validar sessão ativa
if (!isset($_SESSION['user_id'])) {
    $redirect = 'servicos.php';
    if (isset($_GET['id'])) {
        $redirect = 'servico.php?id=' . urlencode((string)$_GET['id']);
    }
    $_SESSION['redirect_after_login'] = $redirect;
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if (!isset($_GET['id'])) {
    die("Serviço não encontrado.");
}

$id = intval($_GET['id']);

// Obter dados serviço
$sql = "SELECT * FROM services WHERE id = $id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Serviço não encontrado.");
}

$servico = $result->fetch_assoc();

function service_images($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) return array_values(array_filter($decoded, 'is_string'));
    return [$value];
}

$service_images = service_images($servico['imagem'] ?? '');

$mensagem_sucesso = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');

    if (strlen($nome) < 2) $errors[] = 'Indique o seu nome.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (strlen($mensagem) < 5) $errors[] = 'A mensagem é demasiado curta.';

    if (empty($errors)) {
        $null_val = null;
        $stmt = $conn->prepare("INSERT INTO pedidos (service_id, nome, email, telefone, mensagem, data_marcacao, hora_marcacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $id, $nome, $email, $telefone, $mensagem, $null_val, $null_val);

        if ($stmt->execute()) {
            $mensagem_sucesso = "Pedido de orçamento enviado com sucesso!";
            
            $subject = "Novo Pedido de Orçamento: " . ($servico['nome'] ?? 'Serviço');
            $body_mail = "Serviço: " . ($servico['nome'] ?? '') . "\n"
                       . "Nome: $nome\nEmail: $email\nTelefone: $telefone\nMensagem: $mensagem\n";

            if (function_exists('send_lab_email')) {
                send_lab_email($subject, $body_mail, $email, $nome);
            } elseif (function_exists('mail')) {
                $to = defined('LAB_EMAIL') ? LAB_EMAIL : 'labinsmile@gmail.com';
                $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
                @mail($to, $subject, $body_mail, $headers);
            }

            // Limpar os campos após sucesso
            $nome = '';
            $email = '';
            $telefone = '';
            $mensagem = '';
        } else {
            $errors[] = "Erro ao guardar o seu pedido.";
        }
        $stmt->close();
    }
}

$old_nome = htmlspecialchars($nome ?? '');
$old_email = htmlspecialchars($email ?? '');
$old_telefone = htmlspecialchars($telefone ?? '');
$old_mensagem = htmlspecialchars($mensagem ?? '');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($servico['nome']) ?> - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .service-detail-container {
            padding: 40px 0;
        }

        .btn-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 24px;
            transition: var(--transition-fast);
        }

        .btn-back-link:hover {
            color: var(--primary-700);
            transform: translateX(-2px);
        }

        .btn-back-link svg {
            width: 16px;
            height: 16px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 36px;
            align-items: start;
        }

        /* Gallery Carousel */
        .service-gallery-wrap {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .service-carousel-outer {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .carousel-slides-track {
            display: flex;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide-item {
            flex: 0 0 100%;
            aspect-ratio: 4/3;
            margin: 0;
        }

        .carousel-slide-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: var(--shadow-sm);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            z-index: 10;
            font-size: 1.15rem;
            transition: var(--transition-fast);
        }

        .nav-btn:hover {
            background: #ffffff;
            transform: translateY(-50%) scale(1.05);
        }

        .nav-btn.prev { left: 12px; }
        .nav-btn.next { right: 12px; }

        .dots-row {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 6px;
            z-index: 10;
        }

        .dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            border: none;
            cursor: pointer;
            padding: 0;
            transition: var(--transition-fast);
        }

        .dot-indicator.active {
            background: #ffffff;
            width: 18px;
            border-radius: 4px;
        }

        .service-description-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }

        .service-description-card h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 16px;
        }

        .service-description-card p {
            color: var(--text-main);
            line-height: 1.7;
            font-size: 0.95rem;
            margin: 0;
            white-space: pre-wrap;
        }

        /* Booking / Quote Form Panel */
        .booking-panel {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 100px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .booking-panel h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .booking-panel p.intro {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.5;
        }

        .booking-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-part-section {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 18px;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-part-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin: 0 0 4px;
        }

        .field-unit {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-unit label {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-main);
        }

        .booking-form input,
        .booking-form textarea,
        .booking-form select {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-main);
            padding: 10px 12px;
            font-size: 0.88rem;
            outline: none;
            transition: var(--transition-fast);
        }

        .booking-form input:focus,
        .booking-form textarea:focus,
        .booking-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .booking-form textarea {
            min-height: 80px;
            resize: vertical;
        }

        .booking-form select:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .timing-row-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 10px;
        }

        .timing-status-msg {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.4;
        }

        .btn-submit-booking {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit-booking:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        .booking-alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .booking-alert.success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #047857;
        }

        .booking-alert.error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .booking-alert ul {
            margin: 0;
            padding-left: 18px;
        }

        @media (max-width: 900px) {
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .booking-panel {
                position: static;
            }
        }

        @media (max-width: 480px) {
            .timing-row-grid {
                grid-template-columns: 1fr;
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

<main class="container service-detail-container">
    <a href="servicos.php" class="btn-back-link" id="btn-voltar">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        <span>Voltar aos Serviços</span>
    </a>

    <div class="detail-grid">
        <!-- DETAIL LEFT -->
        <div class="service-gallery-wrap">
            <div class="service-carousel-outer">
                <?php if (count($service_images) > 1): ?>
                    <div class="carousel-slides-track" id="gallery-track">
                        <?php foreach ($service_images as $image): ?>
                            <div class="carousel-slide-item">
                                <img src="/LabInSmile/images/<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($servico['nome']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="nav-btn prev" id="gallery-prev" aria-label="Imagem anterior">&#10094;</button>
                    <button type="button" class="nav-btn next" id="gallery-next" aria-label="Próxima imagem">&#10095;</button>
                    <div class="dots-row" id="gallery-dots"></div>
                <?php elseif (count($service_images) === 1): ?>
                    <div class="carousel-slide-item">
                        <img src="/LabInSmile/images/<?= htmlspecialchars($service_images[0]) ?>" alt="<?= htmlspecialchars($servico['nome']) ?>">
                    </div>
                <?php else: ?>
                    <div style="aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; color:#94a3b8; background:#ffffff;">
                        <svg style="width:48px; height:48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                <?php endif; ?>
            </div>

            <div class="service-description-card">
                <h2><?= htmlspecialchars($servico['nome']) ?></h2>
                <p><?= nl2br(htmlspecialchars($servico['descricao'])) ?></p>
            </div>
        </div>

        <!-- BOOKING PANEL RIGHT -->
        <aside class="booking-panel">
            <h3>Pedido de Orçamento</h3>
            <p class="intro">Insira os seus dados de contacto e envie o seu pedido de orçamento.</p>

            <?php if ($mensagem_sucesso): ?>
                <div class="booking-alert success"><?= htmlspecialchars($mensagem_sucesso) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="booking-alert error">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="booking-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Secção dados -->
                <div class="form-part-section">
                    <span class="form-part-title">Dados Pessoais</span>
                    
                    <div class="field-unit">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="O seu nome" required value="<?= $old_nome ?>">
                    </div>

                    <div class="field-unit">
                        <label for="email">Endereço de Email</label>
                        <input type="email" id="email" name="email" placeholder="email@clinica.pt" required value="<?= $old_email ?>">
                    </div>

                    <div class="field-unit">
                        <label for="telefone">Telemóvel / Telefone</label>
                        <input type="text" id="telefone" name="telefone" placeholder="Ex: 967544606" value="<?= $old_telefone ?>">
                    </div>
                </div>

                <!-- Secção de marcação -->
                <div class="form-part-section">
                    <span class="form-part-title">Detalhes do Pedido</span>

                    <div class="field-unit">
                        <label for="mensagem">Mensagem / Observações</label>
                        <textarea id="mensagem" name="mensagem" placeholder="Descreva os requisitos técnicos ou detalhes clínicos..." required><?= $old_mensagem ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit-booking">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <span>Solicitar orçamento</span>
                </button>
            </form>
        </aside>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
// Handle back button referrer path nicely
const btnVoltar = document.getElementById('btn-voltar');
if (btnVoltar) {
    btnVoltar.addEventListener('click', function(e) {
        e.preventDefault();
        const ref = document.referrer || '';
        if (ref && ref.indexOf('servicos.php') !== -1) {
            window.location.href = ref;
        } else {
            window.location.href = 'servicos.php';
        }
    });
}

// Photo Gallery Slider JS
const track = document.getElementById('gallery-track');
if (track) {
    const slides = Array.from(track.children);
    const prevBtn = document.getElementById('gallery-prev');
    const nextBtn = document.getElementById('gallery-next');
    const dotsContainer = document.getElementById('gallery-dots');
    let current = 0;

    slides.forEach((_, idx) => {
        const dot = document.createElement('button');
        dot.className = 'dot-indicator';
        if (idx === 0) dot.classList.add('active');
        dot.addEventListener('click', () => showSlide(idx));
        dotsContainer.appendChild(dot);
    });

    const dots = Array.from(dotsContainer.children);

    function showSlide(index) {
        current = (index + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        dots.forEach((dot, dotIdx) => dot.classList.toggle('active', dotIdx === current));
    }

    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => showSlide(current - 1));
        nextBtn.addEventListener('click', () => showSlide(current + 1));
    }
}

// Form client-side validation
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.booking-form');
    
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
                } else if (field.id === 'mensagem' && field.value.trim().length < 5) {
                    isValid = false;
                    errorMsg = 'A mensagem deve conter pelo menos 5 caracteres.';
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
                } else if (this.id === 'mensagem' && this.value.trim() !== '' && this.value.trim().length < 5) {
                    isValid = false;
                    errorMsg = 'A mensagem deve conter pelo menos 5 caracteres.';
                }

                setFieldError(this, isValid, errorMsg);
            });
        });
    }
});
</script>
</body>
</html>
