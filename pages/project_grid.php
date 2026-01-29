<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- HILFSFUNKTION FÜR RECHTE ---
if (!function_exists('can')) {
    function can($permission) {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}
if (!function_exists('can_edit_project')) {
    function can_edit_project($project) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (can('edit_all')) {
            return true;
        }
        if (can('edit_own') && isset($project['author_id'])) {
            return (string)$project['author_id'] === $_SESSION['user_id'];
        }
        return false;
    }
}

// HYBRIDE VERBINDUNG: Prüfen ob Master bereits $db bereitgestellt hat
if (!isset($db)) {
    require_once 'vendor/autoload.php';
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $db = $client->portfolio_db;
}

$projectsCursor = $db->projects->find([], ['sort' => ['created_at' => -1]]);
?>

<head>
    <link rel="stylesheet" href="style/project_grid.css">
</head>

<div class="project-grid">
    <?php 
    $projects = iterator_to_array($projectsCursor);
    if (empty($projects)): 
    ?>
        <p>Keine Projekte gefunden.</p>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <?php
                $detailUrl = "index.php?page=project_detail&id=" . (string)$project['_id'];
                $thumb = null;
                $thumbType = 'image';
                if (!empty($project['gallery']) && is_array($project['gallery'])) {
                    foreach ($project['gallery'] as $item) {
                        if (!empty($item['url'])) {
                            $thumb = $item['url'];
                            $thumbType = $item['type'] ?? 'image';
                            break;
                        }
                    }
                }
                if (!$thumb) {
                    $thumb = 'img/placeholder.svg';
                    $thumbType = 'image';
                }
                $canEditProject = can_edit_project($project);
            ?>
            <article class="project-card">
                <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="project-card-link">
                    <?php if ($thumbType === 'video'): ?>
                        <video class="thumbnail" src="<?php echo htmlspecialchars($thumb); ?>" muted playsinline preload="metadata"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Vorschau" class="thumbnail">
                    <?php endif; ?>
                    <div class="content">
                        <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($project['description'], 0, 100)) . '...'; ?></p>
                        <span class="date">
                            <?php 
                                if ($project['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                                    echo $project['created_at']->toDateTime()->format('d.m.Y');
                                } else {
                                    echo "Datum unbekannt";
                                }
                            ?>
                        </span>
                    </div>
                </a>
                <?php if ($canEditProject): ?>
                    <a href="index.php?page=edit_project&id=<?php echo (string)$project['_id']; ?>" class="project-edit" title="Projekt bearbeiten">✎</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (can('create_project')): ?>
<a href="index.php?page=create_project" class="fab" title="Neues Projekt erstellen">+</a>
<?php endif; ?>
