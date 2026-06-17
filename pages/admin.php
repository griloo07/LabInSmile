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
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <title>Painel Admin - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .admin-wrap { max-width:1100px; margin:20px auto; padding:20px; }
        .card { background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,0.05); }
        .product { border:1px solid #eee; padding:10px; margin:10px 0; display:flex; gap:12px; align-items:flex-start; }
        .product img{ width:100px; height:auto; border-radius:6px }
        .actions form{ display:inline-block; margin-left:8px }
        .image-row{ display:flex; gap:10px; align-items:flex-start }
        .image-preview{ display:flex; flex-wrap:wrap; gap:8px; min-width:120px }
        .image-preview img{ width:90px; height:70px; object-fit:cover; border-radius:6px }
        .muted-small{ font-size:12px; color:#6b7280 }
        .mt-10{ margin-top:10px }
        .admin-search { margin:12px 0 18px; display:flex; gap:8px; }
        .admin-search input { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,0.03); }
        .admin-toast { position:fixed; right:20px; bottom:20px; min-width:200px; max-width:360px; background:#0b6e4f; color:#fff; padding:12px 16px; border-radius:10px; box-shadow:0 8px 24px rgba(11,110,79,0.18); display:none; z-index:9999; }
        .admin-toast.error { background:#b91c1c; box-shadow:0 8px 24px rgba(185,28,28,0.15); }
        .product .actions a, .product .actions button { margin-left:8px; padding:8px 10px; border-radius:8px; border:0; background:transparent; cursor:pointer; color:#0b6e4f; text-decoration:none; }
        .product .actions .btn-edit { background:linear-gradient(180deg,#ecfdf5,#dcfce7); border:1px solid #bbf7d0; padding:6px 10px; color:#065f46; border-radius:8px; }
        .product .actions .btn-delete { background:linear-gradient(180deg,#fff1f2,#fee2e2); border:1px solid #fecaca; padding:6px 10px; color:#991b1b; border-radius:8px; }
        .product.card:hover { transform:translateY(-3px); transition:transform .14s ease; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
        #products-list { display:flex; flex-direction:column; gap:12px; margin-top:12px; }
        .tag-manager { margin-bottom:16px; }
        .tag-form { display:flex; gap:8px; align-items:center; margin-bottom:12px; }
        .tag-form input { flex:1; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; }
        .tag-list, .tag-options, .tag-badges { display:flex; flex-wrap:wrap; gap:8px; }
        .tag-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 9px; border:1px solid #d7eee5; border-radius:999px; background:#f0fdf7; color:#065f46; font-size:13px; font-weight:700; }
        .tag-chip button { border:0; background:transparent; color:#991b1b; cursor:pointer; font-weight:800; padding:0 2px; }
        .tag-options { margin-top:6px; }
        .tag-option { display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer; font-size:14px; }
        .tag-option input { margin:0; }
        .tag-badges { margin-top:8px; }
        .tag-empty { color:#6b7280; font-size:13px; }
    </style>
</head>
<body>

<div class="admin-wrap">
    <div style="margin-bottom:12px;">
        <a href="/LabInSmile/pages/servicos.php" id="btn-voltar" style="text-decoration:none; display:inline-block; padding:8px 12px; background:#0b6e4f; color:#fff; border-radius:6px;">← Voltar</a>
    </div>
    <h1>Painel Admin</h1>
        <div class="admin-search">
            <input type="search" id="admin-search" placeholder="Procurar serviços por título ou descrição...">
        </div>

    <?php if ($mensagem): ?>
        <p><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <div class="card tag-manager">
        <h2>Keywords / Tags</h2>
        <form id="tag-form" class="tag-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="create_tag">
            <input type="text" name="tag_nome" id="tag-nome" placeholder="Ex.: Protese fixa, Ortodontia, Reparacao..." required>
            <button type="submit">Criar tag</button>
        </form>
        <div id="tag-list" class="tag-list">
            <?php if (!empty($all_tags)): ?>
                <?php foreach ($all_tags as $tag): ?>
                    <span class="tag-chip" data-tag-id="<?= intval($tag['id']) ?>" data-tag-nome="<?= htmlspecialchars($tag['nome'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($tag['nome']) ?>
                        <button type="button" class="btn-delete-tag" title="Remover tag">x</button>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="tag-empty">Ainda nao existem tags.</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 id="form-title"><?= $edit_product ? 'Editar Serviço' : 'Adicionar Serviço' ?></h2>

        <form id="product-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="form-id" value="">
            <input type="hidden" name="imagem" id="form-imagem" value="<?= htmlspecialchars($edit_product['imagem'] ?? '') ?>">

            <div>
                <label>T&iacute;tulo</label><br>
                <input type="text" name="nome" id="form-nome" required value="<?= htmlspecialchars($edit_product['nome'] ?? '') ?>">
            </div>

            <div>
                <label>Descrição</label><br>
                <textarea name="descricao" id="form-descricao" required><?= htmlspecialchars($edit_product['descricao'] ?? '') ?></textarea>
            </div>

            <div>
                <label>Keywords / Tags</label>
                <div id="form-tags" class="tag-options">
                    <?php if (!empty($all_tags)): ?>
                        <?php foreach ($all_tags as $tag): ?>
                            <label class="tag-option">
                                <input type="checkbox" name="tags[]" value="<?= intval($tag['id']) ?>" <?= in_array((int)$tag['id'], $edit_tag_ids, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($tag['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="tag-empty">Cria tags acima para as atribuir aos servicos.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label>Imagens</label><br>
                <div class="image-row">
                    <div id="preview" class="image-preview">
                        <?php foreach (service_images($edit_product['imagem'] ?? '') as $image): ?>
                            <img src="/LabInSmile/images/<?= htmlspecialchars($image) ?>" alt="">
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <input type="file" id="image-input" accept="image/*" multiple>
                        <div class="muted-small">Pode escolher uma ou mais imagens. Serão enviadas imediatamente.</div>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <button type="submit" id="form-submit"><?= $edit_product ? 'Atualizar' : 'Adicionar' ?></button>
                 <button type="button" id="form-cancel" style="display:none;">Cancelar</button>
            </div>
        </form>

        <div id="upload-status" style="margin-top:8px; font-size:13px; color:#374151;"></div>
    </div>

    <h2 style="margin-top:20px">Serviços existentes</h2>

    <div id="products-list">

    <?php
    $result = $conn->query("SELECT * FROM services ORDER BY id DESC");

    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
    ?>

        <?php $row_tags = $service_tags_map[(int)$row['id']] ?? []; ?>
        <div class="product card" id="product-<?= intval($row['id']) ?>"
            data-id="<?= intval($row['id']) ?>"
            data-nome="<?= htmlspecialchars($row['nome'], ENT_QUOTES) ?>"
            data-descricao="<?= htmlspecialchars($row['descricao'], ENT_QUOTES) ?>"
            data-imagem="<?= htmlspecialchars($row['imagem'], ENT_QUOTES) ?>"
            data-tags="<?= htmlspecialchars(implode(',', array_column($row_tags, 'id')), ENT_QUOTES) ?>">
        <?php $row_images = service_images($row['imagem']); ?>
        <?php if (!empty($row_images)): ?>
            <div><img src="/LabInSmile/images/<?= htmlspecialchars($row_images[0]) ?>" alt="<?= htmlspecialchars($row['nome']) ?>" style="max-width:120px; border-radius:6px"></div>
        <?php endif; ?>

        <div class="flex-1">
            <strong><?= htmlspecialchars($row['nome']) ?></strong>
            <p><?= nl2br(htmlspecialchars($row['descricao'])) ?></p>
            <div class="tag-badges">
                <?php foreach ($row_tags as $tag): ?>
                    <span class="tag-chip" data-tag-id="<?= intval($tag['id']) ?>"><?= htmlspecialchars($tag['nome']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="actions">
            <a href="#" class="btn-edit">Editar</a>

            <form method="POST" class="delete-form" data-id="<?= intval($row['id']) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                <button type="submit" class="btn-delete">Eliminar</button>
            </form>
        </div>
    </div>

    <?php
        endwhile;
    else:
        echo "<p>Sem serviços.</p>";
    endif;
    ?>

    </div>

</div>

<div id="edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:700px;">
        <h3>Editar Serviço</h3>
        <form id="modal-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="modal-id">
            <div>
                <label>T&iacute;tulo</label><br>
                <input type="text" name="nome" id="modal-nome" required>
            </div>
            <div>
                <label>Descrição</label><br>
                <textarea name="descricao" id="modal-descricao" required></textarea>
            </div>
            <div>
                <label>Keywords / Tags</label>
                <div id="modal-tags" class="tag-options">
                    <?php if (!empty($all_tags)): ?>
                        <?php foreach ($all_tags as $tag): ?>
                            <label class="tag-option">
                                <input type="checkbox" name="tags[]" value="<?= intval($tag['id']) ?>">
                                <?= htmlspecialchars($tag['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="tag-empty">Cria tags no painel antes de editar.</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label>Imagens (ficheiros já carregados)</label><br>
                <div id="modal-preview" class="image-preview modal-preview"></div>
                <input type="hidden" name="imagem" id="modal-imagem">
                <input type="file" id="modal-image-input" accept="image/*" multiple>
            </div>
            <div class="modal-buttons">
                <button type="submit">Guardar</button>
                <button type="button" id="modal-close">Fechar</button>
            </div>
        </form>
    </div>
</div>

    <div id="admin-toast" class="admin-toast" role="status" aria-live="polite"></div>

<script>
// Back button behavior: prefer referrer to avoid going back to an empty form
;(function(){
    const btnVoltar = document.getElementById('btn-voltar');
    if (!btnVoltar) return;
    btnVoltar.addEventListener('click', function(e){
        e.preventDefault();
        const ref = document.referrer || '';
        // If the referrer looks like the services list, go there, otherwise use a safe fallback
        if (ref && ref.indexOf('servicos.php') !== -1) {
            window.location.href = ref;
        } else {
            window.location.href = '/LabInSmile/pages/servicos.php';
        }
    });
})();

// Upload helper
async function uploadFile(file) {
    const fd = new FormData();
    fd.append('image', file);

    const res = await fetch('upload_image.php', { method: 'POST', body: fd });
    return res.json();
}

function parseImages(value) {
    if (!value) return [];
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed.filter(Boolean) : [value];
    } catch (err) {
        return [value];
    }
}

function storeImages(input, images) {
    const clean = [...new Set(images.filter(Boolean))];
    input.value = JSON.stringify(clean);
    return clean;
}

function renderPreview(target, images) {
    target.innerHTML = images.map(filename => (
        '<img src="/LabInSmile/images/' + filename + '" alt="">'
    )).join('');
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]; }); }

function selectedTagIds(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map(input => String(input.value));
}

function setSelectedTags(container, ids) {
    if (!container) return;
    const selected = new Set((ids || []).map(String));
    container.querySelectorAll('input[type="checkbox"]').forEach(input => {
        input.checked = selected.has(String(input.value));
    });
}

function renderTagBadges(tags) {
    const list = Array.isArray(tags) ? tags : [];
    return list.map(tag => '<span class="tag-chip" data-tag-id="' + escapeHtml(tag.id || '') + '">' + escapeHtml(tag.nome || '') + '</span>').join('');
}

function syncTagOptions(tag) {
    const html = '<label class="tag-option"><input type="checkbox" name="tags[]" value="' + tag.id + '"> ' + escapeHtml(tag.nome) + '</label>';
    document.querySelectorAll('#form-tags, #modal-tags').forEach(container => {
        const empty = container.querySelector('.tag-empty');
        if (empty) empty.remove();
        container.insertAdjacentHTML('beforeend', html);
    });
}

async function addFiles(files, input, target, statusTarget) {
    const selected = Array.from(files || []);
    if (selected.length === 0) return;

    if (statusTarget) statusTarget.textContent = 'A carregar...';
    const images = parseImages(input.value);

    try {
        for (const file of selected) {
            const json = await uploadFile(file);
            if (!json.success) {
                throw new Error(json.error || 'Falha');
            }
            images.push(json.filename);
        }

        const clean = storeImages(input, images);
        renderPreview(target, clean);
        if (statusTarget) statusTarget.textContent = clean.length + ' imagem(ns) carregada(s).';
    } catch (err) {
        if (statusTarget) statusTarget.textContent = 'Erro: ' + err.message;
        else target.innerHTML = 'Erro: ' + err.message;
    }
}

const imageInput = document.getElementById('image-input');
const preview = document.getElementById('preview');
const formImagem = document.getElementById('form-imagem');
const uploadStatus = document.getElementById('upload-status');

if (imageInput) {
    imageInput.addEventListener('change', function() {
        addFiles(this.files, formImagem, preview, uploadStatus);
    });
}

// Modal edit
const modal = document.getElementById('edit-modal');
const modalClose = document.getElementById('modal-close');
const modalNome = document.getElementById('modal-nome');
const modalDescricao = document.getElementById('modal-descricao');
const modalId = document.getElementById('modal-id');
const modalImagem = document.getElementById('modal-imagem');
const modalPreview = document.getElementById('modal-preview');
const modalImageInput = document.getElementById('modal-image-input');
const formTags = document.getElementById('form-tags');
const modalTags = document.getElementById('modal-tags');

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const card = this.closest('.product');
        modalId.value = card.dataset.id || '';
        modalNome.value = card.dataset.nome || '';
        modalDescricao.value = card.dataset.descricao || '';
        modalImagem.value = card.dataset.imagem || '';
        setSelectedTags(modalTags, (card.dataset.tags || '').split(',').filter(Boolean));
        renderPreview(modalPreview, parseImages(modalImagem.value));
        modal.style.display = 'flex';
    });
});

modalClose.addEventListener('click', function(){ modal.style.display='none'; });

// allow uploading new image from modal
if (modalImageInput) {
    modalImageInput.addEventListener('change', function(){
        addFiles(this.files, modalImagem, modalPreview, null);
    });
}

// When modal form submits, post to admin.php via normal POST (will reload)
// When main form is used for create, ensure action is set
const formAction = document.getElementById('form-action');
const formCancel = document.getElementById('form-cancel');

// If user clicks cancel (when editing), reset the form
formCancel.addEventListener('click', function(){
    formAction.value = 'create';
    document.getElementById('form-id').value = '';
    document.getElementById('form-nome').value = '';
    document.getElementById('form-descricao').value = '';
    document.getElementById('form-imagem').value = '';
    setSelectedTags(formTags, []);
    preview.innerHTML = '';
        document.getElementById('form-title').textContent = 'Adicionar Serviço';
    formCancel.style.display = 'none';
});

// When clicking an edit button, also populate the top form for quick edit
// Consolidated handlers (search, edit delegation, AJAX actions and toast notifications)
(function(){
    const productsList = document.getElementById('products-list');
    const adminSearch = document.getElementById('admin-search');
    const toastEl = document.getElementById('admin-toast');
    const tagForm = document.getElementById('tag-form');
    const tagList = document.getElementById('tag-list');

    function showToast(msg, type='success'){
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.classList.toggle('error', type === 'error');
        toastEl.style.display = 'block';
        clearTimeout(toastEl._timeout);
        toastEl._timeout = setTimeout(()=>{ toastEl.style.display = 'none'; toastEl.classList.remove('error'); }, 4200);
    }

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]; }); }

    function renderProductCard(item){
        const imgs = parseImages(item.imagem || '');
        const imgHtml = imgs.length ? '<div><img src="/LabInSmile/images/'+imgs[0]+'" alt="'+escapeHtml(item.nome)+'" style="max-width:120px; border-radius:6px"></div>' : '';
        const descricaoHtml = (item.descricao||'').replace(/\n/g,'<br>');
        const tags = Array.isArray(item.tags) ? item.tags : [];
        const tagIds = tags.map(tag => tag.id).join(',');
        return '<div class="product card" id="product-'+item.id+'" data-id="'+item.id+'" data-nome="'+escapeHtml(item.nome)+'" data-descricao="'+escapeHtml(item.descricao)+'" data-imagem="'+escapeHtml(item.imagem||'')+'" data-tags="'+escapeHtml(tagIds)+'">'
            + imgHtml
            + '<div class="flex-1"><strong>'+escapeHtml(item.nome)+'</strong><p>'+descricaoHtml+'</p><div class="tag-badges">'+renderTagBadges(tags)+'</div></div>'
            + '<div class="actions">'
            + '<a href="#" class="btn-edit">Editar</a>'
            + '<form method="POST" class="delete-form" data-id="'+item.id+'">'
            + '<input type="hidden" name="csrf_token" value="'+document.querySelector('input[name=csrf_token]').value+'">'
            + '<input type="hidden" name="action" value="delete">'
            + '<input type="hidden" name="id" value="'+item.id+'">'
            + '<button type="submit" class="btn-delete">Eliminar</button>'
            + '</form></div></div>';
    }

    tagForm && tagForm.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        fetch('admin.php', {method:'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json())
        .then(json => {
            if (json.success && json.tag) {
                const empty = tagList.querySelector('.tag-empty');
                if (empty) empty.remove();
                const existing = tagList.querySelector('[data-tag-id="'+json.tag.id+'"]');
                if (!existing) {
                    tagList.insertAdjacentHTML('beforeend', '<span class="tag-chip" data-tag-id="'+json.tag.id+'" data-tag-nome="'+escapeHtml(json.tag.nome)+'">'+escapeHtml(json.tag.nome)+' <button type="button" class="btn-delete-tag" title="Remover tag">x</button></span>');
                    syncTagOptions(json.tag);
                }
                this.reset();
                showToast(json.message || 'Tag guardada.', 'success');
            } else {
                showToast(json.message || 'Erro ao guardar tag.', 'error');
            }
        }).catch(err => { console.error(err); showToast('Erro de rede.', 'error'); });
    });

    tagList && tagList.addEventListener('click', function(e){
        const btn = e.target.closest('.btn-delete-tag');
        if (!btn) return;
        const chip = btn.closest('.tag-chip');
        if (!chip) return;
        if (!confirm('Remover esta tag? Ela tambem sera retirada dos servicos associados.')) return;
        const fd = new FormData();
        fd.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
        fd.append('action', 'delete_tag');
        fd.append('tag_id', chip.dataset.tagId);
        fetch('admin.php', {method:'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const tagId = String(json.tag_id || chip.dataset.tagId);
                document.querySelectorAll('[data-tag-id="'+tagId+'"]').forEach(el => el.remove());
                document.querySelectorAll('#form-tags input[value="'+tagId+'"], #modal-tags input[value="'+tagId+'"]').forEach(input => input.closest('.tag-option').remove());
                document.querySelectorAll('#products-list .product').forEach(card => {
                    const ids = (card.dataset.tags || '').split(',').filter(id => id && id !== tagId);
                    card.dataset.tags = ids.join(',');
                });
                showToast(json.message || 'Tag removida.', 'success');
            } else {
                showToast(json.message || 'Erro ao remover tag.', 'error');
            }
        }).catch(err => { console.error(err); showToast('Erro de rede.', 'error'); });
    });

    // Search/filter
    if (adminSearch) {
        adminSearch.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#products-list .product').forEach(card=>{
                const nome = (card.dataset.nome||'').toLowerCase();
                const desc = (card.dataset.descricao||'').toLowerCase();
                const tags = Array.from(card.querySelectorAll('.tag-badges .tag-chip')).map(tag => tag.textContent.toLowerCase()).join(' ');
                const ok = q === '' || nome.includes(q) || desc.includes(q) || tags.includes(q);
                card.style.display = ok ? '' : 'none';
            });
        });
    }

    // Delegated click for Edit (populate modal and top form)
    productsList && productsList.addEventListener('click', function(e){
        const btn = e.target.closest('.btn-edit');
        if (!btn) return;
        e.preventDefault();
        const card = btn.closest('.product');
        if (!card) return;
        modalId.value = card.dataset.id || '';
        modalNome.value = card.dataset.nome || '';
        modalDescricao.value = card.dataset.descricao || '';
        modalImagem.value = card.dataset.imagem || '';
        const tagIds = (card.dataset.tags || '').split(',').filter(Boolean);
        setSelectedTags(modalTags, tagIds);
        renderPreview(modalPreview, parseImages(modalImagem.value));
        modal.style.display = 'flex';

        document.getElementById('form-id').value = card.dataset.id || '';
        document.getElementById('form-nome').value = card.dataset.nome || '';
        document.getElementById('form-descricao').value = card.dataset.descricao || '';
        document.getElementById('form-imagem').value = card.dataset.imagem || '';
        setSelectedTags(formTags, tagIds);
        renderPreview(preview, parseImages(card.dataset.imagem || ''));
        formAction.value = 'update';
        formCancel.style.display = 'inline-block';
        document.getElementById('form-title').textContent = 'Editar Serviço';
        window.scrollTo({top:0, behavior:'smooth'});
    });

    // Delegated delete (AJAX) handling
    productsList && productsList.addEventListener('submit', function(e){
        const form = e.target.closest('.delete-form');
        if (!form) return;
        e.preventDefault();
        if (!confirm('Tens a certeza que queres eliminar este serviço?')) return;
        const fd = new FormData(form);
        fetch('admin.php', {method:'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const id = json.id || fd.get('id');
                const el = document.getElementById('product-'+id);
                if (el) el.remove();
                showToast(json.message || 'Serviço eliminado.', 'success');
            } else {
                showToast(json.message || 'Erro ao eliminar.', 'error');
            }
        }).catch(err => {
            console.error(err);
            showToast('Erro de rede.', 'error');
        });
    });

    // Submit handler for top form (create/update) via AJAX
    const productForm = document.getElementById('product-form');
    productForm && productForm.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        fetch('admin.php', {method:'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const action = fd.get('action');
                if (action === 'create') {
                    const item = json.item || { id: json.id, nome: fd.get('nome'), descricao: fd.get('descricao'), imagem: fd.get('imagem') };
                    const container = document.getElementById('products-list');
                    if (container) container.insertAdjacentHTML('afterbegin', renderProductCard(item));
                    this.reset();
                    preview.innerHTML = '';
                    formAction.value = 'create';
                    formCancel.style.display = 'none';
                    document.getElementById('form-title').textContent = 'Adicionar Serviço';
                    showToast(json.message || 'Serviço adicionado.', 'success');
                } else if (action === 'update') {
                    const item = json.item || { id: fd.get('id'), nome: fd.get('nome'), descricao: fd.get('descricao'), imagem: fd.get('imagem') };
                    const card = document.getElementById('product-'+item.id);
                    if (card) {
                        card.dataset.nome = item.nome;
                        card.dataset.descricao = item.descricao;
                        card.dataset.imagem = item.imagem;
                        card.dataset.tags = (item.tags || []).map(tag => tag.id).join(',');
                        const imgEl = card.querySelector('img');
                        if (imgEl && parseImages(item.imagem).length) imgEl.src = '/LabInSmile/images/' + parseImages(item.imagem)[0];
                        card.querySelector('strong').textContent = item.nome;
                        card.querySelector('p').innerHTML = (item.descricao||'').replace(/\n/g,'<br>');
                        const badges = card.querySelector('.tag-badges');
                        if (badges) badges.innerHTML = renderTagBadges(item.tags || []);
                    }
                    formAction.value = 'create';
                    formCancel.style.display = 'none';
                    document.getElementById('form-id').value = '';
                    setSelectedTags(formTags, []);
                    document.getElementById('form-title').textContent = 'Adicionar Serviço';
                    showToast(json.message || 'Serviço atualizado.', 'success');
                }
            } else {
                showToast(json.message || 'Erro ao guardar.', 'error');
            }
        }).catch(err => { console.error(err); showToast('Erro de rede.', 'error'); });
    });

    // Modal form submits via AJAX (keeps modal UX)
    const modalForm = document.getElementById('modal-form');
    modalForm && modalForm.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        fetch('admin.php', {method:'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r=>r.json())
        .then(json=>{
            if (json.success){
                const item = json.item || { id: fd.get('id'), nome: fd.get('nome'), descricao: fd.get('descricao'), imagem: fd.get('imagem') };
                const card = document.getElementById('product-'+item.id);
                if (card) {
                    card.dataset.nome = item.nome;
                    card.dataset.descricao = item.descricao;
                    card.dataset.imagem = item.imagem;
                    card.dataset.tags = (item.tags || []).map(tag => tag.id).join(',');
                    const imgEl = card.querySelector('img');
                    if (imgEl && parseImages(item.imagem).length) imgEl.src = '/LabInSmile/images/' + parseImages(item.imagem)[0];
                    card.querySelector('strong').textContent = item.nome;
                    card.querySelector('p').innerHTML = (item.descricao||'').replace(/\n/g,'<br>');
                    const badges = card.querySelector('.tag-badges');
                    if (badges) badges.innerHTML = renderTagBadges(item.tags || []);
                }
                modal.style.display = 'none';
                showToast(json.message || 'Serviço atualizado.', 'success');
            } else {
                showToast(json.message || 'Erro ao guardar.', 'error');
            }
        }).catch(err=>{ console.error(err); showToast('Erro de rede.', 'error'); });
    });
})();
</script>
</body>
</html>
