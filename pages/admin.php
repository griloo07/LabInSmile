<?php
session_start();

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Acesso negado.");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$mensagem = "";

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

function service_images_value(array $images) {
    $images = array_values(array_filter(array_map('trim', $images)));
    if (count($images) === 0) {
        return '';
    }
    if (count($images) === 1) {
        return $images[0];
    }
    return json_encode($images, JSON_UNESCAPED_SLASHES);
}

function ensure_service_tag_tables($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS service_tags (
            service_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (service_id, tag_id),
            CONSTRAINT fk_service_tags_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            CONSTRAINT fk_service_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function tag_slug($name) {
    $name = trim((string)$name);
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $ascii = strtolower($converted ?: $name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'tag-' . substr(sha1($name), 0, 8);
}

function get_all_tags($conn) {
    $tags = [];
    $result = $conn->query("SELECT id, nome, slug FROM tags ORDER BY nome ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
    }
    return $tags;
}

function get_service_tags_map($conn) {
    $map = [];
    $sql = "
        SELECT st.service_id, t.id, t.nome, t.slug
        FROM service_tags st
        INNER JOIN tags t ON t.id = st.tag_id
        ORDER BY t.nome ASC
    ";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $serviceId = (int)$row['service_id'];
            if (!isset($map[$serviceId])) {
                $map[$serviceId] = [];
            }
            $map[$serviceId][] = ['id' => (int)$row['id'], 'nome' => $row['nome'], 'slug' => $row['slug']];
        }
    }
    return $map;
}

function selected_tag_ids_from_post() {
    $ids = $_POST['tags'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    return array_values(array_unique(array_filter(array_map('intval', $ids))));
}

function sync_service_tags($conn, $serviceId, array $tagIds) {
    $stmt = $conn->prepare("DELETE FROM service_tags WHERE service_id = ?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $stmt->close();

    if (empty($tagIds)) {
        return;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO service_tags (service_id, tag_id) VALUES (?, ?)");
    foreach ($tagIds as $tagId) {
        $stmt->bind_param("ii", $serviceId, $tagId);
        $stmt->execute();
    }
    $stmt->close();
}

function tags_payload_for_service($conn, $serviceId) {
    $tags = [];
    $stmt = $conn->prepare("
        SELECT t.id, t.nome, t.slug
        FROM service_tags st
        INNER JOIN tags t ON t.id = st.tag_id
        WHERE st.service_id = ?
        ORDER BY t.nome ASC
    ");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tags[] = ['id' => (int)$row['id'], 'nome' => $row['nome'], 'slug' => $row['slug']];
    }
    $stmt->close();
    return $tags;
}

ensure_service_tag_tables($conn);

// Tratar ações via POST: create, update, delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {

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
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $imagem = service_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);
            $tagIds = selected_tag_ids_from_post();

            if ($nome && $descricao) {
                $stmt = $conn->prepare("INSERT INTO services (nome, descricao, imagem) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $nome, $descricao, $imagem);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    sync_service_tags($conn, $newId, $tagIds);
                    $serviceTags = tags_payload_for_service($conn, $newId);
                    $mensagem = "Serviço adicionado com sucesso!";
                    if ($isAjax) {
                        $ajaxResponse = [
                            'success' => true,
                            'message' => $mensagem,
                            'action' => 'create',
                            'item' => ['id' => $newId, 'nome' => $nome, 'descricao' => $descricao, 'imagem' => $imagem, 'tags' => $serviceTags]
                        ];
                    }
                } else {
                    $mensagem = "Erro ao adicionar: " . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = "Preenche todos os campos.";
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $imagem = service_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);
            $tagIds = selected_tag_ids_from_post();

            if ($id && $nome && $descricao) {
                $stmt = $conn->prepare("UPDATE services SET nome = ?, descricao = ?, imagem = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $descricao, $imagem, $id);
                if ($stmt->execute()) {
                    sync_service_tags($conn, $id, $tagIds);
                    $serviceTags = tags_payload_for_service($conn, $id);
                    $mensagem = "Serviço atualizado com sucesso!";
                    if ($isAjax) {
                        $ajaxResponse = [
                            'success' => true,
                            'message' => $mensagem,
                            'action' => 'update',
                            'item' => ['id' => $id, 'nome' => $nome, 'descricao' => $descricao, 'imagem' => $imagem, 'tags' => $serviceTags]
                        ];
                    }
                } else {
                    $mensagem = "Erro ao atualizar: " . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = "Dados inválidos para atualização.";
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $mensagem = "Serviço eliminado.";
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'action' => 'delete', 'id' => $id];
                } else {
                    $mensagem = "Erro ao eliminar: " . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = "ID inválido para eliminação.";
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }
        } elseif ($action === 'create_tag') {
            $tagName = trim($_POST['tag_nome'] ?? '');
            if ($tagName !== '') {
                $slug = tag_slug($tagName);
                $stmt = $conn->prepare("INSERT INTO tags (nome, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE nome = VALUES(nome)");
                $stmt->bind_param("ss", $tagName, $slug);
                if ($stmt->execute()) {
                    $tagId = $stmt->insert_id;
                    if (!$tagId) {
                        $stmtFind = $conn->prepare("SELECT id FROM tags WHERE slug = ? LIMIT 1");
                        $stmtFind->bind_param("s", $slug);
                        $stmtFind->execute();
                        $resFind = $stmtFind->get_result();
                        $found = $resFind->fetch_assoc();
                        $tagId = (int)($found['id'] ?? 0);
                        $stmtFind->close();
                    }
                    $mensagem = "Tag guardada com sucesso.";
                    if ($isAjax) {
                        $ajaxResponse = [
                            'success' => true,
                            'message' => $mensagem,
                            'action' => 'create_tag',
                            'tag' => ['id' => $tagId, 'nome' => $tagName, 'slug' => $slug]
                        ];
                    }
                } else {
                    $mensagem = "Erro ao guardar tag: " . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = "Escreve o nome da tag.";
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }
        } elseif ($action === 'delete_tag') {
            $tagId = intval($_POST['tag_id'] ?? 0);
            if ($tagId) {
                $stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
                $stmt->bind_param("i", $tagId);
                if ($stmt->execute()) {
                    $mensagem = "Tag removida.";
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'action' => 'delete_tag', 'tag_id' => $tagId];
                } else {
                    $mensagem = "Erro ao remover tag: " . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = "Tag invalida.";
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ajaxResponse ?? ['success' => false, 'message' => $mensagem ?? '']);
        exit;
    }

    // Após ação, evitar re-submissão
    header("Location: admin.php");
    exit;
}

// Se for pedido de edição, buscar serviço
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM services WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_product = $res->fetch_assoc();
        $stmt->close();
    }
}

$all_tags = get_all_tags($conn);
$service_tags_map = get_service_tags_map($conn);
$edit_tag_ids = $edit_product ? array_column($service_tags_map[(int)$edit_product['id']] ?? [], 'id') : [];

// Estatísticas para o Dashboard
$stat_servicos = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM services");
if ($res) { $row = $res->fetch_assoc(); $stat_servicos = (int)$row['total']; }

$stat_tags = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM tags");
if ($res) { $row = $res->fetch_assoc(); $stat_tags = (int)$row['total']; }

$stat_portfolio = 0;
$res = $conn->query("SHOW TABLES LIKE 'portfolio'");
if ($res && $res->num_rows > 0) {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM portfolio");
    if ($res2) { $row = $res2->fetch_assoc(); $stat_portfolio = (int)$row['total']; }
}

$stat_users = 0;
$res = $conn->query("SHOW TABLES LIKE 'users'");
if ($res && $res->num_rows > 0) {
    $res2 = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($res2) { $row = $res2->fetch_assoc(); $stat_users = (int)$row['total']; }
}

// Obter as iniciais do utilizador logado
$user_name = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Admin';
$name_parts = explode(' ', trim($user_name));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        :root {
            --primary: #0b6e4f;
            --primary-hover: #0a5a41;
            --primary-light: #ecfdf5;
            --primary-50: #f0fdf4;
            --primary-200: #bbf7d0;
            --primary-700: #047857;
            --sidebar-bg: linear-gradient(180deg, #093c2c 0%, #05261b 100%);
            --sidebar-width: 260px;
            --surface: #ffffff;
            --bg: #f3f4f6;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --danger-light: #fee2e2;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: #ffffff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
            box-shadow: 4px 0 24px rgba(5, 38, 27, 0.15);
        }

        .sidebar-brand {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand img {
            height: 36px;
            width: auto;
            border-radius: var(--radius-sm);
            background: #fff;
            padding: 2px;
        }

        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 14px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .sidebar-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.06);
        }

        .sidebar-link.active {
            color: #ffffff;
            background: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.25);
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            border: 2px solid rgba(255,255,255,0.2);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }

        /* MAIN CONTENT CONTAINER */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 32px;
            min-height: 100vh;
            transition: var(--transition);
        }

        /* TOP BAR */
        .topbar-admin {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 20px;
        }

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .page-title p {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-box-admin {
            position: relative;
            width: 320px;
        }

        .search-box-admin input {
            width: 100%;
            padding: 10px 16px 10px 42px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--surface);
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .search-box-admin input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 110, 79, 0.1);
        }

        .search-icon-svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            width: 18px;
            height: 18px;
        }

        .btn-add-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .btn-add-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.2);
        }

        /* METRICS GRID */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .metric-info h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .metric-info .number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-main);
            margin-top: 4px;
        }

        .metric-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .metric-icon svg {
            width: 22px;
            height: 22px;
        }

        /* LAYOUT COLUMNS */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 28px;
            align-items: start;
        }

        /* CARD STYLE COMPONENT */
        .dashboard-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .dashboard-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dashboard-card-header h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .dashboard-card-body {
            padding: 24px;
        }

        /* TAG MANAGER CARD */
        .tag-manager-sidebar {
            position: sticky;
            top: 24px;
        }

        .tag-input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .tag-input-group input {
            flex-grow: 1;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: var(--transition);
        }

        .tag-input-group input:focus {
            border-color: var(--primary);
        }

        .tag-input-group button {
            padding: 10px 16px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-200);
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .tag-input-group button:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .tag-list-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 280px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .tag-chip-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
            transition: var(--transition);
        }

        .tag-chip-item:hover {
            background: var(--primary-light);
            border-color: var(--primary-200);
            color: var(--primary);
        }

        .tag-chip-item button {
            background: transparent;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 2px;
            transition: var(--transition);
        }

        .tag-chip-item button:hover {
            color: var(--danger);
        }

        /* SERVICES GRID */
        .services-list-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .service-row-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            padding: 20px;
            display: flex;
            gap: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
        }

        .service-row-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-200);
        }

        .service-row-card.fade-out {
            opacity: 0;
            transform: scale(0.95);
        }

        .service-thumbnail {
            width: 140px;
            height: 105px;
            flex-shrink: 0;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--bg);
            border: 1px solid var(--border);
            position: relative;
        }

        .service-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            background: var(--bg);
        }

        .images-count-badge {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: rgba(0, 0, 0, 0.7);
            color: #ffffff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            backdrop-filter: blur(4px);
        }

        .service-details-row {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .service-card-header {
            margin-bottom: 8px;
        }

        .service-card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .service-card-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .service-row-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .badge-chip {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 9999px;
            background: var(--primary-light);
            color: var(--primary-700);
            border: 1px solid var(--primary-200);
        }

        .service-actions-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
            align-items: flex-end;
            min-width: 100px;
        }

        .btn-action-edit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-200);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            width: 100%;
            justify-content: center;
        }

        .btn-action-edit:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .btn-action-delete {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            justify-content: center;
        }

        .btn-action-delete:hover {
            background: var(--danger);
            color: #ffffff;
            border-color: var(--danger);
        }

        .no-services-found {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-size: 0.95rem;
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
        }

        /* PREMIUM DYNAMIC MODAL */
        .admin-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: 20px 10px;
            opacity: 0;
            transition: opacity 0.25s ease;
            overflow-y: auto;
        }

        .admin-modal.is-open {
            display: flex;
            opacity: 1;
        }

        .modal-container {
            background: var(--surface);
            width: 100%;
            max-width: 650px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            transform: scale(0.95);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin: 40px auto;
            display: flex;
            flex-direction: column;
        }

        .admin-modal.is-open .modal-container {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.5rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close-btn:hover {
            color: var(--text-main);
        }

        .modal-body {
            padding: 24px;
            overflow-y: visible;
            flex-grow: 1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* MODAL TAG OPTIONS CHIPS */
        .modal-tags-select-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
        }

        .tag-option-label {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .tag-option-label input {
            display: none;
        }

        .tag-option-label:has(input:checked) {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary-700);
            box-shadow: var(--shadow-sm);
        }

        /* IMAGE DRAG & DROP ZONE (COMPACT ROW) */
        .dropzone-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 12px 18px;
            background: var(--bg);
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropzone-area:hover, .dropzone-area.dragover {
            border-color: var(--primary);
            background: var(--primary-50);
        }

        .dropzone-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            flex-wrap: wrap;
        }

        .dropzone-content svg {
            width: 24px;
            height: 24px;
            color: var(--text-light);
            transition: var(--transition);
            margin: 0;
            flex-shrink: 0;
        }

        .dropzone-area:hover svg, .dropzone-area.dragover svg {
            color: var(--primary);
            transform: translateY(-1px);
        }

        .dropzone-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0;
            text-align: left;
        }

        .dropzone-text span {
            color: var(--primary);
            font-weight: 700;
            text-decoration: underline;
        }

        .dropzone-hint {
            font-size: 0.72rem;
            color: var(--text-light);
            margin: 0;
            text-align: left;
        }

        /* IMAGE PREVIEW GALLERY IN FORM */
        .modal-gallery-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .gallery-preview-item {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border);
            background: var(--bg);
            box-shadow: var(--shadow-sm);
            animation: scaleIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .gallery-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-preview-item .remove-image {
            position: absolute;
            inset: 0;
            background: rgba(239, 68, 68, 0.8);
            color: #ffffff;
            border: none;
            opacity: 0;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
        }

        .gallery-preview-item:hover .remove-image {
            opacity: 1;
        }

        .gallery-preview-spinner {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
        }

        .spinner-ring {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal-cancel {
            padding: 10px 18px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-modal-cancel:hover {
            background: rgba(0,0,0,0.04);
            color: var(--text-main);
        }

        .btn-modal-save {
            padding: 10px 18px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .btn-modal-save:hover {
            background: var(--primary-hover);
        }

        /* TOAST SYSTEM */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 360px;
            width: 100%;
        }

        .admin-toast {
            background: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            transition: var(--transition);
        }

        .admin-toast.error {
            border-left: 4px solid var(--danger);
        }

        .admin-toast.success {
            border-left: 4px solid var(--primary);
        }

        .toast-icon-svg {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            margin-top: 1px;
        }

        .admin-toast.success .toast-icon-svg {
            color: var(--primary);
        }

        .admin-toast.error .toast-icon-svg {
            color: var(--danger);
        }

        .toast-content {
            flex-grow: 1;
        }

        .toast-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .toast-message {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary);
            width: 100%;
            transform-origin: left;
            animation: progressShrink 4.2s linear forwards;
        }

        .admin-toast.error .toast-progress {
            background: var(--danger);
        }

        /* ANIMATIONS */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @keyframes slideInLeft {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes progressShrink {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        /* RESPONSIVE LAYOUT */
        @media (max-width: 992px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }

            .tag-manager-sidebar {
                position: static;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 850px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.is-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            /* Responsive trigger button for sidebar */
            .sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                cursor: pointer;
                color: var(--text-main);
            }
        }

        @media (max-width: 576px) {
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

            .service-row-card {
                flex-direction: column;
            }

            .service-thumbnail {
                width: 100%;
                height: 180px;
            }

            .service-actions-row {
                flex-direction: row;
                width: 100%;
            }

            .btn-action-edit, .btn-action-delete {
                flex: 1;
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
                <a href="admin.php" class="sidebar-link active">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>Serviços</span>
                </a>
            </li>
            <li>
                <a href="manage_portfolio.php" class="sidebar-link">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Portfólio</span>
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="sidebar-link">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>Utilizadores</span>
                </a>
            </li>
            <li style="margin-top: auto">
                <a href="/LabInSmile/pages/servicos.php" class="sidebar-link" style="color: rgba(255,255,255,0.5)">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
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
                <h1>Painel de Serviços</h1>
                <p>Crie, edite e organize os serviços exibidos no website</p>
            </div>
            
            <div class="topbar-actions">
                <div class="search-box-admin">
                    <svg class="search-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="search" id="admin-search" placeholder="Filtrar por título, tags...">
                </div>
                <button type="button" class="btn-add-primary" id="btn-open-create-modal">
                    <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Novo Serviço</span>
                </button>
            </div>
        </div>

        <!-- METRICS -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Serviços Ativos</h3>
                    <div class="number" id="metric-services-count"><?= $stat_servicos ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Tags / Categorias</h3>
                    <div class="number" id="metric-tags-count"><?= $stat_tags ?></div>
                </div>
                <div class="metric-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-info">
                    <h3>Portfolio</h3>
                    <div class="number"><?= $stat_portfolio ?></div>
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

        <!-- LAYOUT -->
        <div class="dashboard-layout">
            
            <!-- SERVICES SECTION -->
            <div class="services-list-container">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Serviços Registados</h2>
                    </div>
                    <div class="dashboard-card-body" id="products-list">
                        <?php
                        $result = $conn->query("SELECT * FROM services ORDER BY id DESC");
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $row_tags = $service_tags_map[(int)$row['id']] ?? [];
                                $row_images = service_images($row['imagem']);
                                $first_image = !empty($row_images) ? $row_images[0] : '';
                                $tag_ids = implode(',', array_column($row_tags, 'id'));
                        ?>
                            <div class="service-row-card" id="product-<?= intval($row['id']) ?>"
                                 data-id="<?= intval($row['id']) ?>"
                                 data-nome="<?= htmlspecialchars($row['nome'], ENT_QUOTES) ?>"
                                 data-descricao="<?= htmlspecialchars($row['descricao'], ENT_QUOTES) ?>"
                                 data-imagem="<?= htmlspecialchars($row['imagem'], ENT_QUOTES) ?>"
                                 data-tags="<?= htmlspecialchars($tag_ids, ENT_QUOTES) ?>">
                                
                                <div class="service-thumbnail">
                                    <?php if ($first_image): ?>
                                        <img src="/LabInSmile/images/<?= htmlspecialchars($first_image) ?>" alt="<?= htmlspecialchars($row['nome']) ?>" id="img-thumb-<?= intval($row['id']) ?>">
                                    <?php else: ?>
                                        <div class="no-image-placeholder" id="img-thumb-<?= intval($row['id']) ?>">
                                            <svg style="width:28px;height:28px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (count($row_images) > 1): ?>
                                        <span class="images-count-badge" id="img-count-badge-<?= intval($row['id']) ?>">+<?= count($row_images) ?> imagens</span>
                                    <?php endif; ?>
                                </div>

                                <div class="service-details-row">
                                    <div class="service-card-header">
                                        <h3 id="txt-title-<?= intval($row['id']) ?>"><?= htmlspecialchars($row['nome']) ?></h3>
                                    </div>
                                    <p class="service-card-desc" id="txt-desc-<?= intval($row['id']) ?>"><?= nl2br(htmlspecialchars($row['descricao'])) ?></p>
                                    <div class="service-row-badges" id="badges-container-<?= intval($row['id']) ?>">
                                        <?php foreach ($row_tags as $tag): ?>
                                            <span class="badge-chip" data-tag-id="<?= intval($tag['id']) ?>"><?= htmlspecialchars($tag['nome']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="service-actions-row">
                                    <button type="button" class="btn-action-edit btn-edit" data-id="<?= intval($row['id']) ?>">
                                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        <span>Editar</span>
                                    </button>
                                    
                                    <form method="POST" class="delete-form" data-id="<?= intval($row['id']) ?>" style="width:100%">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                                        <button type="submit" class="btn-action-delete">
                                            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            <span>Eliminar</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <div class="no-services-found" id="no-services-placeholder">Nenhum serviço registado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAG MANAGER SIDEBAR -->
            <div class="tag-manager-sidebar">
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2>Gestão de Tags</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <form id="tag-form" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="create_tag">
                            <div class="tag-input-group">
                                <input type="text" name="tag_nome" id="tag-nome" placeholder="Nova tag (ex: Ortodontia)" required>
                                <button type="submit">Criar</button>
                            </div>
                        </form>

                        <div id="tag-list" class="tag-list-chips">
                            <?php if (!empty($all_tags)): ?>
                                <?php foreach ($all_tags as $tag): ?>
                                    <span class="tag-chip-item" data-tag-id="<?= intval($tag['id']) ?>" data-tag-nome="<?= htmlspecialchars($tag['nome'], ENT_QUOTES) ?>">
                                        <span><?= htmlspecialchars($tag['nome']) ?></span>
                                        <button type="button" class="btn-delete-tag" title="Remover tag">&times;</button>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="tag-empty" style="color:var(--text-light);font-size:0.85rem">Ainda não existem tags.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- SINGLE UNIFIED DIALOG/MODAL FORM -->
    <div class="admin-modal" id="admin-modal" role="dialog" aria-modal="true">
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="modal-title">Novo Serviço</h3>
                <button type="button" class="modal-close-btn" id="btn-close-modal">&times;</button>
            </div>
            
            <form id="product-form" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" id="form-action" value="create">
                    <input type="hidden" name="id" id="form-id" value="">
                    <input type="hidden" name="imagem" id="form-imagem" value="">

                    <!-- Title field -->
                    <div class="form-group">
                        <label for="form-nome">Título do Serviço</label>
                        <input type="text" name="nome" id="form-nome" class="form-control" placeholder="Introduza o nome do serviço" required>
                    </div>

                    <!-- Description field -->
                    <div class="form-group">
                        <label for="form-descricao">Descrição Detalhada</label>
                        <textarea name="descricao" id="form-descricao" class="form-control" placeholder="Escreva sobre o serviço..." required></textarea>
                    </div>

                    <!-- Tags list options -->
                    <div class="form-group">
                        <label>Keywords / Tags Relacionadas</label>
                        <div class="modal-tags-select-grid" id="form-tags-select">
                            <?php if (!empty($all_tags)): ?>
                                <?php foreach ($all_tags as $tag): ?>
                                    <label class="tag-option-label" id="tag-option-label-<?= intval($tag['id']) ?>">
                                        <input type="checkbox" name="tags[]" value="<?= intval($tag['id']) ?>">
                                        <span><?= htmlspecialchars($tag['nome']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="tag-empty" style="color:var(--text-light);font-size:0.85rem">Crie tags no painel lateral antes.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div class="form-group">
                        <label>Galeria de Imagens</label>
                        <div class="dropzone-area" id="dropzone">
                            <div class="dropzone-content">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <p class="dropzone-text">Arraste as imagens aqui ou <span>escolha ficheiros</span></p>
                                <p class="dropzone-hint">Tipos permitidos: JPG, PNG, WEBP, GIF. Múltiplos suportados.</p>
                            </div>
                            <input type="file" id="image-input" accept="image/*" multiple style="display: none;">
                        </div>

                        <!-- Gallery lists inside the modal -->
                        <div class="modal-gallery-preview" id="modal-gallery-preview"></div>
                        <div id="upload-status" style="margin-top:8px; font-size:12px; color:var(--text-muted);"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancel-modal">Cancelar</button>
                    <button type="submit" class="btn-modal-save" id="btn-submit-form">Guardar Serviço</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toast-container"></div>

    <script>
    // App State and Selectors
    const modal = document.getElementById('admin-modal');
    const productForm = document.getElementById('product-form');
    const modalTitle = document.getElementById('modal-title');
    const formAction = document.getElementById('form-action');
    const formId = document.getElementById('form-id');
    const formNome = document.getElementById('form-nome');
    const formDescricao = document.getElementById('form-descricao');
    const formImagem = document.getElementById('form-imagem');
    const formTagsSelect = document.getElementById('form-tags-select');
    const modalGalleryPreview = document.getElementById('modal-gallery-preview');
    const uploadStatus = document.getElementById('upload-status');
    const imageInput = document.getElementById('image-input');
    const dropzone = document.getElementById('dropzone');
    const productsList = document.getElementById('products-list');
    const adminSearch = document.getElementById('admin-search');
    const tagForm = document.getElementById('tag-form');
    const tagList = document.getElementById('tag-list');
    const sidebar = document.getElementById('sidebar');

    let currentEditCard = null;

    // Toast Notification System
    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `admin-toast ${type}`;
        
        let iconSvg = '';
        if (type === 'success') {
            iconSvg = `<svg class="toast-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
        } else {
            iconSvg = `<svg class="toast-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
        }

        toast.innerHTML = `
            ${iconSvg}
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <div class="toast-progress"></div>
        `;

        container.appendChild(toast);

        // Auto remove toast
        const timeout = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4200);

        toast.addEventListener('click', () => {
            clearTimeout(timeout);
            toast.remove();
        });
    }

    // Helper functions for image strings
    function parseImages(value) {
        if (!value) return [];
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed.filter(Boolean) : [value];
        } catch (err) {
            return [value];
        }
    }

    function storeImages(images) {
        const clean = [...new Set(images.filter(Boolean))];
        formImagem.value = JSON.stringify(clean);
        return clean;
    }

    // Dynamic tags template rendering inside sidebar
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function renderModalGallery(images) {
        modalGalleryPreview.innerHTML = images.map(filename => `
            <div class="gallery-preview-item" data-filename="${escapeHtml(filename)}">
                <img src="/LabInSmile/images/${filename}" alt="">
                <button type="button" class="remove-image" data-filename="${escapeHtml(filename)}" title="Remover imagem">&times;</button>
            </div>
        `).join('');
    }

    // Modal Actions
    function openModal(mode = 'create', serviceId = null) {
        if (mode === 'create') {
            modalTitle.textContent = 'Novo Serviço';
            formAction.value = 'create';
            formId.value = '';
            formNome.value = '';
            formDescricao.value = '';
            formImagem.value = '';
            
            // Uncheck tags
            formTagsSelect.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = false);
            modalGalleryPreview.innerHTML = '';
            uploadStatus.textContent = '';
            currentEditCard = null;
        } else {
            modalTitle.textContent = 'Editar Serviço';
            formAction.value = 'update';
        }
        
        modal.classList.add('is-open');
        modal.scrollTop = 0;
    }

    function closeModal() {
        modal.classList.remove('is-open');
    }

    document.getElementById('btn-open-create-modal').addEventListener('click', () => openModal('create'));
    document.getElementById('btn-close-modal').addEventListener('click', closeModal);
    document.getElementById('btn-cancel-modal').addEventListener('click', closeModal);
    
    // Close modal clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Tag checkboxes sync state
    function setSelectedTags(ids) {
        const selected = new Set((ids || []).map(String));
        formTagsSelect.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.checked = selected.has(String(input.value));
        });
    }

    // Open Modal dynamically from card click
    productsList.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit');
        if (!btn) return;
        
        const card = btn.closest('.service-row-card');
        if (!card) return;

        currentEditCard = card;
        formId.value = card.dataset.id || '';
        formNome.value = card.dataset.nome || '';
        formDescricao.value = card.dataset.descricao || '';
        formImagem.value = card.dataset.imagem || '';

        const tags = (card.dataset.tags || '').split(',').filter(Boolean);
        setSelectedTags(tags);
        renderModalGallery(parseImages(formImagem.value));
        
        openModal('edit');
    });

    // UPLOAD LOGIC
    async function uploadFile(file) {
        const fd = new FormData();
        fd.append('image', file);
        const res = await fetch('upload_image.php', { method: 'POST', body: fd });
        return res.json();
    }

    async function handleFileSelection(files) {
        const selected = Array.from(files || []);
        if (selected.length === 0) return;

        uploadStatus.textContent = 'A enviar ficheiros...';
        const images = parseImages(formImagem.value);

        // Append loaders for the files
        selected.forEach(() => {
            const spinner = document.createElement('div');
            spinner.className = 'gallery-preview-spinner';
            spinner.innerHTML = '<div class="spinner-ring"></div>';
            modalGalleryPreview.appendChild(spinner);
        });

        try {
            for (const file of selected) {
                const json = await uploadFile(file);
                // Remove one spinner
                const loader = modalGalleryPreview.querySelector('.gallery-preview-spinner');
                if (loader) loader.remove();

                if (!json.success) {
                    throw new Error(json.error || 'Erro no envio');
                }
                images.push(json.filename);
            }

            const clean = storeImages(images);
            renderModalGallery(clean);
            uploadStatus.textContent = `${selected.length} imagem(ns) adicionada(s).`;
            showToast('Imagens Carregadas', 'Os ficheiros foram enviados para o servidor.', 'success');
        } catch (err) {
            // Remove remaining spinners
            modalGalleryPreview.querySelectorAll('.gallery-preview-spinner').forEach(el => el.remove());
            uploadStatus.textContent = `Erro: ${err.message}`;
            showToast('Erro de Carregamento', err.message, 'error');
        }
    }

    // Dropzone Events
    dropzone.addEventListener('click', () => imageInput.click());
    imageInput.addEventListener('change', function() {
        handleFileSelection(this.files);
    });

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files) {
            handleFileSelection(e.dataTransfer.files);
        }
    });

    // Remove Images inside modal (instant fetch DELETE + JSON update)
    modalGalleryPreview.addEventListener('click', function(e) {
        const btn = e.target.closest('.remove-image');
        if (!btn) return;

        const filename = btn.dataset.filename;
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('filename', filename);

        fetch('delete_service_image.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const item = btn.closest('.gallery-preview-item');
                    if (item) item.remove();

                    const current = parseImages(formImagem.value);
                    const filtered = current.filter(f => f !== filename);
                    storeImages(filtered);

                    // Sync the thumbnail if currently editing a card
                    if (currentEditCard) {
                        currentEditCard.dataset.imagem = formImagem.value;
                        syncCardImage(currentEditCard.dataset.id, filtered);
                    }

                    showToast('Imagem Eliminada', 'Ficheiro removido com sucesso.', 'success');
                } else {
                    showToast('Erro', json.message || 'Falha ao remover ficheiro.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Erro de Rede', 'Não foi possível ligar ao servidor.', 'error');
            });
    });

    function syncCardImage(serviceId, imageList) {
        const card = document.getElementById(`product-${serviceId}`);
        if (!card) return;

        const thumb = document.getElementById(`img-thumb-${serviceId}`);
        const badge = document.getElementById(`img-count-badge-${serviceId}`);

        if (imageList.length > 0) {
            const first = imageList[0];
            if (thumb.tagName === 'IMG') {
                thumb.src = `/LabInSmile/images/${first}`;
            } else {
                // Reconstruct thumbnail wrapper as img
                const newImg = document.createElement('img');
                newImg.src = `/LabInSmile/images/${first}`;
                newImg.id = `img-thumb-${serviceId}`;
                thumb.replaceWith(newImg);
            }
        } else {
            // Reconstruct placeholder
            const placeholder = document.createElement('div');
            placeholder.className = 'no-image-placeholder';
            placeholder.id = `img-thumb-${serviceId}`;
            placeholder.innerHTML = `<svg style="width:28px;height:28px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
            thumb.replaceWith(placeholder);
        }

        if (badge) badge.remove();
        if (imageList.length > 1) {
            const wrapper = card.querySelector('.service-thumbnail');
            const newBadge = document.createElement('span');
            newBadge.className = 'images-count-badge';
            newBadge.id = `img-count-badge-${serviceId}`;
            newBadge.textContent = `+${imageList.length} imagens`;
            wrapper.appendChild(newBadge);
        }
    }

    // Dynamic cards template rendering
    function renderProductCard(item) {
        const imgs = parseImages(item.imagem || '');
        const hasImgs = imgs.length > 0;
        
        let thumbHtml = '';
        if (hasImgs) {
            thumbHtml = `<img src="/LabInSmile/images/${imgs[0]}" alt="${escapeHtml(item.nome)}" id="img-thumb-${item.id}">`;
        } else {
            thumbHtml = `
                <div class="no-image-placeholder" id="img-thumb-${item.id}">
                    <svg style="width:28px;height:28px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            `;
        }

        const badgeCount = imgs.length > 1 ? `<span class="images-count-badge" id="img-count-badge-${item.id}">+${imgs.length} imagens</span>` : '';
        const tags = Array.isArray(item.tags) ? item.tags : [];
        const tagIds = tags.map(t => t.id).join(',');
        
        const tagsHtml = tags.map(tag => `<span class="badge-chip" data-tag-id="${tag.id}">${escapeHtml(tag.nome)}</span>`).join('');
        const descBr = (item.descricao || '').replace(/\n/g, '<br>');

        return `
            <div class="service-row-card" id="product-${item.id}"
                 data-id="${item.id}"
                 data-nome="${escapeHtml(item.nome)}"
                 data-descricao="${escapeHtml(item.descricao)}"
                 data-imagem="${escapeHtml(item.imagem || '')}"
                 data-tags="${escapeHtml(tagIds)}">
                
                <div class="service-thumbnail">
                    ${thumbHtml}
                    ${badgeCount}
                </div>

                <div class="service-details-row">
                    <div class="service-card-header">
                        <h3 id="txt-title-${item.id}">${escapeHtml(item.nome)}</h3>
                    </div>
                    <p class="service-card-desc" id="txt-desc-${item.id}">${descBr}</p>
                    <div class="service-row-badges" id="badges-container-${item.id}">
                        ${tagsHtml}
                    </div>
                </div>

                <div class="service-actions-row">
                    <button type="button" class="btn-action-edit btn-edit" data-id="${item.id}">
                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        <span>Editar</span>
                    </button>
                    
                    <form method="POST" class="delete-form" data-id="${item.id}" style="width:100%">
                        <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${item.id}">
                        <button type="submit" class="btn-action-delete">
                            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            <span>Eliminar</span>
                        </button>
                    </form>
                </div>
            </div>
        `;
    }

    // FORM AJAX SUBMISSION
    productForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        
        fetch('admin.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const action = fd.get('action');
                const item = json.item;
                
                if (action === 'create') {
                    // Prepend new card to the list
                    const placeholder = document.getElementById('no-services-placeholder');
                    if (placeholder) placeholder.remove();
                    
                    productsList.insertAdjacentHTML('afterbegin', renderProductCard(item));
                    
                    // Increment metrics
                    const countEl = document.getElementById('metric-services-count');
                    if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;
                    
                    showToast('Serviço Criado', 'Novo serviço registado com sucesso.', 'success');
                } else if (action === 'update') {
                    const card = document.getElementById(`product-${item.id}`);
                    if (card) {
                        card.dataset.nome = item.nome;
                        card.dataset.descricao = item.descricao;
                        card.dataset.imagem = item.imagem;
                        
                        const tagIds = (item.tags || []).map(t => t.id).join(',');
                        card.dataset.tags = tagIds;

                        // Update text & layouts inside card
                        const titleTxt = document.getElementById(`txt-title-${item.id}`);
                        if (titleTxt) titleTxt.textContent = item.nome;

                        const descTxt = document.getElementById(`txt-desc-${item.id}`);
                        if (descTxt) descTxt.innerHTML = (item.descricao || '').replace(/\n/g, '<br>');

                        const badgesContainer = document.getElementById(`badges-container-${item.id}`);
                        if (badgesContainer) {
                            badgesContainer.innerHTML = (item.tags || []).map(tag => `
                                <span class="badge-chip" data-tag-id="${tag.id}">${escapeHtml(tag.nome)}</span>
                            `).join('');
                        }

                        syncCardImage(item.id, parseImages(item.imagem));
                    }
                    showToast('Serviço Atualizado', 'Alterações guardadas com sucesso.', 'success');
                }
                closeModal();
            } else {
                showToast('Erro de Execução', json.message || 'Ocorreu um erro ao guardar.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de Rede', 'Não foi possível enviar o formulário.', 'error');
        });
    });

    // CARD DELETION AJAX
    productsList.addEventListener('submit', function(e) {
        const form = e.target.closest('.delete-form');
        if (!form) return;

        e.preventDefault();
        if (!confirm('Tens a certeza que queres eliminar este serviço permanentemente?')) return;

        const fd = new FormData(form);
        const cardId = fd.get('id');
        const card = document.getElementById(`product-${cardId}`);

        fetch('admin.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                if (card) {
                    card.classList.add('fade-out');
                    setTimeout(() => {
                        card.remove();
                        // If list is empty, show placeholder
                        if (productsList.querySelectorAll('.service-row-card').length === 0) {
                            productsList.innerHTML = '<div class="no-services-found" id="no-services-placeholder">Nenhum serviço registado.</div>';
                        }
                    }, 200);
                }
                // Decrement metrics
                const countEl = document.getElementById('metric-services-count');
                if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent || 0) - 1);

                showToast('Serviço Eliminado', 'O registo foi removido da base de dados.', 'success');
            } else {
                showToast('Erro ao Eliminar', json.message || 'Erro do servidor.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de Rede', 'Ligação perdida com o servidor.', 'error');
        });
    });

    // SEARCH/FILTER CLIENT-SIDE
    if (adminSearch) {
        adminSearch.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#products-list .service-row-card').forEach(card => {
                const nome = (card.dataset.nome || '').toLowerCase();
                const desc = (card.dataset.descricao || '').toLowerCase();
                const tags = Array.from(card.querySelectorAll('.badge-chip')).map(t => t.textContent.toLowerCase()).join(' ');
                
                const matches = q === '' || nome.includes(q) || desc.includes(q) || tags.includes(q);
                
                if (matches) {
                    card.style.display = '';
                    card.classList.remove('fade-out');
                } else {
                    card.classList.add('fade-out');
                    setTimeout(() => {
                        if (card.classList.contains('fade-out')) {
                            card.style.display = 'none';
                        }
                    }, 200);
                }
            });
        });
    }

    // TAG MANAGEMENT AJAX
    tagForm && tagForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);

        fetch('admin.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            if (json.success && json.tag) {
                const empty = tagList.querySelector('.tag-empty');
                if (empty) empty.remove();

                const tagId = json.tag.id;
                const tagName = json.tag.nome;

                const existing = tagList.querySelector(`[data-tag-id="${tagId}"]`);
                if (!existing) {
                    // Append chip to management list
                    tagList.insertAdjacentHTML('beforeend', `
                        <span class="tag-chip-item" data-tag-id="${tagId}" data-tag-nome="${escapeHtml(tagName)}">
                            <span>${escapeHtml(tagName)}</span>
                            <button type="button" class="btn-delete-tag" title="Remover tag">&times;</button>
                        </span>
                    `);

                    // Append select option inside modal form
                    formTagsSelect.insertAdjacentHTML('beforeend', `
                        <label class="tag-option-label" id="tag-option-label-${tagId}">
                            <input type="checkbox" name="tags[]" value="${tagId}">
                            <span>${escapeHtml(tagName)}</span>
                        </label>
                    `);
                }

                // Increment tag metrics
                const countEl = document.getElementById('metric-tags-count');
                if (countEl) countEl.textContent = parseInt(countEl.textContent || 0) + 1;

                this.reset();
                showToast('Tag Criada', `A tag "${tagName}" foi registada.`, 'success');
            } else {
                showToast('Erro na Tag', json.message || 'Não foi possível criar a tag.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de Rede', 'Não foi possível ligar ao servidor.', 'error');
        });
    });

    tagList && tagList.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-tag');
        if (!btn) return;

        const chip = btn.closest('.tag-chip-item');
        if (!chip) return;

        if (!confirm(`Tens a certeza que queres eliminar a tag "${chip.dataset.tagNome}"? Ela será removida de todos os serviços associados.`)) return;

        const fd = new FormData();
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fd.append('action', 'delete_tag');
        fd.append('tag_id', chip.dataset.tagId);

        fetch('admin.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const tagId = String(json.tag_id || chip.dataset.tagId);
                
                // Remove elements from tags layout
                document.querySelectorAll(`[data-tag-id="${tagId}"]`).forEach(el => el.remove());
                
                const selectLabel = document.getElementById(`tag-option-label-${tagId}`);
                if (selectLabel) selectLabel.remove();

                // Clean data-tags reference inside DOM services lists
                document.querySelectorAll('#products-list .service-row-card').forEach(card => {
                    const currentTags = (card.dataset.tags || '').split(',').filter(id => id && id !== tagId);
                    card.dataset.tags = currentTags.join(',');
                });

                // Decrement metrics
                const countEl = document.getElementById('metric-tags-count');
                if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent || 0) - 1);

                showToast('Tag Eliminada', 'A tag foi removida de todo o sistema.', 'success');
            } else {
                showToast('Erro ao Remover', json.message || 'Erro do servidor.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de Rede', 'Não foi possível ligar ao servidor.', 'error');
        });
    });
    </script>
</body>
</html>
