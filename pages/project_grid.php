<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$projectsCursor = $db->projects->find(
    ['is_draft' => ['$ne' => true]],
    ['sort' => ['created_at' => -1]]
);
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
                $gallery = normalize_gallery($project['gallery'] ?? []);
                if (!empty($gallery)) {
                    foreach ($gallery as $item) {
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
                    <div class="thumb-wrap">
                        <?php if ($thumbType === 'video'): ?>
                            <video class="thumbnail" src="<?php echo htmlspecialchars($thumb); ?>" muted playsinline preload="metadata"></video>
                            <span class="thumb-play">▶</span>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Vorschau" class="thumbnail" loading="lazy">
                        <?php endif; ?>
                    </div>
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
