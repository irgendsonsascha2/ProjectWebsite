<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$canDeleteProjects = can('delete_all');

use MongoDB\BSON\Regex;

function delete_project_files($project) {
    $gallery = normalize_gallery($project['gallery'] ?? []);
    foreach ($gallery as $item) {
        $url = $item['url'] ?? '';
        if (strpos($url, 'content/images/') === 0 || strpos($url, 'content/videos/') === 0) {
            $path = __DIR__ . '/../' . $url;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

if ($canDeleteProjects && isset($_POST['delete_projects']) && isset($_POST['project_ids']) && is_array($_POST['project_ids'])) {
    $ids = array_values(array_filter($_POST['project_ids'], function ($id) {
        return is_string($id) && $id !== '';
    }));
    if (!empty($ids)) {
        $objectIds = [];
        foreach ($ids as $id) {
            try {
                $objectIds[] = new \MongoDB\BSON\ObjectId($id);
            } catch (Exception $e) {
                continue;
            }
        }
        if (!empty($objectIds)) {
            $projectsToDelete = iterator_to_array($db->projects->find(['_id' => ['$in' => $objectIds]]));
            foreach ($projectsToDelete as $project) {
                delete_project_files($project);
            }
            $db->projects->deleteMany(['_id' => ['$in' => $objectIds]]);
            $db->likes->deleteMany(['project_id' => ['$in' => $objectIds]]);
            $db->comments->deleteMany(['project_id' => ['$in' => $objectIds]]);
        }
    }
}

$canViewProjects = can('view_projects');
if ($canViewProjects) {
    $q = '';
    if (isset($_GET['q']) && is_string($_GET['q'])) {
        $q = trim($_GET['q']);
    }

    $filter = ['is_draft' => ['$ne' => true]];
    if ($q !== '') {
        // Search by title OR tags (case-insensitive).
        // In MongoDB, regex against an array field matches any element.
        $escaped = preg_quote($q, '/');
        $rx = new Regex($escaped, 'i');
        $filter['$or'] = [
            ['title' => $rx],
            ['tags' => $rx],
        ];
    }

    $projectsCursor = $db->projects->find(
        $filter,
        ['sort' => ['created_at' => -1]]
    );
} else {
    $projectsCursor = [];
}
?>

<form method="POST" action="index.php?page=project_grid" id="grid-delete-form">
<div class="project-grid">
    <?php 
    $projects = is_array($projectsCursor) ? $projectsCursor : iterator_to_array($projectsCursor);
    if (!$canViewProjects): 
    ?>
        <p>Du hast keine Berechtigung, Projekte anzusehen.</p>
    <?php elseif (empty($projects)): 
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
            <article class="project-card" data-skeleton-card>
                <?php if ($canDeleteProjects): ?>
                    <label class="project-select">
                        <input type="checkbox" name="project_ids[]" value="<?php echo (string)$project['_id']; ?>" aria-label="Projekt zum Löschen markieren">
                        <span class="project-check" aria-hidden="true"><?php echo svg_icon_check(18); ?></span>
                    </label>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="project-card-link">
                    <div class="thumb-wrap skeleton-host" data-skeleton-media>
                        <span class="skeleton-panel" aria-hidden="true"></span>
                        <?php if ($thumbType === 'video'): ?>
                            <video class="thumbnail" src="<?php echo htmlspecialchars($thumb); ?>" muted playsinline preload="metadata"></video>
                            <span class="thumb-play">▶</span>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Vorschau" class="thumbnail" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="content project-card-copy">
                        <div class="skeleton-text-block" aria-hidden="true">
                            <span class="skeleton-line skeleton-line--title"></span>
                            <span class="skeleton-line skeleton-line--md"></span>
                            <span class="skeleton-line skeleton-line--short"></span>
                            <span class="skeleton-line skeleton-line--date"></span>
                        </div>
                        <div class="project-card-copy-inner">
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
                    </div>
                </a>
                <?php if ($canEditProject): ?>
                    <a href="index.php?page=edit_project&id=<?php echo (string)$project['_id']; ?>" class="project-edit" title="Projekt bearbeiten"><?php echo svg_icon_pencil(18); ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</form>

<?php if (can('create_project')): ?>
<button type="button" class="fab" title="Neues Projekt erstellen" aria-label="Neues Projekt erstellen" onclick="window.location.href='index.php?page=create_project'">+</button>
<?php endif; ?>

<?php if ($canDeleteProjects): ?>
<button type="submit" form="grid-delete-form" name="delete_projects" value="1" class="fab fab-delete" title="Markierte Projekte löschen" aria-label="Markierte Projekte löschen" disabled>
    <?php echo svg_icon_trash(22); ?>
</button>
<?php endif; ?>

<?php if ($canDeleteProjects): ?>
<script>
    (function () {
        const form = document.getElementById('grid-delete-form');
        if (!form) return;
        const deleteButton = document.querySelector('.fab-delete');
        const checkboxes = Array.from(form.querySelectorAll('input[type="checkbox"][name="project_ids[]"]'));
        if (!deleteButton || checkboxes.length === 0) return;

        const grid = document.querySelector('.project-grid');

        function updateDeleteButton() {
            const anyChecked = checkboxes.some((cb) => cb.checked);
            deleteButton.classList.toggle('is-active', anyChecked);
            deleteButton.disabled = !anyChecked;
            if (grid) {
                grid.classList.toggle('has-selection', anyChecked);
            }
            checkboxes.forEach((cb) => {
                const label = cb.closest('.project-select');
                if (label) {
                    label.classList.toggle('is-checked', cb.checked);
                }
            });
        }

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input[type="checkbox"][name="project_ids[]"]')) {
                updateDeleteButton();
            }
        });

        form.addEventListener('submit', function (event) {
            if (event.submitter !== deleteButton) {
                return;
            }
            const anyChecked = checkboxes.some((cb) => cb.checked);
            if (!anyChecked) {
                event.preventDefault();
                return;
            }
            const ok = window.confirm('Ausgewählte Projekte wirklich löschen?');
            if (!ok) {
                event.preventDefault();
            }
        });

        updateDeleteButton();
    })();
</script>
<?php endif; ?>

<script>
    (function () {
        const cards = Array.from(document.querySelectorAll('.project-card'));
        if (cards.length === 0) return;

        cards.forEach((card) => {
            let rafId = 0;
            let lastEvent = null;

            function applyTilt() {
                rafId = 0;
                if (!lastEvent) return;
                const rect = card.getBoundingClientRect();
                const x = Math.min(Math.max((lastEvent.clientX - rect.left) / rect.width, 0), 1);
                const y = Math.min(Math.max((lastEvent.clientY - rect.top) / rect.height, 0), 1);
                const rx = (0.5 - y) * 10;
                const ry = (x - 0.5) * 12;

                card.style.setProperty('--rx', `${rx}deg`);
                card.style.setProperty('--ry', `${ry}deg`);
                card.style.setProperty('--mx', `${x * 100}%`);
                card.style.setProperty('--my', `${y * 100}%`);
                card.classList.add('is-tilting');
            }

            card.addEventListener('pointermove', (event) => {
                if (event.pointerType === 'touch') return;
                lastEvent = event;
                if (!rafId) {
                    rafId = window.requestAnimationFrame(applyTilt);
                }
            });

            card.addEventListener('pointerleave', () => {
                if (rafId) {
                    window.cancelAnimationFrame(rafId);
                    rafId = 0;
                }
                lastEvent = null;
                card.classList.remove('is-tilting');
                card.style.removeProperty('--rx');
                card.style.removeProperty('--ry');
                card.style.removeProperty('--mx');
                card.style.removeProperty('--my');
            });
        });
    })();
</script>
