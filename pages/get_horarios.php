<?php
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

$data = $_GET['data'] ?? '';

if (!$data) {
    echo json_encode([]);
    exit;
}

// gerar todos os horários
$horarios = [];

for ($h = 9; $h < 18; $h++) {
    $horarios[] = sprintf("%02d:00", $h);
    $horarios[] = sprintf("%02d:30", $h);
}
$horarios[] = "18:00";

// buscar ocupados
$stmt = $conn->prepare("SELECT hora_marcacao FROM pedidos WHERE data_marcacao = ?");
$stmt->bind_param("s", $data);
$stmt->execute();

$result = $stmt->get_result();

$ocupados = [];

while ($row = $result->fetch_assoc()) {
    $ocupados[] = substr($row['hora_marcacao'], 0, 5);
}

// montar resposta
$resposta = [];

foreach ($horarios as $hora) {
    $resposta[] = [
        "hora" => $hora,
        "ocupado" => in_array($hora, $ocupados)
    ];
}

echo json_encode($resposta);