<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// --- HILFSFUNKTIONEN ---
if (!function_exists('can')) {
    function can($permission)
    {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}
$isLoggedIn = isset($_SESSION['user_id']);

// --- DATENBANK & PROJEKT LADEN ---
use MongoDB\BSON\ObjectId;

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    die("Projekt nicht gefunden.");
}

$returnTo = 'index.php';
if (!empty($_POST['return_to'])) {
    $candidate = $_POST['return_to'];
    $candidatePath = parse_url($candidate, PHP_URL_PATH);
    $candidateQuery = parse_url($candidate, PHP_URL_QUERY);
    $candidateFile = $candidatePath ? basename($candidatePath) : '';
    if (in_array($candidateFile, ['index.php', ''], true)) {
        $returnTo = 'index.php' . ($candidateQuery ? ('?' . $candidateQuery) : '');
    }
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $refererQuery = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    $refererFile = $refererPath ? basename($refererPath) : '';
    if (in_array($refererFile, ['index.php', ''], true)) {
        $returnTo = 'index.php' . ($refererQuery ? ('?' . $refererQuery) : '');
    }
}

try {
    $projectObjectId = new ObjectId($projectId);
    // Die $db Variable wird von der index.php bereitgestellt
    $project = $db->projects->findOne(['_id' => $projectObjectId]);

    if (!$project) {
        die("Projekt existiert nicht.");
    }
} catch (Exception $e) {
    die("Ungültige Projekt-ID.");
}

// --- BERECHTIGUNGS-CHECK ---
$canEdit = false;
if ($isLoggedIn) {
    if (can('edit_all')) {
        $canEdit = true;
    } elseif (can('edit_own') && isset($project['author_id']) && (string)$project['author_id'] === $_SESSION['user_id']) {
        $canEdit = true;
    }
}
if (!$canEdit) {
    die("<h1>Zugriff verweigert</h1><p>Sie haben nicht die nötigen Rechte, um dieses Projekt zu bearbeiten.</p>");
}

$message = "";
$contentBaseDir = __DIR__ . '/../content';
$contentImageDir = $contentBaseDir . '/images';
$contentVideoDir = $contentBaseDir . '/videos';
$contentTmpDir = $contentBaseDir . '/tmp';

if (!is_dir($contentImageDir)) {
    mkdir($contentImageDir, 0755, true);
}
if (!is_dir($contentVideoDir)) {
    mkdir($contentVideoDir, 0755, true);
}

function media_target_dir($type, $imageDir, $videoDir) {
    return $type === 'video' ? $videoDir : $imageDir;
}

function media_temp_dir($type, $baseTmpDir, $userId, $projectId) {
    $subdir = $type === 'video' ? 'videos' : 'images';
    return $baseTmpDir . '/' . $userId . '/' . $projectId . '/' . $subdir;
}

function build_temp_url($type, $userId, $projectId, $filename) {
    $subdir = $type === 'video' ? 'videos' : 'images';
    return 'content/tmp/' . $userId . '/' . $projectId . '/' . $subdir . '/' . $filename;
}

function normalize_gallery_items($gallery) {
    $gallery = normalize_gallery($gallery);
    $normalized = [];
    foreach ($gallery as $item) {
        if (is_array($item)) {
            $normalized[] = $item;
        } elseif ($item instanceof Traversable) {
            $normalized[] = iterator_to_array($item);
        } else {
            $normalized[] = (array)$item;
        }
    }
    return $normalized;
}

function ensure_media_ids($gallery) {
    $gallery = normalize_gallery_items($gallery);
    $normalized = [];
    foreach ($gallery as $item) {
        if (empty($item['media_id'])) {
            $item['media_id'] = new ObjectId();
        }
        $normalized[] = $item;
    }
    return $normalized;
}

function normalize_tags($tags) {
    if (is_array($tags)) {
        return $tags;
    }
    if ($tags instanceof Traversable) {
        return iterator_to_array($tags);
    }
    if (is_string($tags)) {
        $rawTags = array_map('trim', explode(',', $tags));
        return array_values(array_filter($rawTags, function ($tag) {
            return $tag !== '';
        }));
    }
    return [];
}

function render_media_manager($workingGallery, $editActionUrl, $message, $showMessage = false) {
    ob_start();
    ?>
        <h2>Projekt‑Medien</h2>

        <?php if ($showMessage && $message): ?>
            <div class="alert media-alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" enctype="multipart/form-data" class="media-upload-form" id="media-upload-form" data-ajax="true">
            <label for="gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="gallery_files" name="gallery_files[]" multiple accept="image/*,video/*">
            <input type="hidden" name="upload_media" value="1">
        </form>
        <?php if (!empty($workingGallery)): ?>
            <div class="media-grid">
                <?php foreach ($workingGallery as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                    ?>
                    <?php if ($url): ?>
                        <div class="media-tile" data-index="<?php echo (int)$index; ?>">
                            <?php if ($type === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline draggable="false"></video>
                                <span class="media-badge">Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy" draggable="false">
                            <?php endif; ?>
                            <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" class="media-delete" data-ajax="true">
                                <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                <button type="submit" name="delete_media">Löschen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Noch keine Medien vorhanden.</p>
        <?php endif; ?>
    <?php
    return ob_get_clean();
}

$sessionGalleryKey = 'edit_gallery_' . (string)$projectObjectId;
$sessionOriginalKey = 'edit_gallery_original_' . (string)$projectObjectId;
if (!isset($_SESSION[$sessionGalleryKey])) {
    $_SESSION[$sessionGalleryKey] = ensure_media_ids($project['gallery'] ?? []);
    $_SESSION[$sessionOriginalKey] = ensure_media_ids($project['gallery'] ?? []);
}

$workingGallery = ensure_media_ids($_SESSION[$sessionGalleryKey] ?? []);
$_SESSION[$sessionGalleryKey] = $workingGallery;

// --- LOGIK: MEDIA LÖSCHEN ---
if (isset($_POST['delete_media']) && isset($_POST['media_index'])) {
    $index = (int)$_POST['media_index'];
    $gallery = $workingGallery;

    if (isset($gallery[$index])) {
        $item = $gallery[$index];
        if (!empty($item['is_temp']) && !empty($item['tmp_path'])) {
            $path = $item['tmp_path'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        unset($gallery[$index]);
        $gallery = array_values($gallery);
        $gallery = ensure_media_ids($gallery);

        $_SESSION[$sessionGalleryKey] = $gallery;
        $workingGallery = $gallery;
        $message = "✅ Medium gelöscht.";
    }
}

if (isset($_POST['reorder_media']) && isset($_POST['order']) && is_array($_POST['order'])) {
    $order = array_map('intval', $_POST['order']);
    $gallery = $workingGallery;
    $reordered = [];
    foreach ($order as $idx) {
        if (isset($gallery[$idx])) {
            $reordered[] = $gallery[$idx];
        }
    }
    if (count($reordered) === count($gallery)) {
        $reordered = ensure_media_ids($reordered);
        $_SESSION[$sessionGalleryKey] = $reordered;
        $workingGallery = $reordered;
        $message = "✅ Reihenfolge aktualisiert.";
    }
}

// --- LOGIK: MEDIA UPLOAD ---
if (isset($_POST['upload_media']) && isset($_FILES['gallery_files'])) {
    $files = $_FILES['gallery_files'];
    $newItems = [];
    $uploadErrors = [];
    $uploadedFiles = [];
    if (count($files['name']) > MEDIA_UPLOAD_MAX_FILES) {
        $uploadErrors[] = "Maximal " . MEDIA_UPLOAD_MAX_FILES . " Dateien pro Upload.";
    }

    $limit = min(count($files['name']), MEDIA_UPLOAD_MAX_FILES);
    for ($i = 0; $i < $limit; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $uploadErrors[] = htmlspecialchars($files['name'][$i]) . ": " . upload_error_message($files['error'][$i]);
            }
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $type = detect_media_type($tmpPath, $files['name'][$i]);
        if (!$type) {
            $uploadErrors[] = "Ungültiger Dateityp: " . htmlspecialchars($files['name'][$i]);
            continue;
        }
        $validationError = validate_media_upload($tmpPath, $files['size'][$i], $type);
        if ($validationError) {
            $uploadErrors[] = htmlspecialchars($files['name'][$i]) . ": " . $validationError;
            continue;
        }
        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $safeExt = sanitize_extension($ext);
        $filename = uniqid('media_', true) . $safeExt;
        $targetDir = media_temp_dir($type, $contentTmpDir, $_SESSION['user_id'], (string)$projectObjectId);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetFile = $targetDir . '/' . $filename;

        if (move_uploaded_file($tmpPath, $targetFile)) {
            $publicPath = build_temp_url($type, $_SESSION['user_id'], (string)$projectObjectId, $filename);
            $newItems[] = [
                'media_id' => new ObjectId(),
                'type' => $type,
                'url' => $publicPath,
                'is_temp' => true,
                'tmp_path' => $targetFile
            ];
            $uploadedFiles[] = $targetFile;
        } else {
            $uploadErrors[] = "Fehler beim Speichern: " . htmlspecialchars($files['name'][$i]);
        }
    }

    if (!empty($newItems)) {
        $gallery = $workingGallery;
        $gallery = array_merge($gallery, $newItems);
        $gallery = ensure_media_ids($gallery);
        $_SESSION[$sessionGalleryKey] = $gallery;
        $workingGallery = $gallery;
        $message = "✅ Medien hochgeladen. Änderungen sind noch nicht gespeichert.";
    } elseif (!$message) {
        $message = "❌ Keine gültigen Medien zum Upload gefunden.";
    }
    if (!empty($uploadErrors)) {
        $message .= "<br>" . implode("<br>", $uploadErrors);
    }
}

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

// --- LOGIK: PROJEKT AKTUALISIEREN ---
if (isset($_POST['update_project'])) {
    $hasError = false;
    if (!empty($message)) {
        $trimmedMessage = ltrim($message);
        $hasError = strpos($trimmedMessage, '❌') === 0;
    }
    $rawTags = [];
    if (isset($_POST['tags'])) {
        $rawTags = array_map('trim', explode(',', $_POST['tags']));
    }
    $tags = array_values(array_filter($rawTags, function ($tag) {
        return $tag !== '';
    }));
    $updateData = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        // Tags sind im Schema nicht explizit als Pflichtfeld im Validator, 
        // aber wir behalten sie bei.
        'tags' => $tags,
        'updated_at' => new \MongoDB\BSON\UTCDateTime() // PFLICHT laut Schema
    ];

    if (!$hasError && !empty($updateData['title'])) {
        $finalGallery = [];
        $workingGallery = ensure_media_ids($_SESSION[$sessionGalleryKey] ?? ($project['gallery'] ?? []));
        foreach ($workingGallery as $item) {
            if (empty($item['type']) || empty($item['url'])) {
                continue;
            }
            $mediaId = $item['media_id'] ?? new ObjectId();
            if (!empty($item['is_temp']) && !empty($item['tmp_path'])) {
                $type = $item['type'];
                $ext = pathinfo($item['tmp_path'], PATHINFO_EXTENSION);
                $safeExt = sanitize_extension($ext);
                $filename = uniqid('media_', true) . $safeExt;
                $targetDir = media_target_dir($type, $contentImageDir, $contentVideoDir);
                $targetFile = $targetDir . '/' . $filename;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (rename($item['tmp_path'], $targetFile)) {
                    $publicPath = 'content/' . ($type === 'video' ? 'videos' : 'images') . '/' . $filename;
                    $finalGallery[] = [
                        'media_id' => $mediaId,
                        'type' => $type,
                        'url' => $publicPath
                    ];
                } else {
                    $message = "❌ Fehler beim Finalisieren der Medien.";
                    $hasError = true;
                    break;
                }
            } else {
                $finalGallery[] = [
                    'media_id' => $mediaId,
                    'type' => $item['type'],
                    'url' => $item['url']
                ];
            }
        }

        if (!$hasError) {
            $updateData['gallery'] = $finalGallery;
            if (!empty($finalGallery) && !empty($finalGallery[0]['url'])) {
                $updateData['thumbnail'] = $finalGallery[0]['url'];
                $updateData['thumbnail_type'] = $finalGallery[0]['type'] ?? 'image';
            }
            $originalGallery = normalize_gallery_items($_SESSION[$sessionOriginalKey] ?? ($project['gallery'] ?? []));
            $originalUrls = [];
            $originalMediaIds = [];
            foreach ($originalGallery as $item) {
                if (!empty($item['url'])) {
                    $originalUrls[] = $item['url'];
                }
                if (!empty($item['media_id'])) {
                    $originalMediaIds[(string)$item['media_id']] = true;
                }
            }
            $finalUrls = [];
            $finalMediaIds = [];
            foreach ($finalGallery as $item) {
                if (!empty($item['url'])) {
                    $finalUrls[] = $item['url'];
                }
                if (!empty($item['media_id'])) {
                    $finalMediaIds[(string)$item['media_id']] = true;
                }
            }
            $removed = array_diff($originalUrls, $finalUrls);
            foreach ($removed as $url) {
                if (strpos($url, 'content/images/') === 0 || strpos($url, 'content/videos/') === 0) {
                    $path = __DIR__ . '/../' . $url;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            }
            $removedMediaIds = array_diff(array_keys($originalMediaIds), array_keys($finalMediaIds));
            if (!empty($removedMediaIds)) {
                $objectIds = [];
                foreach ($removedMediaIds as $id) {
                    try {
                        $objectIds[] = new ObjectId($id);
                    } catch (Exception $e) {
                        continue;
                    }
                }
                if (!empty($objectIds)) {
                    $db->likes->deleteMany(['project_id' => $projectObjectId, 'media_id' => ['$in' => $objectIds]]);
                    $db->comments->deleteMany(['project_id' => $projectObjectId, 'media_id' => ['$in' => $objectIds]]);
                }
            }
        }

        if (!$hasError) {
            try {
                $update = ['$set' => $updateData];
                if (empty($finalGallery)) {
                    $update['$unset'] = [
                        'thumbnail' => '',
                        'thumbnail_type' => ''
                    ];
                }
                $db->projects->updateOne(['_id' => $projectObjectId], $update);

                $project = $db->projects->findOne(['_id' => $projectObjectId]);
                $_SESSION[$sessionGalleryKey] = ensure_media_ids($project['gallery'] ?? []);
                $_SESSION[$sessionOriginalKey] = ensure_media_ids($project['gallery'] ?? []);
                $workingGallery = $_SESSION[$sessionGalleryKey];
                header('Location: ' . $returnTo);
                exit;
            } catch (Exception $e) {
                $message = "❌ Datenbankfehler: " . $e->getMessage();
            }
        }
    } elseif (!$hasError) {
        $message = "❌ Bitte geben Sie mindestens einen Titel an.";
    }
}

$debugFlag = isset($_GET['debug']) && $_GET['debug'] === '1';
$editActionUrl = 'index.php?page=edit_project&id=' . urlencode((string)$projectObjectId);
if ($debugFlag) {
    $editActionUrl .= '&debug=1';
}

if ($isAjax) {
    echo render_media_manager($workingGallery, $editActionUrl, $message, true);
    exit;
}
?>

<div class="container">
    <div class="page-header">
        <h1>Projekt bearbeiten</h1>
        <a href="index.php?page=project_detail&id=<?php echo (string)$projectObjectId; ?>">Zurück zum Projekt</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($editActionUrl); ?>" enctype="multipart/form-data" id="edit-project-form">
        <input type="hidden" name="return_to" id="return_to" value="">
        <label for="title">Projekttitel</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($project['description']); ?></textarea>

        <label for="tags">Tags (kommagetrennt)</label>
        <?php
            $tags = normalize_tags($project['tags'] ?? []);
        ?>
        <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', $tags)); ?>" placeholder="z.B. Coding, Musik, Gym">

        <button type="submit" name="update_project">Änderungen speichern</button>
    </form>

    <section id="media-upload" class="media-manager">
        <?php echo render_media_manager($workingGallery, $editActionUrl, $message); ?>
    </section>
</div>

<script>
    (function () {
        const backTarget = 'index.php';
        const navEntries = performance.getEntriesByType('navigation');
        const navType = navEntries && navEntries.length ? navEntries[0].type : '';
        if (navType === 'back_forward') {
            window.location.replace(backTarget);
            return;
        }

        const returnToInput = document.getElementById('return_to');
        if (returnToInput && document.referrer) {
            returnToInput.value = document.referrer;
        }

        const mediaSection = document.getElementById('media-upload');
        if (!mediaSection) return;
        const mediaActionUrl = <?php echo json_encode($editActionUrl); ?>;

        async function submitMediaForm(form, submitter) {
            const formData = new FormData(form);
            formData.set('ajax', '1');
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value || '1');
            }
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'fetch'
                }
            });
            const html = await response.text();
            mediaSection.innerHTML = html;
        }

        mediaSection.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.ajax !== 'true') {
                return;
            }
            event.preventDefault();
            submitMediaForm(form, event.submitter).catch(() => {});
        });

        mediaSection.addEventListener('change', function (event) {
            const target = event.target;
            if (!target || target.id !== 'gallery_files') {
                return;
            }
            const form = target.closest('form');
            if (!form || form.dataset.ajax !== 'true') {
                return;
            }
            if (!target.files || target.files.length === 0) {
                return;
            }
            submitMediaForm(form).catch(() => {});
        });

        function sendReorder(grid) {
            const order = Array.from(grid.querySelectorAll('.media-tile')).map((tile) => tile.dataset.index);
            const formData = new FormData();
            order.forEach((idx) => formData.append('order[]', idx));
            formData.set('reorder_media', '1');
            formData.set('ajax', '1');
            fetch(mediaActionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'fetch'
                }
            }).then((response) => response.text())
              .then((html) => {
                  mediaSection.innerHTML = html;
              }).catch(() => {});
        }

        let pointerDrag = null;
        mediaSection.addEventListener('pointerdown', function (event) {
            const tile = event.target.closest('.media-tile');
            if (!tile) return;
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            if (event.target.closest('button, input, form')) return;
            pointerDrag = tile;
            tile.classList.add('is-dragging');
            tile.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        mediaSection.addEventListener('pointermove', function (event) {
            if (!pointerDrag) return;
            const el = document.elementFromPoint(event.clientX, event.clientY);
            const tile = el ? el.closest('.media-tile') : null;
            if (!tile || tile === pointerDrag) return;
            const grid = tile.parentElement;
            const tiles = Array.from(grid.querySelectorAll('.media-tile'));
            const draggedIndex = tiles.indexOf(pointerDrag);
            const targetIndex = tiles.indexOf(tile);
            if (draggedIndex < targetIndex) {
                grid.insertBefore(pointerDrag, tile.nextSibling);
            } else {
                grid.insertBefore(pointerDrag, tile);
            }
        });

        function endPointerDrag(event) {
            if (!pointerDrag) return;
            const grid = pointerDrag.parentElement;
            pointerDrag.classList.remove('is-dragging');
            try {
                pointerDrag.releasePointerCapture(event.pointerId);
            } catch (e) {
                // ignore
            }
            pointerDrag = null;
            if (grid) {
                sendReorder(grid);
            }
        }

        mediaSection.addEventListener('pointerup', endPointerDrag);
        mediaSection.addEventListener('pointercancel', endPointerDrag);
    })();
</script>
