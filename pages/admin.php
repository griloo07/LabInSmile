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
            $imagem = trim($_POST['imagem'] ?? '');

            if ($nome && $descricao) {
                $stmt = $conn->prepare("INSERT INTO services (nome, descricao, imagem) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $nome, $descricao, $imagem);
                if ($stmt->execute()) {
                    $mensagem = "Produto adicionado com sucesso!";
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
            $imagem = trim($_POST['imagem'] ?? '');

            if ($id && $nome && $descricao) {
                $stmt = $conn->prepare("UPDATE services SET nome = ?, descricao = ?, imagem = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $descricao, $imagem, $id);
                if ($stmt->execute()) {
                    $mensagem = "Produto atualizado com sucesso!";
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
                    $mensagem = "Produto eliminado.";
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

// Se for pedido de edição, buscar produto
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
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-wrap { max-width:1100px; margin:20px auto; padding:20px; }
        .card { background:#fff; padding:15px; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,0.05); }
        .product { border:1px solid #eee; padding:10px; margin:10px 0; display:flex; gap:12px; align-items:flex-start; }
        .product img{ width:100px; height:auto; border-radius:6px }
        .actions form{ display:inline-block; margin-left:8px }
        .image-row{ display:flex; gap:10px; align-items:center }
        .muted-small{ font-size:12px; color:#6b7280 }
        .mt-10{ margin-top:10px }
    </style>
</head>
<body>

<div class="admin-wrap">
    <div style="margin-bottom:12px;">
        <a href="/LabInSmile/pages/produtos.php" onclick="if(document.referrer){ history.back(); return false; }" style="text-decoration:none; display:inline-block; padding:8px 12px; background:#0b6e4f; color:#fff; border-radius:6px;">← Voltar</a>
    </div>
    <h1>Painel Admin</h1>

    <?php if ($mensagem): ?>
        <p><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <div class="card">
        <h2 id="form-title"><?= $edit_product ? 'Editar Produto' : 'Adicionar Produto' ?></h2>

        <form id="product-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="form-id" value="">
            <input type="hidden" name="imagem" id="form-imagem" value="<?= htmlspecialchars($edit_product['imagem'] ?? '') ?>">

            <div>
                <label>Nome</label><br>
                <input type="text" name="nome" id="form-nome" required value="<?= htmlspecialchars($edit_product['nome'] ?? '') ?>">
            </div>

            <div>
                <label>Descrição</label><br>
                <textarea name="descricao" id="form-descricao" required><?= htmlspecialchars($edit_product['descricao'] ?? '') ?></textarea>
            </div>

            <div>
                <label>Imagem</label><br>
                <div class="image-row">
                    <div id="preview"><?php if (!empty($edit_product['imagem'])): ?><img src="/LabInSmile/images/<?= htmlspecialchars($edit_product['imagem']) ?>" style="max-width:120px; border-radius:6px"><?php endif; ?></div>
                    <div>
                        <input type="file" id="image-input" accept="image/*">
                        <div class="muted-small">Carrega a imagem e será enviada imediatamente.</div>
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

    <h2 style="margin-top:20px">Produtos existentes</h2>

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
        <?php if (!empty($row['imagem'])): ?>
            <div><img src="/LabInSmile/images/<?= htmlspecialchars($row['imagem']) ?>" alt="<?= htmlspecialchars($row['nome']) ?>" style="max-width:120px; border-radius:6px"></div>
        <?php endif; ?>

        <div class="flex-1">
            <strong><?= htmlspecialchars($row['nome']) ?></strong>
            <p><?= nl2br(htmlspecialchars($row['descricao'])) ?></p>
        </div>

        <div class="actions">
            <a href="#" class="btn-edit">Editar</a>

            <form method="POST" onsubmit="return confirm('Tens a certeza que queres eliminar este produto?');">
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
        echo "<p>Sem produtos.</p>";
    endif;
    ?>

</div>

    </body>
    </html>

    <div id="edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:700px;">
        <h3>Editar Produto</h3>
        <form id="modal-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="modal-id">
            <div>
                <label>Nome</label><br>
                <input type="text" name="nome" id="modal-nome" required>
            </div>
            <div>
                <label>Descrição</label><br>
                <textarea name="descricao" id="modal-descricao" required></textarea>
            </div>
            <div>
                <label>Imagem (ficheiro já carregado)</label><br>
                <div id="modal-preview" class="modal-preview"></div>
                <input type="hidden" name="imagem" id="modal-imagem">
                <input type="file" id="modal-image-input" accept="image/*">
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

// Image input on main form
const imageInput = document.getElementById('image-input');
const preview = document.getElementById('preview');
const formImagem = document.getElementById('form-imagem');
const uploadStatus = document.getElementById('upload-status');

if (imageInput) {
    imageInput.addEventListener('change', async function(e) {
        const file = this.files[0];
        if (!file) return;
        uploadStatus.textContent = 'A carregar...';
        try {
            const json = await uploadFile(file);
            if (json.success) {
                formImagem.value = json.filename;
                preview.innerHTML = '<img src="/LabInSmile/images/' + json.filename + '" style="max-width:120px;border-radius:6px">';
                uploadStatus.textContent = 'Imagem carregada: ' + json.filename;
            } else {
                uploadStatus.textContent = 'Erro: ' + (json.error || 'Falha');
            }
        } catch (err) {
            uploadStatus.textContent = 'Erro ao enviar.';
        }
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
        modalPreview.innerHTML = modalImagem.value ? '<img src="/LabInSmile/images/' + modalImagem.value + '" style="max-width:120px;border-radius:6px">' : '';
        modal.style.display = 'flex';
    });
});

modalClose.addEventListener('click', function(){ modal.style.display='none'; });

// allow uploading new image from modal
if (modalImageInput) {
    modalImageInput.addEventListener('change', async function(){
        const f = this.files[0];
        if (!f) return;
        modalPreview.innerHTML = 'A carregar...';
        const json = await uploadFile(f);
        if (json.success) {
            modalImagem.value = json.filename;
            modalPreview.innerHTML = '<img src="/LabInSmile/images/' + json.filename + '" style="max-width:120px;border-radius:6px">';
        } else {
            modalPreview.innerHTML = 'Erro: ' + (json.error||'Falha');
        }
    });
}

// When modal form submits, post to admin.php via normal POST (will reload)
// When main form is used for create, ensure action is set
const productForm = document.getElementById('product-form');
const formAction = document.getElementById('form-action');
const formCancel = document.getElementById('form-cancel');

productForm.addEventListener('submit', function(){
    // default action in hidden field
});

// If user clicks cancel (when editing), reset the form
formCancel.addEventListener('click', function(){
    formAction.value = 'create';
    document.getElementById('form-id').value = '';
    document.getElementById('form-nome').value = '';
    document.getElementById('form-descricao').value = '';
    document.getElementById('form-imagem').value = '';
    preview.innerHTML = '';
    document.getElementById('form-title').textContent = 'Adicionar Produto';
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
        preview.innerHTML = card.dataset.imagem ? '<img src="/LabInSmile/images/' + card.dataset.imagem + '" style="max-width:120px;border-radius:6px">' : '';
        formAction.value = 'update';
        formCancel.style.display = 'inline-block';
        document.getElementById('form-title').textContent = 'Editar Produto';
    });
});
</script>