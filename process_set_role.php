<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido.';
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$role = isset($_POST['role']) ? trim($_POST['role']) : '';
$token = $_POST['csrf_token'] ?? '';

// CSRF
if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(400);
    echo 'Token CSRF inválido.';
    exit;
}

// Não permitir alterar o próprio role aqui
if ($id === (int)($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Não pode alterar o seu próprio role aqui.';
    header('Location: pages/manage_users.php');
    exit;
}

// Validar role
$allowed = ['user','editor','admin'];
if (!in_array($role, $allowed, true)) {
    $_SESSION['flash'] = 'Role inválido.';
    header('Location: pages/manage_users.php');
    exit;
}

$stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
if (!$stmt) {
    $_SESSION['flash'] = 'Erro no servidor.';
    header('Location: pages/manage_users.php');
    exit;
}
$stmt->bind_param('si', $role, $id);
if ($stmt->execute()) {
    $_SESSION['flash'] = 'Role atualizado.';
} else {
    $_SESSION['flash'] = 'Falha ao atualizar.';
}

header('Location: pages/manage_users.php');
exit;
