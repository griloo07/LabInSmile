<?php
session_start();
require_once __DIR__ . '/../config.php';

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

$sql = "SELECT * FROM services WHERE id = $id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Serviço não encontrado.");
}

$servico = $result->fetch_assoc();

function service_images($value) {
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

$service_images = service_images($servico['imagem'] ?? '');

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
    if (!$data_marcacao || !$hora_marcacao) $errors[] = 'Escolha data e hora.';

    if (empty($errors)) {

        // verificar conflito
        $check = $conn->prepare("\n            SELECT id FROM pedidos \n            WHERE data_marcacao = ? AND hora_marcacao = ?\n        ");

        $check->bind_param("ss", $data_marcacao, $hora_marcacao);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {

            $errors[] = "Este horário já está ocupado.";

        } else {

            $stmt = $conn->prepare("\n                INSERT INTO pedidos \n                (service_id, nome, email, telefone, mensagem, data_marcacao, hora_marcacao)\n                VALUES (?, ?, ?, ?, ?, ?, ?)\n            ");

            $stmt->bind_param(
                "issssss",
                $id,
                $nome,
                $email,
                $telefone,
                $mensagem,
                $data_marcacao,
                $hora_marcacao
            );

            if ($stmt->execute()) {
                $mensagem_sucesso = "Marcação feita com sucesso!";

                // Notificar laboratório por email sobre o novo pedido/agendamento
                $subject = "Pedido de Orçamento / Agendamento: " . (isset($servico['nome']) ? $servico['nome'] : 'Serviço');
                $body_mail = "Serviço: " . (isset($servico['nome']) ? $servico['nome'] : '') . "\n";
                $body_mail .= "Nome: $nome\nEmail: $email\nTelefone: $telefone\nMensagem: $mensagem\nData: $data_marcacao\nHora: $hora_marcacao\n";

                if (function_exists('send_lab_email')) {
                    $mail_ok = send_lab_email($subject, $body_mail, $email);
                } elseif (function_exists('mail')) {
                    $to = defined('LAB_EMAIL') ? LAB_EMAIL : 'labinsmile@gmail.com';
                    $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
                    $mail_ok = @mail($to, $subject, $body_mail, $headers);
                }

            } else {
                $errors[] = "Erro ao guardar a marcação.";
            }

            $stmt->close();
        }
    }
}

$old_nome = htmlspecialchars($nome ?? '');
$old_email = htmlspecialchars($email ?? '');
$old_telefone = htmlspecialchars($telefone ?? '');
$old_mensagem = htmlspecialchars($mensagem ?? '');
$old_data_marcacao = htmlspecialchars($data_marcacao ?? '');
$old_hora_marcacao = htmlspecialchars($hora_marcacao ?? '');
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($servico['nome']) ?></title>
<?php require_once __DIR__ . '/../includes/site_head.php'; ?>

<style>
.product-grid {
    display:grid;
    grid-template-columns:1fr 420px;
    gap:25px;
    align-items:start;
}

.product-image img {
    width:100%;
    border-radius:10px;
}

.service-carousel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(16,24,40,0.12);
    overflow: hidden;
    position: relative;
}

.service-carousel-track {
    display: flex;
    transition: transform .28s ease;
}

.service-carousel-slide {
    flex: 0 0 100%;
}

.service-carousel img,
.service-detail > img {
    width: 100%;
    display: block;
    aspect-ratio: 4 / 3;
    border-radius: 14px;
    object-fit: cover;
}

.service-carousel img {
    border-radius: 0;
}

.carousel-control {
    align-items: center;
    background: rgba(255,255,255,0.9);
    border: 0;
    border-radius: 50%;
    color: #0b6e4f;
    cursor: pointer;
    display: flex;
    font-size: 26px;
    height: 42px;
    justify-content: center;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 42px;
}

.carousel-control.prev {
    left: 12px;
}

.carousel-control.next {
    right: 12px;
}

.carousel-dots {
    bottom: 12px;
    display: flex;
    gap: 8px;
    justify-content: center;
    left: 0;
    position: absolute;
    right: 0;
}

.carousel-dot {
    background: rgba(255,255,255,0.7);
    border: 0;
    border-radius: 50%;
    cursor: pointer;
    height: 10px;
    padding: 0;
    width: 10px;
}

.carousel-dot.active {
    background: #0b6e4f;
}

.service-detail p {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(16,24,40,0.06);
    margin: 18px 0 0;
    padding: 18px;
}

.quote-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 16px 40px rgba(16,24,40,0.10);
    padding: 22px;
    position: sticky;
    top: 92px;
}

.quote-panel h3 {
    color: #0b6e4f;
    font-size: 22px;
    line-height: 1.2;
    margin: 0;
}

.quote-panel .form-intro {
    color: #6b7280;
    font-size: 14px;
    margin: 8px 0 18px;
}

.quote-form {
    display: grid;
    gap: 14px;
}

.form-section {
    border: 1px solid #edf0f2;
    border-radius: 14px;
    padding: 14px;
    transition: border-color .18s ease, box-shadow .18s ease;
}

.form-section:focus-within {
    border-color: rgba(11,110,79,0.35);
    box-shadow: 0 8px 22px rgba(11,110,79,0.08);
}

.form-section-title {
    color: #0b6e4f;
    font-size: 13px;
    font-weight: 800;
    margin: 0 0 12px;
    text-transform: uppercase;
}

.field-group {
    display: grid;
    gap: 6px;
    margin-bottom: 12px;
}

.field-group:last-child {
    margin-bottom: 0;
}

.field-group label {
    color: #374151;
    font-size: 13px;
    font-weight: 700;
    margin: 0;
}

.quote-form input,
.quote-form textarea,
.quote-form select {
    width: 100%;
    border: 1px solid #dfe5e9;
    border-radius: 10px;
    background: #fbfcfd;
    color: #111;
    font: inherit;
    padding: 11px 12px;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}

.quote-form textarea {
    min-height: 110px;
    resize: vertical;
}

.quote-form input:focus,
.quote-form textarea:focus,
.quote-form select:focus {
    background: #fff;
    border-color: #0b6e4f;
    box-shadow: 0 0 0 4px rgba(11,110,79,0.10);
    outline: none;
}

.quote-form select:disabled {
    color: #9ca3af;
    cursor: not-allowed;
}

.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.form-alert {
    border-radius: 12px;
    font-size: 14px;
    margin: 0 0 14px;
    padding: 12px 14px;
}

.form-alert.success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #047857;
}

.form-alert.error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}

.form-alert ul {
    margin: 0;
    padding-left: 18px;
}

.submit-request {
    align-items: center;
    background: #0b6e4f;
    border: 0;
    border-radius: 12px;
    color: #fff;
    cursor: pointer;
    display: inline-flex;
    font-weight: 800;
    justify-content: center;
    padding: 13px 18px;
    transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
    width: 100%;
}

.submit-request:hover {
    background: #0a5a41;
    box-shadow: 0 10px 22px rgba(11,110,79,0.22);
    transform: translateY(-1px);
}

.schedule-status {
    color: #6b7280;
    font-size: 13px;
    margin: -2px 0 0;
}

@media(max-width:900px){
    .product-grid{grid-template-columns:1fr;}
    .quote-panel{position:static;}
}

@media(max-width:560px){
    .schedule-grid{grid-template-columns:1fr;}
}
</style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main>
<div class="container">

<div class="product-grid">

    <div class="service-detail">
        <?php if (count($service_images) > 1): ?>
            <div class="service-carousel" data-carousel>
                <div class="service-carousel-track">
                    <?php foreach ($service_images as $image): ?>
                        <div class="service-carousel-slide">
                            <img src="/LabInSmile/images/<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($servico['nome']) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="carousel-control prev" data-carousel-prev aria-label="Imagem anterior">&lsaquo;</button>
                <button type="button" class="carousel-control next" data-carousel-next aria-label="Imagem seguinte">&rsaquo;</button>
                <div class="carousel-dots">
                    <?php foreach ($service_images as $index => $image): ?>
                        <button type="button" class="carousel-dot<?= $index === 0 ? ' active' : '' ?>" data-carousel-dot="<?= $index ?>" aria-label="Ver imagem <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (count($service_images) === 1): ?>
            <img src="/LabInSmile/images/<?= htmlspecialchars($service_images[0]) ?>" alt="<?= htmlspecialchars($servico['nome']) ?>">
        <?php endif; ?>

        <p><?= nl2br(htmlspecialchars($servico['descricao'])) ?></p>
    </div>

    <aside class="quote-panel">

        <h3>Pedido de Orçamento</h3>
        <p class="form-intro">Envie o seu pedido e escolha uma data para agendamento.</p>

        <?php if ($mensagem_sucesso): ?>
            <p class="form-alert success"><?= htmlspecialchars($mensagem_sucesso) ?></p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="form-alert error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="quote-form">

            <div class="form-section">
                <p class="form-section-title">Dados de contacto</p>

                <div class="field-group">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" placeholder="O seu nome" required value="<?= $old_nome ?>">
                </div>

                <div class="field-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="email@exemplo.pt" required value="<?= $old_email ?>">
                </div>

                <div class="field-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" placeholder="+351 000 000 000" value="<?= $old_telefone ?>">
                </div>
            </div>

            <div class="form-section">
                <p class="form-section-title">Pedido e agendamento</p>

                <div class="field-group">
                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" placeholder="Conte-nos o que precisa"><?= $old_mensagem ?></textarea>
                </div>

                <div class="schedule-grid">
                    <div class="field-group">
                        <label for="data_marcacao">Data</label>
                        <input type="date" id="data_marcacao" name="data_marcacao" min="<?= date('Y-m-d') ?>" required value="<?= $old_data_marcacao ?>">
                    </div>

                    <div class="field-group">
                        <label for="hora_marcacao">Hora</label>
                        <select id="hora_marcacao" name="hora_marcacao" required data-selected="<?= $old_hora_marcacao ?>" disabled>
                            <option value="">Escolha uma data</option>
                        </select>
                    </div>
                </div>
            </div>

            <p class="schedule-status" id="schedule-status">Escolha uma data para ver os horários disponíveis.</p>

            <button type="submit" class="submit-request">Enviar pedido</button>

        </form>

    </aside>

</div>
</div>
</main>

<script>
document.querySelectorAll('[data-carousel]').forEach(carousel => {
    const track = carousel.querySelector('.service-carousel-track');
    const slides = carousel.querySelectorAll('.service-carousel-slide');
    const dots = carousel.querySelectorAll('[data-carousel-dot]');
    const prev = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    let current = 0;

    function showSlide(index) {
        current = (index + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        dots.forEach((dot, dotIndex) => dot.classList.toggle('active', dotIndex === current));
    }

    if (prev && next && slides.length > 1) {
        prev.addEventListener('click', () => showSlide(current - 1));
        next.addEventListener('click', () => showSlide(current + 1));
        dots.forEach(dot => {
            dot.addEventListener('click', () => showSlide(Number(dot.dataset.carouselDot || 0)));
        });
    }
});

const dataInput = document.getElementById('data_marcacao');
const horaSelect = document.getElementById('hora_marcacao');
const scheduleStatus = document.getElementById('schedule-status');

function setScheduleStatus(message) {
    if (scheduleStatus) scheduleStatus.textContent = message;
}

if (dataInput) {
    const loadHorarios = function () {
        let data = this.value;

        horaSelect.innerHTML = '';
        horaSelect.disabled = true;

        if (!data) {
            horaSelect.innerHTML = '<option value="">Escolha uma data</option>';
            setScheduleStatus('Escolha uma data para ver os horarios disponiveis.');
            return;
        }

        const partes = data.split('-').map(Number);
        let dia = new Date(partes[0], partes[1] - 1, partes[2]).getDay();

        if (dia === 0 || dia === 6) {
            horaSelect.innerHTML = '<option value="">Clinica encerrada ao fim de semana</option>';
            setScheduleStatus('Escolha um dia util para continuar.');
            return;
        }

        horaSelect.innerHTML = '<option value="">A carregar horarios...</option>';
        setScheduleStatus('A procurar horarios disponiveis...');

        fetch('/LabInSmile/pages/get_horarios.php?data=' + data)
        .then(res => res.json())
        .then(horarios => {
            horaSelect.innerHTML = '';

            if (horarios.length === 0) {
                horaSelect.innerHTML = '<option value="">Sem horarios disponiveis</option>';
                setScheduleStatus('Nao existem horarios livres para esta data.');
                return;
            }

            const selectedHora = horaSelect.dataset.selected || '';
            let livres = 0;

            horarios.forEach(item => {
                let opt = document.createElement('option');
                opt.value = item.hora;

                if (item.ocupado) {
                    opt.textContent = item.hora + ' (Ocupado)';
                    opt.disabled = true;
                } else {
                    opt.textContent = item.hora + ' (Disponivel)';
                    livres++;
                }

                if (item.hora === selectedHora) opt.selected = true;
                horaSelect.appendChild(opt);
            });

            horaSelect.disabled = livres === 0;
            setScheduleStatus(livres > 0 ? livres + ' horario(s) disponivel(eis) para esta data.' : 'Nao existem horarios livres para esta data.');
        })
        .catch(err => {
            console.error(err);
            horaSelect.innerHTML = '<option value="">Erro ao carregar horarios</option>';
            setScheduleStatus('Nao foi possivel carregar os horarios. Tente novamente.');
        });
    };

    dataInput.addEventListener('change', loadHorarios);
    if (dataInput.value) dataInput.dispatchEvent(new Event('change'));
}
</script>

<a
    href="https://wa.me/351967544606?text=Olá,%20gostaria%20de%20obter%20mais%20informações."
    class="whatsapp-float"
    target="_blank"
>

    <img
        src="/LabInSmile/images/whatsapp.png"
        alt="WhatsApp"
    >

</a>
</body>
</html>
