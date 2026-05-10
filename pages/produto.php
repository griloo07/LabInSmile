<?php
session_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if (!isset($_GET['id'])) {
    die("Produto não encontrado.");
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM services WHERE id = $id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    die("Produto não encontrado.");
}

$produto = $result->fetch_assoc();

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

            $stmt = $conn->prepare("
                INSERT INTO pedidos 
                (service_id, nome, email, telefone, mensagem, data_marcacao, hora_marcacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

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
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($produto['nome']) ?></title>
<link rel="stylesheet" href="../style.css">

<style>
.product-grid {
    display:grid;
    grid-template-columns:1fr 420px;
    gap:25px;
}

.product-image img {
    width:100%;
    border-radius:10px;
}

@media(max-width:900px){
    .product-grid{grid-template-columns:1fr;}
}
</style>
</head>

<body>

<header>
    <div class="container">
        <h2><?= htmlspecialchars($produto['nome']) ?></h2>
    </div>
</header>

<main>
<div class="container">

<div class="product-grid">

    <div>
        <img src="/laboratorio/images/<?= htmlspecialchars($produto['imagem']) ?>">

        <p><?= nl2br(htmlspecialchars($produto['descricao'])) ?></p>
    </div>

    <aside>

        <h3>Pedido de Orçamento</h3>

        <?php if ($mensagem_sucesso): ?>
            <p style="color:green;"><?= $mensagem_sucesso ?></p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <ul style="color:red;">
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST">

            <input type="text" name="nome" placeholder="Nome" required value="<?= $old_nome ?>">
            <input type="email" name="email" placeholder="Email" required value="<?= $old_email ?>">
            <input type="text" name="telefone" placeholder="Telefone" value="<?= $old_telefone ?>">

            <textarea name="mensagem" placeholder="Mensagem"><?= $old_mensagem ?></textarea>

            <label>Data</label>
            <input type="date" id="data_marcacao" name="data_marcacao" min="<?= date('Y-m-d') ?>" required>

            <label>Hora</label>
            <select id="hora_marcacao" name="hora_marcacao" required>
                <option>Escolha uma data</option>
            </select>

            <button type="submit">Enviar</button>

        </form>

    </aside>

</div>
</div>
</main>

<script>
const dataInput = document.getElementById('data_marcacao');
const horaSelect = document.getElementById('hora_marcacao');

if (dataInput) {
    dataInput.addEventListener('change', function () {

        let data = this.value;

        horaSelect.innerHTML = "";

        let dia = new Date(data).getDay();

        if (dia === 0 || dia === 6) {
            horaSelect.innerHTML = "<option>Clínica encerrada ao fim de semana</option>";
            return;
        }

        horaSelect.innerHTML = "<option>A carregar...</option>";

        fetch("/LabInSmile/pages/get_horarios.php?data=" + data)
        .then(res => res.json())
        .then(horarios => {

            horaSelect.innerHTML = "";

            if (horarios.length === 0) {
                horaSelect.innerHTML = "<option>Sem horários disponíveis</option>";
                return;
            }

            horarios.forEach(item => {
                let opt = document.createElement("option");
                opt.value = item.hora;

                if (item.ocupado) {
                    opt.textContent = item.hora + " (Ocupado)";
                    opt.disabled = true;
                } else {
                    opt.textContent = item.hora + " (Disponível)";
                }

                horaSelect.appendChild(opt);
            });

        })
        .catch(err => {
            console.error(err);
            horaSelect.innerHTML = "<option>Erro ao carregar horários</option>";
        });

    });
}
</script>
</body>
</html>