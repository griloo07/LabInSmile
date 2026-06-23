<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$filename = $_POST['filename'] ?? '';
$filename = basename((string)$filename);

if ($filename === '' || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome de ficheiro inválido']);
    exit;
}

if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Extensão não suportada']);
    exit;
}

$uploadDir = __DIR__ . '/../images';
$path = $uploadDir . '/' . $filename;

$deleted = false;
$deleteError = null;

if (file_exists($path) && is_file($path)) {
    if (!is_writable($path)) {
        @chmod($path, 0644);
    }
    if (!@unlink($path)) {
        $err = error_get_last();
        $deleteError = $err['message'] ?? 'Falha ao eliminar ficheiro';
    } else {
        $deleted = true;
    }
}

function service_images($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }
    return [$value];
}

function service_images_value(array $images) {
    $images = array_values(array_filter(array_map('trim', $images)));
    if (count($images) === 0) return '';
    if (count($images) === 1) return $images[0];
    return json_encode($images, JSON_UNESCAPED_SLASHES);
}

// Remove referências do filename de todos os serviços
$affected = 0;
$like = '%' . $filename . '%';

$stmt = $conn->prepare('SELECT id, imagem FROM services WHERE imagem LIKE ?');
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();

$update = $conn->prepare('UPDATE services SET imagem = ? WHERE id = ?');

while ($row = $res->fetch_assoc()) {
    $imgs = service_images($row['imagem'] ?? '');
    $newImgs = array_values(array_filter($imgs, function ($v) use ($filename) {
        return trim((string)$v) !== $filename;
    }));

    if (count($newImgs) !== count($imgs)) {
        $newVal = service_images_value($newImgs);
        $update->bind_param('si', $newVal, $row['id']);
        if ($update->execute()) {
            $affected++;
        }
    }
}

$update->close();
$stmt->close();

if ($deleteError) {
    // Se falhou a eliminar o ficheiro mas removemos referências, ainda assim devolvemos sucesso parcial.
    echo json_encode([
        'success' => $affected > 0,
        'message' => ($affected > 0 ? 'Referências eliminadas, mas falhou a eliminar o ficheiro.' : 'Falha ao eliminar ficheiro.'),
        'deleted' => $deleted,
        'rows_updated' => $affected,
        'error' => $deleteError
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => ($deleted ? 'Ficheiro e referências eliminadas.' : 'Referências atualizadas.'),
    'deleted' => $deleted,
    'rows_updated' => $affected
]);
exit;

