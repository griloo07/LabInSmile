<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function ensure_avaliacoes_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        classificacao TINYINT NOT NULL,
        observacoes TEXT,
        ip VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_avaliacoes_table($conn);

$errors = [];
$success = '';
$nome = '';
$classificacao = 0;
$observacoes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Token CSRF inválido.';
    } else {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $classificacao = intval($_POST['classificacao'] ?? 0);
        $observacoes = trim((string)($_POST['observacoes'] ?? ''));

        if ($nome === '') $errors[] = 'Indique o seu nome.';
        if ($classificacao < 1 || $classificacao > 5) $errors[] = 'Escolha uma classificação entre 1 e 5 estrelas.';

        if (empty($errors)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $conn->prepare('INSERT INTO avaliacoes (nome, classificacao, observacoes, ip) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('siss', $nome, $classificacao, $observacoes, $ip);
            if ($stmt->execute()) {
                $success = 'Obrigado pela sua avaliação!';
                // reset form values
                $nome = '';
                $classificacao = 0;
                $observacoes = '';
            } else {
                $errors[] = 'Erro ao guardar a avaliação: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Deixe uma Avaliação — LabInSmile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .review-page { padding: 36px 16px; }
        .review-card { max-width:760px; margin: 18px auto; background:var(--surface); padding:22px; border-radius:12px; box-shadow:var(--shadow-sm); }
        .stars { display:flex; gap:6px; align-items:center }
        .star { font-size:30px; background:transparent; border:0; cursor:pointer; color:#e6eaf0; padding:6px; border-radius:8px; line-height:1 }
        .star.selected { color:#f59e0b; transform:translateY(-2px); text-shadow: 0 1px 0 rgba(255,255,255,0.75), 0 6px 18px rgba(0,0,0,0.12); }
        .star:focus { outline:2px solid rgba(11,110,79,0.18); }
        .form-row { margin-bottom:12px }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="review-page">
    <div class="review-card">
        <h2>Deixe a sua avaliação</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form id="review-form" method="POST" class="contact-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-row">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" type="text" required value="<?= htmlspecialchars($nome) ?>">
            </div>

            <div class="form-row">
                <label>Classificação</label>
                <input type="hidden" name="classificacao" id="classificacao" value="<?= htmlspecialchars($classificacao) ?>">
                <div class="stars" id="stars" role="radiogroup" aria-label="Classificação por estrelas">
                    <button type="button" class="star" data-value="1" aria-label="1 estrela">★</button>
                    <button type="button" class="star" data-value="2" aria-label="2 estrelas">★</button>
                    <button type="button" class="star" data-value="3" aria-label="3 estrelas">★</button>
                    <button type="button" class="star" data-value="4" aria-label="4 estrelas">★</button>
                    <button type="button" class="star" data-value="5" aria-label="5 estrelas">★</button>
                </div>
            </div>

            <div class="form-row">
                <label for="observacoes">Observações <small>(opcional)</small></label>
                <textarea id="observacoes" name="observacoes" rows="5"><?= htmlspecialchars($observacoes) ?></textarea>
            </div>

            <div>
                <button type="submit">Enviar avaliação</button>
            </div>
        </form>
    </div>
</main>

<script>
(() => {
    const stars = Array.from(document.querySelectorAll('#stars .star'));
    const input = document.getElementById('classificacao');

    function setRating(v){
        input.value = v;
        stars.forEach(s => {
            const val = parseInt(s.dataset.value,10);
            s.classList.toggle('selected', val <= v);
        });
    }

    stars.forEach(s => {
        s.addEventListener('click', () => setRating(parseInt(s.dataset.value,10)));
        s.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setRating(parseInt(s.dataset.value,10)); }
        });
    });

    document.getElementById('review-form').addEventListener('submit', function(e){
        if (!input.value || parseInt(input.value,10) < 1) {
            e.preventDefault(); alert('Por favor indique a classificação (1 a 5 estrelas).');
            return false;
        }
    });
})();
</script>

</body>
</html>
