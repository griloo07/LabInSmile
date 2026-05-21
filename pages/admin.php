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

// Tratar ações via POST: create, update, delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        $mensagem = 'Token CSRF inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $imagem = service_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);

            if ($nome && $descricao) {
                $stmt = $conn->prepare("INSERT INTO services (nome, descricao, imagem) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $nome, $descricao, $imagem);
                if ($stmt->execute()) {
                    $mensagem = "Serviço adicionado com sucesso!";
                } else {
                    $mensagem = "Erro ao adicionar: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $mensagem = "Preenche todos os campos.";
            }

        } elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $imagem = service_images_value(json_decode($_POST['imagem'] ?? '[]', true) ?: [$_POST['imagem'] ?? '']);

            if ($id && $nome && $descricao) {
                $stmt = $conn->prepare("UPDATE services SET nome = ?, descricao = ?, imagem = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $descricao, $imagem, $id);
                if ($stmt->execute()) {
                    $mensagem = "Serviço atualizado com sucesso!";
                } else {
                    $mensagem = "Erro ao atualizar: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $mensagem = "Dados inválidos para atualização.";
            }

        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $mensagem = "Serviço eliminado.";
                } else {
                    $mensagem = "Erro ao eliminar: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $mensagem = "ID inválido para eliminação.";
            }
        }
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
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <title>Painel Admin - LabInSmile</title>
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
    </style>
</head>
<body>

<div class="admin-wrap">
    <div style="margin-bottom:12px;">
        <a href="/LabInSmile/pages/servicos.php" onclick="if(document.referrer){ history.back(); return false; }" style="text-decoration:none; display:inline-block; padding:8px 12px; background:#0b6e4f; color:#fff; border-radius:6px;">← Voltar</a>
    </div>
    <h1>Painel Admin</h1>

    <?php if ($mensagem): ?>
        <p><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

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

    <?php
    $result = $conn->query("SELECT * FROM services ORDER BY id DESC");

    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
    ?>

    <div class="product card" 
         data-id="<?= intval($row['id']) ?>" 
         data-nome="<?= htmlspecialchars($row['nome'], ENT_QUOTES) ?>" 
         data-descricao="<?= htmlspecialchars($row['descricao'], ENT_QUOTES) ?>" 
         data-imagem="<?= htmlspecialchars($row['imagem'], ENT_QUOTES) ?>">
        <?php $row_images = service_images($row['imagem']); ?>
        <?php if (!empty($row_images)): ?>
            <div><img src="/LabInSmile/images/<?= htmlspecialchars($row_images[0]) ?>" alt="<?= htmlspecialchars($row['nome']) ?>" style="max-width:120px; border-radius:6px"></div>
        <?php endif; ?>

        <div class="flex-1">
            <strong><?= htmlspecialchars($row['nome']) ?></strong>
            <p><?= nl2br(htmlspecialchars($row['descricao'])) ?></p>
        </div>

        <div class="actions">
            <a href="#" class="btn-edit">Editar</a>

                <form method="POST" onsubmit="return confirm('Tens a certeza que queres eliminar este serviço?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= intval($row['id']) ?>">
                <button type="submit">Eliminar</button>
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

<script>
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

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const card = this.closest('.product');
        modalId.value = card.dataset.id || '';
        modalNome.value = card.dataset.nome || '';
        modalDescricao.value = card.dataset.descricao || '';
        modalImagem.value = card.dataset.imagem || '';
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
    preview.innerHTML = '';
        document.getElementById('form-title').textContent = 'Adicionar Serviço';
    formCancel.style.display = 'none';
});

// When clicking an edit button, also populate the top form for quick edit
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        const card = this.closest('.product');
        document.getElementById('form-id').value = card.dataset.id || '';
        document.getElementById('form-nome').value = card.dataset.nome || '';
        document.getElementById('form-descricao').value = card.dataset.descricao || '';
        document.getElementById('form-imagem').value = card.dataset.imagem || '';
        renderPreview(preview, parseImages(card.dataset.imagem || ''));
        formAction.value = 'update';
        formCancel.style.display = 'inline-block';
        document.getElementById('form-title').textContent = 'Editar Serviço';
    });
});
</script>
</body>
</html>
