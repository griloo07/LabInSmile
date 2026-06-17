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
// Basic validation: must not be empty, no directory traversal, must have an allowed image extension
if ($filename === '' || strpos($filename, '..') !== false || preg_match('/[\/\\]/', $filename)) {
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
if (file_exists($path) && is_file($path)) {
    if (!is_writable($path)) {
        @chmod($path, 0644);
    }
    if (!@unlink($path)) {
        $err = error_get_last();
        $errMsg = $err['message'] ?? 'Erro desconhecido';
        // Log the error for debugging
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logFile = $logDir . '/delete_image_errors.log';
        $entry = date('Y-m-d H:i:s') . " - unlink failed for {$path} - {$errMsg}\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Falha ao eliminar ficheiro', 'error' => $errMsg]);
        exit;
    }
    $deleted = true;
}

/**
 * Helpers for portfolio image value handling (same semantics as manage_portfolio.php)
 */
function portfolio_images($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) return array_values(array_filter($decoded, 'is_string'));
    return [$value];
}

function portfolio_images_value(array $images) {
    $images = array_values(array_filter(array_map('trim', $images)));
    if (count($images) === 0) return '';
    if (count($images) === 1) return $images[0];
    return json_encode($images, JSON_UNESCAPED_SLASHES);
}

$affected = 0;
$stmt = $conn->prepare('SELECT id, imagem FROM portfolio WHERE imagem LIKE ?');
$like = '%' . $filename . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
$update = $conn->prepare('UPDATE portfolio SET imagem = ? WHERE id = ?');
while ($row = $res->fetch_assoc()) {
    $imgs = portfolio_images($row['imagem']);
    $newImgs = array_values(array_filter($imgs, function($v) use ($filename) { return trim($v) !== $filename; }));
    if (count($newImgs) !== count($imgs)) {
        $newVal = portfolio_images_value($newImgs);
        $update->bind_param('si', $newVal, $row['id']);
        if ($update->execute()) $affected++;
    }
}
$update->close();
$stmt->close();

echo json_encode(['success' => true, 'message' => ($deleted ? 'Ficheiro e referências eliminadas.' : 'Referências atualizadas.'), 'deleted' => $deleted, 'rows_updated' => $affected]);
exit;

?>
