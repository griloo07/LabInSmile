<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Acesso negado. Apenas administradores.");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// POST processing (both standard and AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $ajaxResponse = ['success' => false, 'message' => 'Erro desconhecido.'];

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
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';

        if ($id === (int)($_SESSION['user_id'])) {
            $mensagem = 'Não pode alterar o seu próprio role aqui.';
            if ($isAjax) {
                $ajaxResponse['message'] = $mensagem;
                header('Content-Type: application/json');
                echo json_encode($ajaxResponse);
                exit;
            }
        } else {
            $allowed = ['user', 'editor', 'admin'];
            if (!in_array($role, $allowed, true)) {
                $mensagem = 'Role inválido.';
                if ($isAjax) {
                    $ajaxResponse['message'] = $mensagem;
                    header('Content-Type: application/json');
                    echo json_encode($ajaxResponse);
                    exit;
                }
            } else {
                $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $role, $id);
                    if ($stmt->execute()) {
                        $mensagem = 'Role de utilizador atualizado.';
                        if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem];
                    } else {
                        $mensagem = 'Falha ao atualizar o role.';
                        if ($isAjax) $ajaxResponse['message'] = $mensagem;
                    }
                    $stmt->close();
                } else {
                    $mensagem = 'Erro de banco de dados.';
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ajaxResponse);
        exit;
    }

    $_SESSION['flash'] = $mensagem;
    header('Location: manage_users.php');
    exit;
}

// Fetch metrics
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

// Load users
$stmt = $conn->prepare('SELECT id, email, name, role FROM users ORDER BY id ASC');
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

$user_name = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Admin';
$name_parts = explode(' ', trim($user_name));
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
} else {
    $initials = strtoupper(substr($user_name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerir Utilizadores - Admin</title>
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
            width: 280px;
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

        /* DATA TABLE CARD */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h2 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table.users-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        table.users-table th {
            background: #f8fafc;
            padding: 14px 24px;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            letter-spacing: 0.5px;
        }

        table.users-table td {
            padding: 16px 24px;
            font-size: 0.9rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        table.users-table tr:last-child td {
            border-bottom: none;
        }

        table.users-table tr:hover td {
            background: #fafafb;
        }

        .user-profile-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-initials {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e2e8f0;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .user-initials.admin-user {
            background: var(--primary-light);
            color: var(--primary);
        }

        .user-name-text {
            font-weight: 700;
            color: var(--text-main);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: #fef3c7;
            color: #d97706;
        }

        .role-badge.editor {
            background: #e0f2fe;
            color: #0284c7;
        }

        .role-badge.user {
            background: #f1f5f9;
            color: #475569;
        }

        /* ACTIONS FORM */
        .role-select-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .role-select {
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface);
            font-size: 0.85rem;
            color: var(--text-main);
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .role-select:focus {
            border-color: var(--primary);
        }

        .btn-update-role {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(11, 110, 79, 0.1);
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-update-role:hover {
            background: var(--primary);
            color: #ffffff;
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
                <a href="manage_portfolio.php" class="sidebar-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Portfólio</span>
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="sidebar-link active">
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
                <h1>Painel de Utilizadores</h1>
                <p>Gerencie as contas e permissões de acesso de utilizadores do site</p>
            </div>
            
            <div class="topbar-actions">
                <div class="search-box-admin">
                    <svg class="search-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="search" id="admin-search" placeholder="Pesquisar por nome ou email...">
                </div>
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
                    <div class="number"><?= $stat_portfolio ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Utilizadores</h3>
                    <div class="number" id="metric-users-count"><?= $stat_users ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
        </div>

        <!-- USERS TABLE -->
        <div class="table-card">
            <div class="table-header">
                <h2>Lista de Utilizadores Registrados</h2>
            </div>
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Utilizador</th>
                            <th>Email</th>
                            <th>Nível de Acesso</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <?php foreach ($users as $u): ?>
                            <?php 
                            $u_name = $u['name'] ?: $u['email'];
                            $u_name_parts = explode(' ', trim($u_name));
                            $u_initials = '';
                            if (count($u_name_parts) >= 2) {
                                $u_initials = strtoupper(substr($u_name_parts[0], 0, 1) . substr($u_name_parts[count($u_name_parts)-1], 0, 1));
                            } else {
                                $u_initials = strtoupper(substr($u_name, 0, 2));
                            }
                            $is_current = ($u['id'] == $_SESSION['user_id']);
                            ?>
                            <tr class="user-row" id="user-row-<?= intval($u['id']) ?>" data-name="<?= htmlspecialchars($u['name'] ?: '', ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>">
                                <td>
                                    <div class="user-profile-row">
                                        <div class="user-initials <?= $u['role'] === 'admin' ? 'admin-user' : '' ?>"><?= htmlspecialchars($u_initials) ?></div>
                                        <span class="user-name-text"><?= htmlspecialchars($u['name'] ?: 'Sem Nome') ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="role-badge <?= htmlspecialchars($u['role']) ?>" id="role-badge-<?= intval($u['id']) ?>"><?= htmlspecialchars($u['role']) ?></span>
                                </td>
                                <td>
                                    <?php if ($is_current): ?>
                                        <span style="font-style: italic; color: var(--text-muted);">Sua Conta</span>
                                    <?php else: ?>
                                        <form method="POST" class="role-select-form" data-id="<?= intval($u['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="id" value="<?= intval($u['id']) ?>">
                                            <select name="role" class="role-select">
                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                                <option value="editor" <?= $u['role'] === 'editor' ? 'selected' : '' ?>>editor</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                            </select>
                                            <button type="submit" class="btn-update-role">Alterar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

    // Capture standard session message if exists
    <?php if (!empty($_SESSION['flash'])): ?>
        showToast(<?= json_encode($_SESSION['flash']) ?>);
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    // Handle AJAX role update
    document.getElementById('users-tbody').addEventListener('submit', function(e) {
        const form = e.target.closest('.role-select-form');
        if (!form) return;

        e.preventDefault();
        const fd = new FormData(form);
        const id = form.dataset.id;
        const newRole = form.querySelector('.role-select').value;

        fetch('manage_users.php', {
            method: 'POST',
            body: fd,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                showToast(json.message);
                // Update badge in UI
                const badge = document.getElementById(`role-badge-${id}`);
                if (badge) {
                    badge.className = `role-badge ${newRole}`;
                    badge.textContent = newRole;
                }
            } else {
                showToast(json.message || 'Erro ao alterar permissão.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de rede ao alterar role.', 'error');
        });
    });

    // Client-side search filtering
    const searchInput = document.getElementById('admin-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                const name = row.dataset.name ? row.dataset.name.toLowerCase() : '';
                const email = row.dataset.email ? row.dataset.email.toLowerCase() : '';
                if (name.indexOf(val) !== -1 || email.indexOf(val) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    </script>
</body>
</html>
