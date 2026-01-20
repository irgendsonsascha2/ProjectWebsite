<?php
$projectId = $_GET['id'] ?? null;

if (!$projectId) {
    echo "Projekt nicht gefunden.";
    return;
}

$collection = $db->projects;
// Wir suchen das Projekt anhand der MongoDB ID
$project = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($projectId)]);

if (!$project) {
    echo "Projekt existiert nicht.";
    return;
}
?>

<article class="project-detail">
    <a href="index.php?page=home" class="back-link">← Zurück zur Übersicht</a>

    <h1><?php echo htmlspecialchars($project['title']); ?></h1>
    <p class="description"><?php echo htmlspecialchars($project['description']); ?></p>

    <div class="gallery-grid">
        <?php foreach ($project['gallery'] as $item): ?>
            <div class="gallery-item">
                <?php if ($item['type'] === 'image'): ?>
                    <img src="<?php echo $item['url']; ?>" alt="<?php echo htmlspecialchars($item['caption']); ?>">
                <?php elseif ($item['type'] === 'video'): ?>
                    <div class="video-container">
                        <iframe src="<?php echo $item['url']; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                <?php endif; ?>
                <p class="caption"><?php echo htmlspecialchars($item['caption']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</article>