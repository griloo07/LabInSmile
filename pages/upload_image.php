<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ficheiro não recebido']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de ficheiro não permitido']);
    exit;
}

$ext = '';
switch ($mime) {
    case 'image/jpeg': $ext = '.jpg'; break;
    case 'image/png': $ext = '.png'; break;
    case 'image/gif': $ext = '.gif'; break;
    case 'image/webp': $ext = '.webp'; break;
}

$uploadDir = __DIR__ . '/../images';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$basename = bin2hex(random_bytes(8));
$target = $uploadDir . '/' . $basename . $ext;

if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
    echo json_encode(['success' => true, 'filename' => $basename . $ext]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Falha ao gravar ficheiro']);
    exit;
}
