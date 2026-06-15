<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'servicos.php';
    header('Location: login.php');
    exit;
}

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
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

    <script>
            $serviceId = (int)$row['service_id'];
            if (!isset($map[$serviceId])) {
                $map[$serviceId] = [];
            }
            $map[$serviceId][] = ['id' => (int)$row['id'], 'nome' => $row['nome']];
        }
    }
    return $map;
}

ensure_service_tag_tables($conn);
$all_tags = get_all_tags($conn);
$service_tags_map = get_service_tags_map($conn);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>Serviços - LabInSmile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        * { box-sizing: border-box; }
        nav {
            display: flex;
            gap: 5px;
        }
        nav a {
            text-decoration: none;
            color: #0b6e4f;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        nav a:hover {
            background: #eef2f5;
            color: #0b6e4f;
        }
        /* Auth button styles moved to global style.css for consistent subtle design */

        /* Page-specific tweaks: slightly larger lab name and button text, ensure vertical alignment */
        header .logo {
            display: inline-flex;
            align-items: center;
            font-weight: 700;
            font-size: 20px;
            color: #0b6e4f;
            cursor: pointer;
            line-height: 1;
            height: 48px;
            padding: 0 6px;
        }

        /* Slightly larger auth buttons on this page to match request (use global styles) */

          /* Ensure nav and controls align horizontally with logo */
          .topbar { align-items: center; }

          /* Force the right-side container in header to align contents vertically
              target the immediate div following the logo anchor */
          .topbar > div { display: flex; align-items: center; gap: 20px; margin-left: auto; width: auto !important; }
        main {
            min-height: calc(100vh - 280px);
            padding: 40px 15px;
        }
        main h1 {
            color: #0b6e4f;
            margin-bottom: 30px;
        }
        .services-heading {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:18px;
        }
        .services-heading h1 { margin:0; }
        .filter-toggle {
            border:1px solid #cfe7de;
            background:#fff;
            color:#0b6e4f;
            border-radius:8px;
            cursor:pointer;
            font-weight:700;
            padding:9px 13px;
        }
        .filters-panel {
            display:none;
            background:#fff;
            border:1px solid #e5eee9;
            border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,0.06);
            margin:0 0 22px;
            padding:14px;
        }
        .filters-panel.is-open { display:block; }
        .filters-title {
            color:#0b6e4f;
            font-size:15px;
            font-weight:800;
            margin:0 0 10px;
        }
        .filter-tags, .service-tags {
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }
        .filter-chip {
            border:1px solid #d7eee5;
            background:#f0fdf7;
            color:#065f46;
            border-radius:999px;
            cursor:pointer;
            font-size:13px;
            font-weight:700;
            padding:7px 10px;
        }
        .filter-chip[data-tag-id="all"] {
            background:#fff;
            border-color:#cfe7de;
        }
        .filter-chip.is-active {
            background:#0b6e4f;
            color:#fff;
            border-color:#0b6e4f;
        }
        .no-services-filtered {
            display:none;
            color:#6b7280;
            margin:20px 0 40px;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 220px));
            justify-content: start;
            gap: 18px;
            margin-bottom: 40px;
        }
        .service-card {
            background: white;
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .service-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-4px);
        }
        .service-card h3 {
            color: #0b6e4f;
            font-size: 15px;
            line-height: 1.3;
            margin: 10px 0 8px;
            text-align: center;
        }
        .service-tags { justify-content:center; }
        .service-tag {
            background:#eef8f4;
            border-radius:999px;
            color:#0b6e4f;
            font-size:11px;
            font-weight:700;
            line-height:1.2;
            padding:4px 7px;
        }
        .service-card img {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            border-radius: 8px;
        }
        footer {
            background: #111;
            color: #fff;
            padding: 40px 15px 20px;
            text-align: center;
            font-size: 14px;
        }
        footer p {
            margin: 8px 0;
        }
        @media (max-width: 600px) {
            nav { order: 3; width: 100%; margin-top: 10px; }
            nav a { flex: 1; text-align: center; }
            .services-heading { align-items:flex-start; flex-direction:column; }
        }
    </style>
<?php /* admin link moved into the header nav (shown only to admins) */ ?>

</head>
<body>

<header>
    <div class="container">
        <div class="topbar">
            <a href="home.php" class="logo" style="text-decoration: none; color: #0b6e4f; display:inline-flex; align-items:center; gap:8px;"> 
                <img src="../images/logo_labinsmile.png" alt="LabInSmile" style="height:30px; width:auto; border-radius:8px; object-fit:cover"> LabInSmile
            </a>

            <div class="top-right">
                
                <nav>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin.php" class="btn-gestao" style="color: #0b6e4f; font-weight: bold;">Gestão</a>
                    <?php endif; ?>
                    <a href="servicos.php" style="color: #0b6e4f; font-weight: bold;">Serviços</a>
                    <a href="especialidades.php">Especialidades</a>
                    <a href="contacto.php">Contacto</a>
                </nav>

                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span style="font-size: 14px; color: #6b7280;">
                            Olá, <?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                        </span>
                        <a href="logout.php" class="btn-login">Sair</a>
                    <?php else: ?>
                        <a href="login.php?next=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn-login">Login</a>
                        <a href="registo.php" class="btn-login">Registar</a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <div class="services-heading">
            <h1>Nossos Serviços</h1>
            <button type="button" id="filter-toggle" class="filter-toggle" aria-expanded="false" aria-controls="filters-panel">Filtros</button>
        </div>

        <div id="filters-panel" class="filters-panel">
            <p class="filters-title">Categorias</p>
            <div class="filter-tags" id="filter-tags">
                <button type="button" class="filter-chip is-active" data-tag-id="all">Todas</button>
                <?php foreach ($all_tags as $tag): ?>
                    <button type="button" class="filter-chip" data-tag-id="<?= intval($tag['id']) ?>"><?= htmlspecialchars($tag['nome']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="services-grid">

        <?php
        $sql = "SELECT * FROM services ORDER BY id ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {

            while ($row = $result->fetch_assoc()) {
                $images = service_images($row['imagem'] ?? '');
                $row_tags = $service_tags_map[(int)$row['id']] ?? [];
                $tag_ids = implode(',', array_column($row_tags, 'id'));
                $tag_names = implode(' ', array_column($row_tags, 'nome'));
        ?>

            <a href="servico.php?id=<?= $row['id'] ?>" class="service-link" data-title="<?= htmlspecialchars($row['nome'], ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($row['descricao'] ?? '', ENT_QUOTES) ?>" data-tags="<?= htmlspecialchars($tag_ids, ENT_QUOTES) ?>" data-tag-names="<?= htmlspecialchars($tag_names, ENT_QUOTES) ?>" style="text-decoration:none; color:inherit;">
    <div class="service-card">

        <?php if (!empty($images)): ?>
            <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['nome']) ?>">
        <?php endif; ?>
        <h3><?= htmlspecialchars($row['nome']) ?></h3>
        <!-- service tags are kept in data attributes for filtering but not displayed here -->
    </div>
</a>

        <?php
            }

        } else {
            echo "<p>Sem serviços disponíveis.</p>";
        }
        ?>

        </div>
        <p id="no-services-filtered" class="no-services-filtered">Nenhum servi&ccedil;o corresponde aos filtros escolhidos.</p>
    </div>
</main>

<footer>
    <div class="container">
        <strong>LabInSmile - Próteses Dentárias</strong>
        <p>Telefone: +351 967 544 606</p>
        <p>Email: labinsmile@gmail.com</p>
        <p>Morada: Avenida da República, Nº 74 1.º Andar Sala 1 Paredes</p>
    </div>
</footer>

<a
    href="https://wa.me/351967544606?text=Olá,%20gostaria%20de%20obter%20mais%20informações."
    class="whatsapp-float"
    target="_blank"
>

    <img
        src="/LabInSmile/images/whatsapp.png"
        alt="WhatsApp"
    >

</a>
<script>
;(function(){
    const toggle = document.getElementById('filter-toggle');
    const panel = document.getElementById('filters-panel');
    const chips = Array.from(document.querySelectorAll('.filter-chip'));
    const cards = Array.from(document.querySelectorAll('.service-link'));
    const empty = document.getElementById('no-services-filtered');
    const activeTags = new Set();

    function applyFilters() {
        let visibleCount = 0;

        cards.forEach(card => {
            const cardTags = new Set((card.dataset.tags || '').split(',').filter(Boolean));
            const matchesTags = activeTags.size === 0 || Array.from(activeTags).every(tag => cardTags.has(tag));
            const visible = matchesTags;
            card.style.display = visible ? '' : 'none';
            if (visible) visibleCount += 1;
        });

        if (empty) {
            empty.style.display = visibleCount === 0 && cards.length > 0 ? 'block' : 'none';
        }
    }

    toggle && toggle.addEventListener('click', function(){
        const isOpen = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    chips.forEach(chip => {
        chip.addEventListener('click', function(){
            const tagId = this.dataset.tagId;
            const allChip = chips.find(item => item.dataset.tagId === 'all');

            if (tagId === 'all') {
                activeTags.clear();
                chips.forEach(item => item.classList.toggle('is-active', item.dataset.tagId === 'all'));
                applyFilters();
                return;
            }

            if (activeTags.has(tagId)) {
                activeTags.delete(tagId);
                this.classList.remove('is-active');
            } else {
                activeTags.add(tagId);
                this.classList.add('is-active');
            }

            if (allChip) {
                allChip.classList.toggle('is-active', activeTags.size === 0);
            }
            applyFilters();
        });
    });
})();
</script>
</body>
</html>
