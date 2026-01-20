<?php
require_once 'vendor/autoload.php'; // Sicherstellen, dass der Treiber geladen ist
$client = new MongoDB\Client("mongodb://localhost:27017");
$collection = $client->portfolio_db->projects;

// Abfrage mit typeMap (wichtig für den Zugriff als Array!)
$projects = $collection->find([], [
    'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
]);
?>

<div class="project-grid">
    <?php if (empty($projects)): ?>
        <p>Keine Projekte gefunden.</p>
    <?php else: ?>
        <?php foreach ($projects as $project): ?>
            <a href="index.php?page=detail&id=<?php echo (string)$project['_id']; ?>" class="project-card">
                <img src="<?php echo htmlspecialchars($project['thumbnail']); ?>" alt="Vorschau" class="thumbnail">
                <div class="content">
                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                    <span class="date">
                        <?php 
                            // Falls created_at ein MongoDB\BSON\UTCDateTime Objekt ist
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