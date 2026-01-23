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
            <a href="index.php?page=project_detail&id=<?php echo (string)$project['_id']; ?>" class="project-card">
                <img src="<?php echo htmlspecialchars($project['image'] ?? 'img/placeholder.jpg'); ?>" alt="Vorschau" class="thumbnail">
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
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (can('create_project')): ?>
<a href="index.php?page=create_project" class="fab" title="Neues Projekt erstellen">+</a>
<?php endif; ?>