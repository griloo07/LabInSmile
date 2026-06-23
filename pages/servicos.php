<?php
session_start();
require_once __DIR__ . '/../config.php';

// Verificar login utilizador
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'servicos.php';
    header('Location: login.php');
    exit;
}

// Obter imagens serviço
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

// Configurar tabelas tags
function ensure_service_tag_tables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS service_tags (
        service_id INT NOT NULL,
        tag_id INT NOT NULL,
        PRIMARY KEY (service_id, tag_id),
        CONSTRAINT fk_service_tags_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        CONSTRAINT fk_service_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Obter todas tags
function get_all_tags($conn) {
    $tags = [];
    $res = $conn->query("SELECT id, nome, slug FROM tags ORDER BY nome ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tags[] = $row;
        }
    }
    return $tags;
}

// Mapear tags serviços
function get_service_tags_map($conn) {
    $map = [];
    $sql = "SELECT st.service_id, t.id, t.nome, t.slug FROM service_tags st INNER JOIN tags t ON t.id = st.tag_id ORDER BY t.nome ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $serviceId = (int)$row['service_id'];
            if (!isset($map[$serviceId])) $map[$serviceId] = [];
            $map[$serviceId][] = ['id' => (int)$row['id'], 'nome' => $row['nome'], 'slug' => $row['slug']];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços - Lab in Smile</title>
    <?php require_once __DIR__ . '/../includes/site_head.php'; ?>
    <style>
        .services-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .services-header-row h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .services-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-services-box {
            position: relative;
            width: 240px;
        }

        .search-services-box input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            outline: none;
            transition: var(--transition-fast);
        }

        .search-services-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.08);
        }

        .search-icon-inside {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            color: var(--muted);
        }

        .btn-filter-toggle {
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 9px 16px;
            border-radius: var(--radius-sm);
            color: var(--text);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-fast);
        }

        .btn-filter-toggle:hover, .btn-filter-toggle.active {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .btn-filter-toggle svg {
            width: 16px;
            height: 16px;
        }

        /* FILTERS PANE */
        .filters-container-pane {
            display: none;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
            animation: paneSlide 0.25s ease-out;
        }

        .filters-container-pane.open {
            display: block;
        }

        @keyframes paneSlide {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .filters-pane-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin: 0 0 12px;
        }

        .chips-list-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag-filter-chip {
            background: var(--bg);
            border: 1px solid var(--border-color);
            color: var(--text);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .tag-filter-chip:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary);
        }

        .tag-filter-chip.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(11, 110, 79, 0.15);
        }

        /* SERVICES GRID */
        .services-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .service-gallery-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: var(--transition-normal);
        }

        .service-gallery-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(11, 110, 79, 0.15);
        }

        .service-card-media {
            height: 200px;
            background: #f1f5f9;
            overflow: hidden;
        }

        .service-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: var(--transition-normal);
        }

        .service-gallery-card:hover .service-card-media img {
            transform: scale(1.04);
        }

        .service-card-content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .service-card-content h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            line-height: 1.3;
        }

        .service-card-content p {
            font-size: 0.85rem;
            color: var(--muted);
            line-height: 1.5;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .service-card-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: auto;
            padding-top: 10px;
        }

        .service-tag-badge {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        .empty-services-alert {
            display: none;
            grid-column: 1 / -1;
            background: #ffffff;
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 60px 20px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 768px) {
            .services-header-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .services-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-services-box {
                width: 100%;
            }

            .btn-filter-toggle {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/site_header.php'; ?>

<main class="container">
    <div class="services-header-row">
        <div>
            <h1>Os Nossos Serviços</h1>
        </div>
        <div class="services-controls">
            <div class="search-services-box">
                <svg class="search-icon-inside" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="search" id="services-search" placeholder="Procurar serviço...">
            </div>
            <button type="button" class="btn-filter-toggle" id="btn-filters-toggle">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                <span>Filtros</span>
            </button>
        </div>
    </div>

    <!-- FILTERS PANE -->
    <div class="filters-container-pane" id="filters-pane">
        <p class="filters-pane-title">Filtrar por Categoria</p>
        <div class="chips-list-row">
            <button type="button" class="tag-filter-chip active" data-tag-id="all">Todas</button>
            <?php foreach ($all_tags as $tag): ?>
                <button type="button" class="tag-filter-chip" data-tag-id="<?= intval($tag['id']) ?>"><?= htmlspecialchars($tag['nome']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SERVICES GALLERY -->
    <div class="services-gallery-grid" id="services-list-container">
        <?php
        $sql = "SELECT * FROM services ORDER BY id ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $images = service_images($row['imagem'] ?? '');
                $row_tags = $service_tags_map[(int)$row['id']] ?? [];
                $tag_ids = implode(',', array_column($row_tags, 'id'));
                $tag_names = implode(' ', array_column($row_tags, 'nome'));
        ?>
            <a href="servico.php?id=<?= $row['id'] ?>" class="service-gallery-card" data-title="<?= htmlspecialchars(strtolower($row['nome']), ENT_QUOTES) ?>" data-description="<?= htmlspecialchars(strtolower($row['descricao'] ?? ''), ENT_QUOTES) ?>" data-tags="<?= htmlspecialchars($tag_ids, ENT_QUOTES) ?>">
                <div class="service-card-media">
                    <?php if (!empty($images)): ?>
                        <img src="/LabInSmile/images/<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($row['nome']) ?>" loading="lazy">
                    <?php else: ?>
                        <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; background:#f8fafc;">
                            <svg style="width:32px; height:32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="service-card-content">
                    <h3><?= htmlspecialchars($row['nome']) ?></h3>
                    <?php if (trim($row['descricao'] ?? '') !== ''): ?>
                        <p><?= htmlspecialchars(strip_tags($row['descricao'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($row_tags)): ?>
                        <div class="service-card-tags-list">
                            <?php foreach ($row_tags as $rt): ?>
                                <span class="service-tag-badge"><?= htmlspecialchars($rt['nome']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        <?php
            endwhile;
        else:
        ?>
            <div class="empty-services-alert" style="display: block;">
                <p>Nenhum serviço registado de momento.</p>
            </div>
        <?php endif; ?>
        
        <div class="empty-services-alert" id="empty-filtered-state">
            <p>Nenhum serviço corresponde à sua pesquisa.</p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/site_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('btn-filters-toggle');
    const pane = document.getElementById('filters-pane');
    const searchInput = document.getElementById('services-search');
    const chips = document.querySelectorAll('.tag-filter-chip');
    const cards = document.querySelectorAll('.service-gallery-card');
    const emptyState = document.getElementById('empty-filtered-state');
    
    let activeTag = 'all';
    let searchQuery = '';

    if (toggleBtn && pane) {
        toggleBtn.addEventListener('click', () => {
            toggleBtn.classList.toggle('active');
            pane.classList.toggle('open');
        });
    }

    function applyFilters() {
        let visibleCount = 0;

        cards.forEach(card => {
            const cardTags = (card.dataset.tags || '').split(',').filter(Boolean);
            const title = card.dataset.title || '';
            const desc = card.dataset.description || '';

            const matchesTag = activeTag === 'all' || cardTags.includes(activeTag);
            const matchesSearch = title.includes(searchQuery) || desc.includes(searchQuery);

            if (matchesTag && matchesSearch) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (emptyState) {
            emptyState.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
        }
    }

    // Search filter
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            searchQuery = this.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // Tag filter
    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            chips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            activeTag = this.dataset.tagId;
            applyFilters();
        });
    });
});
</script>
</body>
</html>
