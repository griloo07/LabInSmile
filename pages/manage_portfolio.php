<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Acesso negado. Apenas administradores.");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

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

function ensure_portfolio_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS portfolio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT NOT NULL,
        imagem TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_portfolio_table($conn);

// Metrics count queries
$stat_servicos = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM services");
if ($res) $stat_servicos = $res->fetch_assoc()['cnt'];

$stat_tags = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM tags");
if ($res) $stat_tags = $res->fetch_assoc()['cnt'];

$stat_portfolio = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM portfolio");
if ($res) $stat_portfolio = $res->fetch_assoc()['cnt'];

$stat_users = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM users");
if ($res) $stat_users = $res->fetch_assoc()['cnt'];

$mensagem = '';
$user_name = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Admin';
$name_parts = explode(' ', trim($user_name));
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
} else {
    $initials = strtoupper(substr($user_name, 0, 2));
}

// POST processing (both standard and AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $ajaxResponse = ['success' => false, 'message' => 'Ação inválida.'];

    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        $mensagem = 'Token CSRF inválido.';
        if ($isAjax) {
            $ajaxResponse['message'] = $mensagem;
            header('Content-Type: application/json');
            echo json_encode($ajaxResponse);
            exit;
        }
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $categoria = trim($_POST['categoria'] ?? '');
            $imagem = $_POST['imagem'] ?? '[]';
            $titulo = $categoria; 
            $descricao = ''; 

            if ($titulo) {
                $stmt = $conn->prepare('INSERT INTO portfolio (titulo, descricao, imagem) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $titulo, $descricao, $imagem);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    $mensagem = 'Exemplo adicionado com sucesso.';
                    if ($isAjax) {
                        $ajaxResponse = [
                            'success' => true,
                            'message' => $mensagem,
                            'item' => [
                                'id' => $newId,
                                'titulo' => $titulo,
                                'imagem' => $imagem
                            ]
                        ];
                    }
                } else {
                    $mensagem = 'Erro ao adicionar: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'Por favor, preencha a categoria.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $categoria = trim($_POST['categoria'] ?? '');
            $imagem = $_POST['imagem'] ?? '[]';
            $titulo = $categoria;
            $descricao = '';

            if ($id && $titulo) {
                $stmt = $conn->prepare('UPDATE portfolio SET titulo = ?, descricao = ?, imagem = ? WHERE id = ?');
                $stmt->bind_param('sssi', $titulo, $descricao, $imagem, $id);
                if ($stmt->execute()) {
                    $mensagem = 'Exemplo atualizado com sucesso.';
                    if ($isAjax) {
                        $ajaxResponse = [
                            'success' => true,
                            'message' => $mensagem,
                            'item' => [
                                'id' => $id,
                                'titulo' => $titulo,
                                'imagem' => $imagem
                            ]
                        ];
                    }
                } else {
                    $mensagem = 'Erro ao atualizar: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'Dados de atualização inválidos.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare('DELETE FROM portfolio WHERE id = ?');
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $mensagem = 'Exemplo removido com sucesso.';
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'id' => $id];
                } else {
                    $mensagem = 'Erro ao remover: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'ID inválido.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ajaxResponse);
        exit;
    }

    header('Location: manage_portfolio.php');
    exit;
}

$result = $conn->query('SELECT * FROM portfolio ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Portfólio - Admin</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 70px;
            --primary: #0b6e4f;
            --primary-dark: #074a35;
            --primary-light: #eefbf6;
            --neutral-bg: #f8fafc;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-card: 0 4px 18px rgba(15, 23, 42, 0.04);
            --shadow-hover: 0 10px 25px rgba(15, 23, 42, 0.08);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--neutral-bg);
            color: var(--text-main);
            margin: 0;
            display: flex;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: #0d1e17;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: var(--transition);
        }

        .sidebar-brand {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand img {
            height: 32px;
            border-radius: var(--radius-sm);
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 0;
            margin: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: rgba(255, 255, 255, 0.65);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .sidebar-link svg {
            width: 20px;
            height: 20px;
            opacity: 0.7;
            transition: var(--transition);
        }

        .sidebar-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar-link.active {
            color: #ffffff;
            background: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.2);
        }

        .sidebar-link.active svg {
            opacity: 1;
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(0, 0, 0, 0.1);
        }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            font-size: 0.85rem;
            color: #ffffff;
        }

        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* MAIN BODY */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 40px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 32px;
            transition: var(--transition);
        }

        /* TOPBAR */
        .topbar-admin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 4px;
        }

        .page-title p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.9rem;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-box-admin {
            position: relative;
            width: 260px;
        }

        .search-box-admin input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid var(--border-color);
            background: var(--surface);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .search-box-admin input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .search-icon-svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--text-muted);
        }

        .btn-add-primary {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
            transition: var(--transition);
        }

        .btn-add-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* METRICS */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .metric-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .metric-info h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin: 0 0 8px;
            font-weight: 700;
        }

        .metric-info .number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .metric-icon svg {
            width: 24px;
            height: 24px;
        }

        /* PORTFOLIO GRID */
        .portfolio-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .portfolio-item-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .portfolio-item-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .card-media {
            height: 180px;
            background: #f1f5f9;
            position: relative;
            overflow: hidden;
        }

        .card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .portfolio-item-card:hover .card-media img {
            transform: scale(1.04);
        }

        .card-no-image {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            gap: 8px;
        }

        .card-no-image svg {
            width: 32px;
            height: 32px;
            opacity: 0.5;
        }

        .images-badge {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            backdrop-filter: blur(4px);
        }

        .card-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex: 1;
        }

        .card-details h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            text-transform: capitalize;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
        }

        .btn-edit-action, .btn-delete-action {
            flex: 1;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-edit-action {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(11, 110, 79, 0.1);
        }

        .btn-edit-action:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-delete-action {
            background: #fff5f5;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.1);
        }

        .btn-delete-action:hover {
            background: #ef4444;
            color: #ffffff;
        }

        /* MODAL */
        .admin-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .admin-modal.open {
            display: flex;
        }

        .modal-container {
            background: var(--surface);
            border-radius: var(--radius-md);
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
            color: var(--text-main);
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-main);
        }

        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }

        .form-group input[type="text"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        /* CUSTOM UPLOAD */
        .dropzone-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .upload-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            background: var(--neutral-bg);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .upload-dropzone svg {
            width: 36px;
            height: 36px;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .upload-dropzone:hover svg, .upload-dropzone.dragover svg {
            color: var(--primary);
            transform: translateY(-2px);
        }

        .upload-dropzone span {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .upload-dropzone strong {
            color: var(--primary);
        }

        .gallery-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }

        .preview-card-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: #f1f5f9;
            border: 1px solid var(--border-color);
        }

        .preview-card-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .btn-remove-prev {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(239, 68, 68, 0.9);
            color: #ffffff;
            border: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .btn-remove-prev:hover {
            background: #ef4444;
            transform: scale(1.1);
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: var(--neutral-bg);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel {
            background: transparent;
            border: 1px solid var(--border-color);
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-cancel:hover {
            background: #f1f5f9;
        }

        .btn-save {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
            transition: var(--transition);
        }

        .btn-save:hover {
            background: var(--primary-dark);
        }

        /* TOAST */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 2000;
        }

        .toast-alert {
            background: var(--surface);
            border-left: 4px solid var(--primary);
            border-radius: var(--radius-sm);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: toastIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border-top: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .toast-alert.error {
            border-left-color: #ef4444;
        }

        @keyframes toastIn {
            from { transform: translateY(100px) scale(0.9); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .toast-content {
            flex: 1;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Empty state */
        .empty-portfolio-state {
            grid-column: 1 / -1;
            background: var(--surface);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .empty-portfolio-state svg {
            width: 48px;
            height: 48px;
            opacity: 0.3;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 24px;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .topbar-admin {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box-admin {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="/LabInSmile/images/logo_labinsmile.png" alt="Lab in Smile">
            <span class="sidebar-brand-text">Lab in Smile</span>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="admin.php" class="sidebar-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>Serviços</span>
                </a>
            </li>
            <li>
                <a href="manage_portfolio.php" class="sidebar-link active">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Portfólio</span>
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="sidebar-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>Utilizadores</span>
                </a>
            </li>
            <li style="margin-top: auto">
                <a href="/LabInSmile/pages/servicos.php" class="sidebar-link" style="color: rgba(255,255,255,0.5)">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Voltar ao Website</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="user-role">Administrador</span>
            </div>
        </div>
    </div>

    <!-- MAIN BODY -->
    <div class="main-content">
        <!-- TOPBAR -->
        <div class="topbar-admin">
            <div class="page-title">
                <h1>Painel do Portfólio</h1>
                <p>Gerencie as fotos e categorias de trabalhos expostos no portfólio</p>
            </div>
            
            <div class="topbar-actions">
                <div class="search-box-admin">
                    <svg class="search-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="search" id="admin-search" placeholder="Pesquisar por categoria...">
                </div>
                <button type="button" class="btn-add-primary" id="btn-open-create-modal">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Novo Exemplo</span>
                </button>
            </div>
        </div>

        <!-- METRICS -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Serviços Ativos</h3>
                    <div class="number"><?= $stat_servicos ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Tags Registradas</h3>
                    <div class="number"><?= $stat_tags ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Fotos Portfólio</h3>
                    <div class="number" id="metric-portfolio-count"><?= $stat_portfolio ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Utilizadores</h3>
                    <div class="number"><?= $stat_users ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
        </div>

        <!-- ITEMS GRID -->
        <div class="portfolio-dashboard-grid" id="portfolio-list">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $images = portfolio_images($row['imagem']); ?>
                    <div class="portfolio-item-card" id="portfolio-item-<?= intval($row['id']) ?>" data-id="<?= intval($row['id']) ?>" data-titulo="<?= htmlspecialchars($row['titulo'], ENT_QUOTES) ?>" data-imagem="<?= htmlspecialchars($row['imagem'], ENT_QUOTES) ?>">
                        <div class="card-media">
                            <?php if (!empty($images)): ?>
                                <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['titulo']) ?>" id="card-img-<?= intval($row['id']) ?>">
                                <span class="images-badge" id="card-badge-<?= intval($row['id']) ?>"><?= count($images) ?> Foto(s)</span>
                            <?php else: ?>
                                <div class="card-no-image" id="card-placeholder-<?= intval($row['id']) ?>">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>Sem imagem carregada</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="card-details">
                                <h3 id="card-title-<?= intval($row['id']) ?>"><?= htmlspecialchars($row['titulo']) ?></h3>
                            </div>
                            <div class="card-actions">
                                <button type="button" class="btn-edit-action" data-id="<?= intval($row['id']) ?>">
                                    <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    <span>Editar</span>
                                </button>
                                <form method="POST" class="delete-form" style="flex:1; display:flex;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                                    <button type="submit" class="btn-delete-action">
                                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        <span>Eliminar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-portfolio-state" id="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p>Ainda não existem trabalhos no portfólio. Clique em "Novo Exemplo" para adicionar.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL ADD/EDIT -->
    <div class="admin-modal" id="portfolio-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="modal-title">Novo Exemplo</h2>
                <button type="button" class="modal-close" id="btn-close-modal">&times;</button>
            </div>
            <form id="portfolio-form" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" id="form-action" value="create">
                    <input type="hidden" name="id" id="form-id" value="">
                    <input type="hidden" name="imagem" id="form-imagem" value="[]">

                    <div class="form-group">
                        <label for="form-categoria">Categoria / Nome do Trabalho</label>
                        <input type="text" name="categoria" id="form-categoria" placeholder="Ex: Prótese Dentária Esquelética" required>
                    </div>

                    <div class="form-group dropzone-container">
                        <label>Fotos do Trabalho</label>
                        <div class="upload-dropzone" id="upload-dropzone">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span>Arrasta ficheiros aqui ou <strong>procura no PC</strong></span>
                            <input type="file" id="image-input" accept="image/*" multiple style="display:none;">
                        </div>
                        <div class="gallery-previews" id="preview-gallery"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="btn-cancel-form">Cancelar</button>
                    <button type="submit" class="btn-save" id="btn-submit-form">Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toast-container"></div>

    <script>
    // Toast helper
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-alert ${type}`;
        toast.innerHTML = `
            <div class="toast-content">${message}</div>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px) scale(0.95)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // Modal Control
    const modal = document.getElementById('portfolio-modal');
    const modalTitle = document.getElementById('modal-title');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formCategoria = document.getElementById('form-categoria');
    const formImagem = document.getElementById('form-imagem');
    const previewGallery = document.getElementById('preview-gallery');
    const btnSubmit = document.getElementById('btn-submit-form');

    function openModal(mode = 'create', data = null) {
        modal.classList.add('open');
        if (mode === 'create') {
            modalTitle.textContent = 'Novo Exemplo';
            formAction.value = 'create';
            formId.value = '';
            formCategoria.value = '';
            formImagem.value = '[]';
            previewGallery.innerHTML = '';
            btnSubmit.textContent = 'Adicionar';
        } else {
            modalTitle.textContent = 'Editar Exemplo';
            formAction.value = 'update';
            formId.value = data.id;
            formCategoria.value = data.titulo;
            formImagem.value = data.imagem || '[]';
            btnSubmit.textContent = 'Gravar Alterações';
            renderPreview(parseImages(data.imagem));
        }
    }

    function closeModal() {
        modal.classList.remove('open');
    }

    document.getElementById('btn-open-create-modal').addEventListener('click', () => openModal('create'));
    document.getElementById('btn-close-modal').addEventListener('click', closeModal);
    document.getElementById('btn-cancel-form').addEventListener('click', closeModal);

    // Helpers images
    function parseImages(value) {
        if (!value) return [];
        try { 
            const parsed = JSON.parse(value); 
            return Array.isArray(parsed) ? parsed.filter(Boolean) : [value].filter(Boolean); 
        } catch (err) { 
            return [value].filter(Boolean); 
        }
    }

    function storeImages(images) {
        const clean = [...new Set(images.filter(Boolean))];
        formImagem.value = JSON.stringify(clean);
        return clean;
    }

    function renderPreview(images) {
        previewGallery.innerHTML = images.map(filename => `
            <div class="preview-card-item" data-filename="${filename.replace(/"/g, '&quot;')}">
                <img src="/LabInSmile/images/${filename}" alt="">
                <button type="button" class="btn-remove-prev" data-filename="${filename.replace(/"/g, '&quot;')}" aria-label="Remover imagem">&times;</button>
            </div>
        `).join('');
    }

    // Upload Handler
    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('image-input');

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files) {
            handleUpload(e.dataTransfer.files);
        }
    });

    fileInput.addEventListener('change', function() {
        if (this.files) {
            handleUpload(this.files);
        }
    });

    async function handleUpload(files) {
        const list = Array.from(files);
        if (list.length === 0) return;

        const currentImages = parseImages(formImagem.value);

        for (const file of list) {
            const fd = new FormData();
            fd.append('image', file);

            try {
                const res = await fetch('upload_image.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    currentImages.push(json.filename);
                    showToast('Imagem carregada com sucesso.');
                } else {
                    showToast(json.error || 'Erro ao carregar ficheiro.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Falha de rede ao carregar ficheiro.', 'error');
            }
        }

        const clean = storeImages(currentImages);
        renderPreview(clean);
    }

    // Image Deletion
    previewGallery.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-prev');
        if (!btn) return;

        const filename = btn.dataset.filename;
        if (!filename) return;

        if (!confirm('Eliminar esta imagem permanentemente?')) return;

        const token = document.querySelector('input[name="csrf_token"]').value;
        const fd = new FormData();
        fd.append('csrf_token', token);
        fd.append('filename', filename);

        fetch('delete_image.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const images = parseImages(formImagem.value);
                const idx = images.indexOf(filename);
                if (idx !== -1) images.splice(idx, 1);
                storeImages(images);
                renderPreview(images);
                showToast('Imagem removida com sucesso.');
            } else {
                showToast(json.message || 'Erro ao remover imagem.', 'error');
            }
        }).catch(err => {
            console.error(err);
            showToast('Erro de rede ao remover imagem.', 'error');
        });
    });

    // Edit Button Handlers
    document.getElementById('portfolio-list').addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-action');
        if (!btn) return;

        const card = btn.closest('.portfolio-item-card');
        if (!card) return;

        openModal('edit', {
            id: card.dataset.id,
            titulo: card.dataset.titulo,
            imagem: card.dataset.imagem
        });
    });

    // Form CRUD Submission
    document.getElementById('portfolio-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);

        fetch('manage_portfolio.php', { 
            method: 'POST', 
            body: fd, 
            headers: {'X-Requested-With': 'XMLHttpRequest'} 
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                closeModal();
                showToast(json.message);
                
                const action = fd.get('action');
                if (action === 'create') {
                    // Create card structure dynamically and append
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    // Update element in DOM
                    const id = fd.get('id');
                    const titleEl = document.getElementById(`card-title-${id}`);
                    if (titleEl) titleEl.textContent = fd.get('categoria');

                    const mediaContainer = document.querySelector(`#portfolio-item-${id} .card-media`);
                    const images = parseImages(formImagem.value);
                    
                    if (mediaContainer) {
                        if (images.length > 0) {
                            mediaContainer.innerHTML = `
                                <img src="/LabInSmile/images/${images[0]}" alt="${fd.get('categoria')}" id="card-img-${id}">
                                <span class="images-badge" id="card-badge-${id}">${images.length} Foto(s)</span>
                            `;
                        } else {
                            mediaContainer.innerHTML = `
                                <div class="card-no-image" id="card-placeholder-${id}">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>Sem imagem carregada</span>
                                </div>
                            `;
                        }
                    }

                    // Update data-attributes on the card
                    const card = document.getElementById(`portfolio-item-${id}`);
                    if (card) {
                        card.dataset.titulo = fd.get('categoria');
                        card.dataset.imagem = formImagem.value;
                    }
                }
            } else {
                showToast(json.message || 'Erro ao guardar.', 'error');
            }
        }).catch(err => {
            console.error(err);
            showToast('Erro de rede ao submeter formulário.', 'error');
        });
    });

    // Delete Submission
    document.getElementById('portfolio-list').addEventListener('submit', function(e) {
        const form = e.target.closest('.delete-form');
        if (!form) return;
        
        e.preventDefault();
        if (!confirm('Tens a certeza que queres eliminar este exemplo do portfólio?')) return;

        const fd = new FormData(form);

        fetch('manage_portfolio.php', { 
            method: 'POST', 
            body: fd, 
            headers: {'X-Requested-With': 'XMLHttpRequest'} 
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const id = json.id || fd.get('id');
                const card = document.getElementById(`portfolio-item-${id}`);
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    card.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        card.remove();
                        // Update metrics counts
                        const countMetric = document.getElementById('metric-portfolio-count');
                        if (countMetric) {
                            countMetric.textContent = Math.max(0, parseInt(countMetric.textContent, 10) - 1);
                        }
                        // Check if grid is empty
                        const grid = document.getElementById('portfolio-list');
                        if (grid.children.length === 0) {
                            grid.innerHTML = `
                                <div class="empty-portfolio-state" id="empty-state">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <p>Ainda não existem trabalhos no portfólio. Clique em "Novo Exemplo" para adicionar.</p>
                                </div>
                            `;
                        }
                    }, 300);
                }
                showToast(json.message);
            } else {
                showToast(json.message || 'Erro ao eliminar.', 'error');
            }
        }).catch(err => {
            console.error(err);
            showToast('Erro de rede ao eliminar.', 'error');
        });
    });

    // Real-time client search filter
    const searchInput = document.getElementById('admin-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.portfolio-item-card');
            
            cards.forEach(card => {
                const title = card.dataset.titulo ? card.dataset.titulo.toLowerCase() : '';
                if (title.indexOf(val) !== -1) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
    </script>
</body>
</html>
