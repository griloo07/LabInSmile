<?php
session_start();
require_once __DIR__ . '/../config.php';

// Gerar token CSRF
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
                $success = 'Muito obrigado pela sua avaliação! A sua opinião é fundamental.';
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
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deixar Avaliação - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .review-form-container {
            padding: 60px 0;
            display: flex;
            justify-content: center;
        }

        .review-card-pane {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .review-card-pane h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            text-align: center;
        }

        .rating-stars-picker {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            padding: 10px 0;
        }

        .star-pick-btn {
            font-size: 2.25rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #e2e8f0;
            padding: 4px;
            outline: none;
            transition: var(--transition-fast);
            line-height: 1;
        }

        .star-pick-btn:hover {
            transform: scale(1.15);
        }

        .star-pick-btn.active {
            color: #f59e0b;
            text-shadow: 0 2px 10px rgba(245, 158, 11, 0.2);
            transform: translateY(-2px);
        }

        .form-fields-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-field-unit {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-field-unit label {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-main);
        }

        .form-field-unit input,
        .form-field-unit textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 11px 12px;
            font-size: 0.9rem;
            background: var(--bg);
            color: var(--text-main);
            outline: none;
            transition: var(--transition-fast);
        }

        .form-field-unit input:focus,
        .form-field-unit textarea:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .form-field-unit textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit-review {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 110, 79, 0.15);
            transition: var(--transition-fast);
            margin-top: 10px;
        }

        .btn-submit-review:hover {
            background: var(--primary-700);
            transform: translateY(-1px);
        }

        .review-alert-banner {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .review-alert-banner.success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #047857;
        }

        .review-alert-banner.error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .review-alert-banner ul {
            margin: 0;
            padding-left: 18px;
        }

        .btn-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition-fast);
            margin-top: 10px;
        }

        .btn-back-btn:hover {
            color: var(--text-main);
        }

        @media (max-width: 480px) {
            .review-card-pane {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="container review-form-container">
    <div class="review-card-pane">
        <h2>A sua opinião importa!</h2>

        <?php if (!empty($errors)): ?>
            <div class="review-alert-banner error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="review-alert-banner success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form id="review-submission-form" method="POST" class="form-fields-stack">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="classificacao" id="review-rating-value" value="<?= htmlspecialchars($classificacao) ?>">

            <div class="form-field-unit">
                <label for="nome">Nome Completo</label>
                <input id="nome" name="nome" type="text" placeholder="Ex: Gabriel Silva" required value="<?= htmlspecialchars($nome) ?>">
            </div>

            <div class="form-field-unit">
                <label>Classificação</label>
                <div class="rating-stars-picker" id="stars-row-box" role="radiogroup" aria-label="Classificação por estrelas">
                    <button type="button" class="star-pick-btn" data-value="1" aria-label="1 estrela">&#9733;</button>
                    <button type="button" class="star-pick-btn" data-value="2" aria-label="2 estrelas">&#9733;</button>
                    <button type="button" class="star-pick-btn" data-value="3" aria-label="3 estrelas">&#9733;</button>
                    <button type="button" class="star-pick-btn" data-value="4" aria-label="4 estrelas">&#9733;</button>
                    <button type="button" class="star-pick-btn" data-value="5" aria-label="5 estrelas">&#9733;</button>
                </div>
            </div>

            <div class="form-field-unit">
                <label for="observacoes">Observações / Comentários <small style="color: var(--text-muted); font-weight: normal;">(opcional)</small></label>
                <textarea id="observacoes" name="observacoes" placeholder="Conte-nos como foi a sua experiência com os nossos serviços..."><?= htmlspecialchars($observacoes) ?></textarea>
            </div>

            <button type="submit" class="btn-submit-review">Enviar Avaliação</button>
            
            <div style="text-align: center;">
                <a href="home.php" class="btn-back-btn">
                    <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    <span>Voltar ao website</span>
                </a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
(() => {
    const stars = Array.from(document.querySelectorAll('#stars-row-box .star-pick-btn'));
    const input = document.getElementById('review-rating-value');

    function updateStarsDisplay(value) {
        input.value = value;
        stars.forEach(s => {
            const v = parseInt(s.dataset.value, 10);
            s.classList.toggle('active', v <= value);
        });
    }

    stars.forEach(s => {
        const val = parseInt(s.dataset.value, 10);
        s.addEventListener('click', () => updateStarsDisplay(val));
        s.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { 
                e.preventDefault(); 
                updateStarsDisplay(val); 
            }
        });
    });

    const form = document.getElementById('review-submission-form');
    form.addEventListener('submit', function(e) {
        const val = parseInt(input.value, 10);
        if (!val || val < 1 || val > 5) {
            e.preventDefault();
            alert('Por favor, escolha uma classificação clicando nas estrelas (1 a 5).');
            return false;
        }
    });

    // Run once on load to populate old value if exists
    if (input.value) {
        updateStarsDisplay(parseInt(input.value, 10));
    }
})();
</script>
</body>
</html>
