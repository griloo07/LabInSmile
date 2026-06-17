<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado. Apenas administradores.';
    exit;
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

$mensagem = '';

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
            // Admin panel now asks for `categoria` instead of `titulo` and no description is required.
            $categoria = trim($_POST['categoria'] ?? '');
            $imagem = portfolio_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);
            $titulo = $categoria; // store category in the existing `titulo` column for compatibility
            $descricao = '';

            if ($titulo) {
                $stmt = $conn->prepare('INSERT INTO portfolio (titulo, descricao, imagem) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $titulo, $descricao, $imagem);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    $mensagem = 'Exemplo adicionado.';
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'item' => ['id' => $newId, 'titulo' => $titulo, 'descricao' => $descricao, 'imagem' => $imagem]];
                } else {
                    $mensagem = 'Erro ao adicionar: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'Preenche a categoria.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $categoria = trim($_POST['categoria'] ?? '');
            $imagem = portfolio_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);
            $titulo = $categoria;
            $descricao = '';

            if ($id && $titulo) {
                $stmt = $conn->prepare('UPDATE portfolio SET titulo = ?, descricao = ?, imagem = ? WHERE id = ?');
                $stmt->bind_param('sssi', $titulo, $descricao, $imagem, $id);
                if ($stmt->execute()) {
                    $mensagem = 'Exemplo atualizado.';
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'item' => ['id' => $id, 'titulo' => $titulo, 'descricao' => $descricao, 'imagem' => $imagem]];
                } else {
                    $mensagem = 'Erro ao atualizar: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'Dados inválidos para atualização.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare('DELETE FROM portfolio WHERE id = ?');
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $mensagem = 'Exemplo removido.';
                    if ($isAjax) $ajaxResponse = ['success' => true, 'message' => $mensagem, 'id' => $id];
                } else {
                    $mensagem = 'Erro ao remover: ' . $stmt->error;
                    if ($isAjax) $ajaxResponse['message'] = $mensagem;
                }
                $stmt->close();
            } else {
                $mensagem = 'ID inválido para eliminação.';
                if ($isAjax) $ajaxResponse['message'] = $mensagem;
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ajaxResponse ?? ['success' => false, 'message' => $mensagem ?? '']);
        exit;
    }

    header('Location: manage_portfolio.php');
    exit;
}

$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    if ($id) {
        $stmt = $conn->prepare('SELECT * FROM portfolio WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_item = $res->fetch_assoc();
        $stmt->close();
    }
}

$result = $conn->query('SELECT * FROM portfolio ORDER BY id DESC');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Painel Portfolio</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#111}
        .btn{display:inline-block;padding:8px 12px;background:#0b6e4f;color:#fff;border-radius:6px;text-decoration:none}
        .card{background:#fff;padding:12px;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,0.05)}
        .image-preview { display:flex; gap:8px; align-items:flex-start; }
        .image-preview .preview-item { position:relative; display:inline-block; }
        .image-preview img{width:90px;height:70px;object-fit:cover;border-radius:6px;display:block}
        .image-preview .remove-image { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.6); color:#fff; border:0; width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; line-height:1 }
        .product{border:1px solid #eee;padding:10px;margin:10px 0;display:flex;gap:12px}
        .product img{width:100px;height:auto;border-radius:6px}
        .actions form{display:inline-block;margin-left:8px}
    </style>
</head>
<body>
    <h1>Painel Portfolio</h1>
    <?php if (!empty($mensagem)): ?>
        <div style="max-width:900px;margin:10px 0;padding:10px;border-radius:6px;background:#eef2ff;color:#0b6e4f;"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <p><a href="home.php" class="btn">Voltar</a></p>

    <div class="card">
        <h2><?= $edit_item ? 'Editar Exemplo' : 'Adicionar Exemplo' ?></h2>
        <form id="portfolio-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="form-id" value="">
            <input type="hidden" name="imagem" id="form-imagem" value="<?= htmlspecialchars($edit_item['imagem'] ?? '') ?>">

            <div>
                <label>Categoria</label><br>
                <input type="text" name="categoria" id="form-categoria" required value="<?= htmlspecialchars($edit_item['titulo'] ?? '') ?>">
            </div>

            <div>
                <label>Imagens</label><br>
                <div style="display:flex; gap:12px; align-items:flex-start">
                    <div id="preview" class="image-preview">
                        <?php foreach (portfolio_images($edit_item['imagem'] ?? '') as $image): ?>
                            <div class="preview-item" data-filename="<?= htmlspecialchars($image, ENT_QUOTES) ?>">
                                <img src="/LabInSmile/images/<?= htmlspecialchars($image) ?>" alt="">
                                <button type="button" class="remove-image" data-filename="<?= htmlspecialchars($image, ENT_QUOTES) ?>" aria-label="Remover imagem">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <input type="file" id="image-input" accept="image/*" multiple>
                        <div style="font-size:13px;color:#6b7280;margin-top:6px">Pode escolher uma ou mais imagens. Serão enviadas imediatamente.</div>
                    </div>
                </div>
            </div>

            <div style="margin-top:10px">
                <button type="submit" id="form-submit"><?= $edit_item ? 'Atualizar' : 'Adicionar' ?></button>
                <button type="button" id="form-cancel" style="display:none;">Cancelar</button>
            </div>
        </form>
        <div id="upload-status" style="margin-top:8px; font-size:13px; color:#374151;"></div>
    </div>

    <h2 style="margin-top:20px">Exemplos existentes</h2>

    <div id="items-list">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $imgs = portfolio_images($row['imagem']); ?>
                <div class="product card" id="item-<?= intval($row['id']) ?>" data-id="<?= intval($row['id']) ?>" data-titulo="<?= htmlspecialchars($row['titulo'], ENT_QUOTES) ?>" data-imagem="<?= htmlspecialchars($row['imagem'], ENT_QUOTES) ?>">
                    <?php if (!empty($imgs)): ?>
                        <div><img src="/LabInSmile/images/<?= htmlspecialchars($imgs[0]) ?>" alt="<?= htmlspecialchars($row['titulo']) ?>"></div>
                    <?php endif; ?>
                    <div style="flex:1">
                        <strong><?= htmlspecialchars($row['titulo']) ?></strong>
                    </div>
                    <div class="actions">
                        <a href="#" class="btn-edit">Editar</a>
                        <form method="POST" class="delete-form" data-id="<?= intval($row['id']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                            <button type="submit">Eliminar</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Sem exemplos.</p>
        <?php endif; ?>
    </div>

    <script>
    // Upload helper (uses existing upload_image.php)
    async function uploadFile(file) {
        const fd = new FormData();
        fd.append('image', file);
        const res = await fetch('upload_image.php', { method: 'POST', body: fd });
        return res.json();
    }

    function parseImages(value) {
        if (!value) return [];
        try { const parsed = JSON.parse(value); return Array.isArray(parsed) ? parsed.filter(Boolean) : [value]; }
        catch (err) { return [value]; }
    }

    function storeImages(input, images) {
        const clean = [...new Set(images.filter(Boolean))];
        input.value = JSON.stringify(clean);
        return clean;
    }

    function renderPreview(target, images) {
        target.innerHTML = images.map(filename => (
            '<div class="preview-item" data-filename="' + filename.replace(/"/g, '&quot;') + '">' +
                '<img src="/LabInSmile/images/' + filename + '" alt="">' +
                '<button type="button" class="remove-image" data-filename="' + filename.replace(/"/g, '&quot;') + '" aria-label="Remover imagem">&times;</button>' +
            '</div>'
        )).join('');
    }
    

    async function addFiles(files, input, target, statusTarget) {
        const selected = Array.from(files || []);
        if (selected.length === 0) return;
        if (statusTarget) statusTarget.textContent = 'A carregar...';
        const images = parseImages(input.value);
        try {
            for (const file of selected) {
                const json = await uploadFile(file);
                if (!json.success) throw new Error(json.error || 'Falha');
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
        imageInput.addEventListener('change', function() { addFiles(this.files, formImagem, preview, uploadStatus); });
    }

    // Delegate remove clicks from preview container (attach after element is available)
    if (preview) {
        preview.addEventListener('click', function(e){
            const btn = e.target.closest('.remove-image');
            if (!btn) return;
            const filename = btn.dataset.filename;
            if (!filename) return;
            // Confirm permanent deletion from server
            if (!confirm('Eliminar esta imagem permanentemente? Esta ação não pode ser desfeita.')) return;
            const tokenEl = document.querySelector('input[name="csrf_token"]');
            const token = tokenEl ? tokenEl.value : '';
            const fd = new FormData();
            fd.append('csrf_token', token);
            fd.append('filename', filename);
            if (uploadStatus) uploadStatus.textContent = 'A remover imagem...';
            fetch('delete_image.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(json => {
                if (json.success) {
                    const images = parseImages(formImagem.value);
                    const idx = images.indexOf(filename);
                    if (idx !== -1) images.splice(idx, 1);
                    storeImages(formImagem, images);
                    renderPreview(preview, images);
                    if (uploadStatus) uploadStatus.textContent = json.message || 'Imagem eliminada.';
                } else {
                    alert(json.message || 'Erro ao eliminar imagem.');
                    if (uploadStatus) uploadStatus.textContent = '';
                }
            }).catch(err => { console.error(err); alert('Erro de rede ao eliminar imagem.'); if (uploadStatus) uploadStatus.textContent = ''; });
        });
    }

    // Edit handlers
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function(e){
            e.preventDefault();
            const card = this.closest('.product');
            if (!card) return;
            document.getElementById('form-id').value = card.dataset.id || '';
            // Admin edits the category (stored in `titulo` column)
            document.getElementById('form-categoria').value = card.dataset.titulo || '';
            document.getElementById('form-imagem').value = card.dataset.imagem || '';
            renderPreview(preview, parseImages(card.dataset.imagem || ''));
            document.getElementById('form-action').value = 'update';
            document.getElementById('form-cancel').style.display = 'inline-block';
            window.scrollTo({top:0, behavior:'smooth'});
        });
    });

    // Cancel button
    document.getElementById('form-cancel').addEventListener('click', function(){
        document.getElementById('form-action').value = 'create';
        document.getElementById('form-id').value = '';
        document.getElementById('form-categoria').value = '';
        document.getElementById('form-imagem').value = '';
        renderPreview(preview, []);
        this.style.display = 'none';
    });

    // Form submit via AJAX for better UX
    document.getElementById('portfolio-form').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        fetch('manage_portfolio.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(json => {
            if (json.success) {
                const action = fd.get('action');
                if (action === 'create') {
                    // Reload after creating a new item to refresh the admin list
                    alert(json.message || 'Exemplo adicionado.');
                    window.location.reload();
                } else if (action === 'update') {
                    alert(json.message || 'Exemplo atualizado.');
                    window.location.reload();
                }
            } else {
                alert(json.message || 'Erro ao guardar.');
            }
        }).catch(err => { console.error(err); alert('Erro de rede.'); });
    });

    // Delegated delete handling
    document.getElementById('items-list').addEventListener('submit', function(e){
        const form = e.target.closest('.delete-form');
        if (!form) return;
        e.preventDefault();
        if (!confirm('Tens a certeza que queres eliminar este exemplo?')) return;
        const fd = new FormData(form);
        fetch('manage_portfolio.php', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(json => {
            if (json.success) {
                const id = json.id || fd.get('id');
                const el = document.getElementById('item-'+id);
                if (el) el.remove();
                alert(json.message || 'Exemplo removido.');
            } else {
                alert(json.message || 'Erro ao eliminar.');
            }
        }).catch(err => { console.error(err); alert('Erro de rede.'); });
    });
    </script>

</body>
</html>
