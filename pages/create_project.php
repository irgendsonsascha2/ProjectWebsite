<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// --- HILFSFUNKTION FÜR RECHTE ---
if (!function_exists('can')) {
    function can($permission)
    {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}

// --- BERECHTIGUNGS-CHECK ---
if (!can('create_project')) {
    die("<h1>Zugriff verweigert</h1><p>Sie haben nicht die nötigen Rechte, um Projekte zu erstellen.</p>");
}

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

$message = "";
$contentBaseDir = __DIR__ . '/../content';
$contentImageDir = $contentBaseDir . '/images';
$contentVideoDir = $contentBaseDir . '/videos';

if (!is_dir($contentImageDir)) {
    mkdir($contentImageDir, 0755, true);
}
if (!is_dir($contentVideoDir)) {
    mkdir($contentVideoDir, 0755, true);
}

function media_target_dir($type, $imageDir, $videoDir) {
    return $type === 'video' ? $videoDir : $imageDir;
}

function delete_project_media_files($project) {
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

function handle_media_uploads($files, $contentImageDir, $contentVideoDir) {
    $newItems = [];
    $uploadedFiles = [];
    $uploadErrors = [];
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
        $targetDir = media_target_dir($type, $contentImageDir, $contentVideoDir);
        $targetFile = $targetDir . '/' . $filename;

        if (move_uploaded_file($tmpPath, $targetFile)) {
            $publicPath = 'content/' . ($type === 'video' ? 'videos' : 'images') . '/' . $filename;
            $newItems[] = [
                'media_id' => new ObjectId(),
                'type' => $type,
                'url' => $publicPath
            ];
            $uploadedFiles[] = $targetFile;
        } else {
            $uploadErrors[] = "Fehler beim Speichern: " . htmlspecialchars($files['name'][$i]);
        }
    }

    return [$newItems, $uploadedFiles, $uploadErrors];
}

function ensure_media_ids($gallery) {
    $gallery = normalize_gallery($gallery);
    $normalized = [];
    foreach ($gallery as $item) {
        if ($item instanceof Traversable) {
            $item = iterator_to_array($item);
        } elseif (!is_array($item)) {
            $item = (array)$item;
        }
        if (empty($item['media_id'])) {
            $item['media_id'] = new ObjectId();
        }
        $normalized[] = $item;
    }
    return $normalized;
}

function render_media_manager($draftProject, $createActionUrl, $message, $showMessage = false) {
    $gallery = $draftProject ? normalize_gallery($draftProject['gallery'] ?? []) : [];
    $draftId = ($draftProject && isset($draftProject['_id'])) ? (string)$draftProject['_id'] : '';
    ob_start();
    ?>
        <h2>Projekt‑Medien (Entwurf)</h2>

        <?php if ($showMessage && $message): ?>
            <div class="alert media-alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <input type="hidden" id="draft-id" value="<?php echo htmlspecialchars($draftId); ?>">

        <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>" enctype="multipart/form-data" class="media-upload-form" id="draft-media-upload-form" data-ajax="true">
            <label for="draft_gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="draft_gallery_files" name="draft_gallery_files[]" multiple accept="image/*,video/*">
            <input type="hidden" name="upload_media_draft" value="1">
        </form>
        <?php if (!empty($gallery)): ?>
            <div class="media-grid">
                <?php foreach ($gallery as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                    ?>
                    <?php if ($url): ?>
                        <div class="media-tile" data-index="<?php echo (int)$index; ?>">
                            <?php if ($type === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline draggable="false" disablepictureinpicture></video>
                                <span class="media-badge">Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy" draggable="false">
                            <?php endif; ?>
                            <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>" class="media-delete" data-ajax="true">
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

$draftProject = null;
$draftObjectId = null;
if (isset($_SESSION['draft_project_id'])) {
    try {
        $draftObjectId = new ObjectId($_SESSION['draft_project_id']);
        $draftProject = $db->projects->findOne([
            '_id' => $draftObjectId,
            'author_id' => $_SESSION['user_id'],
            'is_draft' => true
        ]);
        if (!$draftProject) {
            unset($_SESSION['draft_project_id']);
            $draftObjectId = null;
        }
    } catch (Exception $e) {
        unset($_SESSION['draft_project_id']);
        $draftObjectId = null;
    }
}

$createActionUrl = 'index.php?page=create_project';
$debugFlag = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debugFlag) {
    $createActionUrl .= '&debug=1';
}

$isAjax = request_is_ajax();
$postBodyError = media_upload_request_body_too_large();
if ($postBodyError !== null) {
    $message = '❌ ' . $postBodyError;
}

// --- LOGIK: DRAFT CLEANUP ---
if (isset($_POST['cleanup_draft']) && $_POST['cleanup_draft'] === '1') {
    if ($draftObjectId && $draftProject) {
        delete_project_media_files($draftProject);
        $db->projects->deleteOne(['_id' => $draftObjectId]);
        unset($_SESSION['draft_project_id']);
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => true]);
    exit();
}

// --- LOGIK: DRAFT MEDIA LÖSCHEN ---
if ($draftObjectId && isset($_POST['delete_media']) && isset($_POST['media_index'])) {
    $index = (int)$_POST['media_index'];
    $gallery = normalize_gallery($draftProject['gallery'] ?? []);

    if (isset($gallery[$index])) {
        $item = $gallery[$index];
        $url = $item['url'] ?? '';
        if (strpos($url, 'content/images/') === 0 || strpos($url, 'content/videos/') === 0) {
            $path = __DIR__ . '/../' . $url;
            if (is_file($path)) {
                unlink($path);
            }
        }
        unset($gallery[$index]);
        $gallery = array_values($gallery);
        $gallery = ensure_media_ids($gallery);

        $update = [
            '$set' => [
                'gallery' => $gallery,
                'updated_at' => new UTCDateTime()
            ]
        ];
        if (!empty($gallery) && !empty($gallery[0]['url'])) {
            $update['$set']['thumbnail'] = $gallery[0]['url'];
            $update['$set']['thumbnail_type'] = $gallery[0]['type'] ?? 'image';
        } else {
            $update['$unset'] = [
                'thumbnail' => '',
                'thumbnail_type' => ''
            ];
        }
        $db->projects->updateOne(
            ['_id' => $draftObjectId],
            $update
        );
        $draftProject = $db->projects->findOne(['_id' => $draftObjectId]);
        $message = "✅ Medium gelöscht.";
    }
}

if ($draftObjectId && isset($_POST['reorder_media']) && isset($_POST['order']) && is_array($_POST['order'])) {
    $order = array_map('intval', $_POST['order']);
    $gallery = normalize_gallery($draftProject['gallery'] ?? []);
    $reordered = [];
    foreach ($order as $idx) {
        if (isset($gallery[$idx])) {
            $reordered[] = $gallery[$idx];
        }
    }
    if (count($reordered) === count($gallery)) {
        $reordered = ensure_media_ids($reordered);
        $update = [
            '$set' => [
                'gallery' => $reordered,
                'updated_at' => new UTCDateTime()
            ]
        ];
        if (!empty($reordered) && !empty($reordered[0]['url'])) {
            $update['$set']['thumbnail'] = $reordered[0]['url'];
            $update['$set']['thumbnail_type'] = $reordered[0]['type'] ?? 'image';
        } else {
            $update['$unset'] = [
                'thumbnail' => '',
                'thumbnail_type' => ''
            ];
        }
        $db->projects->updateOne(['_id' => $draftObjectId], $update);
        $draftProject = $db->projects->findOne(['_id' => $draftObjectId]);
        $message = "✅ Reihenfolge aktualisiert.";
    }
}

// --- LOGIK: DRAFT MEDIA UPLOAD ---
if (isset($_POST['upload_media_draft']) && isset($_FILES['draft_gallery_files'])) {
    if (!$draftObjectId) {
        $now = new UTCDateTime();
        $draftDocument = [
            'title' => 'Entwurf',
            'description' => '',
            'gallery' => [],
            'tags' => [],
            'author_id' => $_SESSION['user_id'],
            'is_draft' => true,
            'created_at' => $now,
            'updated_at' => $now
        ];
        $insertResult = $db->projects->insertOne($draftDocument);
        $draftObjectId = $insertResult->getInsertedId();
        $_SESSION['draft_project_id'] = (string)$draftObjectId;
        $draftProject = $db->projects->findOne(['_id' => $draftObjectId]);
    }

    $files = $_FILES['draft_gallery_files'];
    [$newItems, $uploadedFiles, $uploadErrors] = handle_media_uploads($files, $contentImageDir, $contentVideoDir);

    if (!empty($newItems)) {
        $gallery = normalize_gallery($draftProject['gallery'] ?? []);
        $gallery = array_merge($gallery, $newItems);
        $gallery = ensure_media_ids($gallery);
        $update = [
            '$set' => [
                'gallery' => $gallery,
                'updated_at' => new UTCDateTime()
            ]
        ];
        if (!empty($gallery) && !empty($gallery[0]['url'])) {
            $update['$set']['thumbnail'] = $gallery[0]['url'];
            $update['$set']['thumbnail_type'] = $gallery[0]['type'] ?? 'image';
        } else {
            $update['$unset'] = [
                'thumbnail' => '',
                'thumbnail_type' => ''
            ];
        }
        try {
            $db->projects->updateOne(['_id' => $draftObjectId], $update);
            $draftProject = $db->projects->findOne(['_id' => $draftObjectId]);
            $message = "✅ Medien hochgeladen.";
        } catch (Exception $e) {
            foreach ($uploadedFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $message = "❌ Datenbankfehler: " . $e->getMessage();
        }
    } elseif (!$message) {
        $message = "❌ Keine gültigen Medien zum Upload gefunden.";
    }
    if (!empty($uploadErrors)) {
        $message .= "<br>" . implode("<br>", $uploadErrors);
    }
}

function has_error_message($message) {
    if (!$message) {
        return false;
    }
    $trimmed = ltrim($message);
    return strpos($trimmed, '❌') === 0;
}

// --- LOGIK: PROJEKT ERSTELLEN ---
if (isset($_POST['create_project'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $rawTags = [];
    if (isset($_POST['tags'])) {
        $rawTags = array_map('trim', explode(',', $_POST['tags']));
    }
    $tags = array_values(array_filter($rawTags, function ($tag) {
        return $tag !== '';
    }));
    $gallery = [];
    $uploadedFiles = [];
    $uploadErrors = [];

    if (isset($_FILES['gallery_files'])) {
        $files = $_FILES['gallery_files'];
        [$gallery, $uploadedFiles, $uploadErrors] = handle_media_uploads($files, $contentImageDir, $contentVideoDir);
    }

    // --- LOGIK: PROJEKT ERSTELLEN ---
    $hasError = has_error_message($message);
    if (!$hasError && !empty($title)) {
        $now = new \MongoDB\BSON\UTCDateTime();
        $existingGallery = $draftProject ? normalize_gallery($draftProject['gallery'] ?? []) : [];
        if (!empty($gallery)) {
            $gallery = array_merge($existingGallery, $gallery);
        } else {
            $gallery = $existingGallery;
        }
        $gallery = ensure_media_ids($gallery);
        $thumb = !empty($gallery) && !empty($gallery[0]['url']) ? $gallery[0]['url'] : null;
        $thumbType = !empty($gallery) && !empty($gallery[0]['type']) ? $gallery[0]['type'] : null;

        // Wir bauen das Dokument exakt nach Schema-Vorgabe
        if ($draftObjectId) {
            $updateData = [
                'title' => $title,
                'description' => $description,
                'tags' => $tags,
                'gallery' => $gallery,
                'is_draft' => false,
                'updated_at' => $now
            ];
            if ($thumb) {
                $updateData['thumbnail'] = $thumb;
                $updateData['thumbnail_type'] = $thumbType ?? 'image';
            }
            try {
                $db->projects->updateOne(
                    ['_id' => $draftObjectId],
                    ['$set' => $updateData]
                );
                $publishedId = $draftObjectId;
                unset($_SESSION['draft_project_id']);
                $draftProject = null;
                $draftObjectId = null;
                header("Location: index.php?page=project_detail&id=" . urlencode((string)$publishedId));
                exit();
            } catch (Exception $e) {
                $message = "❌ Datenbankfehler: " . $e->getMessage();
            }
        } else {
            $document = [
                'title'       => $title,
                'description' => $description,
                'gallery'     => $gallery, // Muss vorhanden sein (Array) laut Schema
                'tags'        => $tags,
                'author_id'   => $_SESSION['user_id'], // Als STRING senden, wie im Schema definiert
                'is_draft'    => false,
                'created_at'  => $now,
                'updated_at'  => $now  // Muss vorhanden sein laut Schema
            ];
            if ($thumb) {
                $document['thumbnail'] = $thumb;
                $document['thumbnail_type'] = $thumbType ?? 'image';
            }

            try {
                $insertResult = $db->projects->insertOne($document);
                $newId = $insertResult->getInsertedId();
                header("Location: index.php?page=project_detail&id=" . urlencode((string)$newId));
                exit();
            } catch (Exception $e) {
                foreach ($uploadedFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                // Dies zeigt dir den genauen Validierungsfehler an
                $message = "❌ Datenbankfehler: " . $e->getMessage();
            }
        }
    }
    if (!empty($uploadErrors)) {
        $message .= "<br>" . implode("<br>", $uploadErrors);
    }
}

if ($isAjax) {
    echo render_media_manager($draftProject, $createActionUrl, $message, true);
    exit();
}
?>

<div class="container">
    <div class="page-header">
        <h1>Neues Projekt</h1>
        <?php if ($draftProject): ?>
            <span class="draft-badge">Entwurf aktiv</span>
        <?php endif; ?>
        <a href="index.php?page=project_grid">Zurück zur Übersicht</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>" enctype="multipart/form-data">
        <label for="title">Projekttitel</label>
        <input type="text" id="title" name="title" required>

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description"></textarea>

        <label for="tags">Tags (kommagetrennt)</label>
        <input type="text" id="tags" name="tags" placeholder="z.B. Coding, Musik, Gym">

        <button type="submit" name="create_project">Projekt erstellen</button>
    </form>

    <section id="media-upload" class="media-manager">
        <?php echo render_media_manager($draftProject, $createActionUrl, $message); ?>
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

        const mediaSection = document.getElementById('media-upload');
        if (!mediaSection) return;
        const mediaActionUrl = <?php echo json_encode($createActionUrl); ?>;
        let isSubmitting = false;

        document.addEventListener('submit', function () {
            isSubmitting = true;
        }, true);

        function showMediaUploadError(text) {
            mediaSection.innerHTML = '<div class="alert media-alert">❌ ' + text + '</div>';
        }

        async function submitMediaForm(form, submitter) {
            const formData = new FormData(form);
            formData.set('ajax', '1');
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value || '1');
            }
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'fetch'
                    }
                });
                if (!response.ok) {
                    showMediaUploadError('Upload fehlgeschlagen (HTTP ' + response.status + ').');
                    return;
                }
                const html = await response.text();
                mediaSection.innerHTML = html;
            } catch (err) {
                showMediaUploadError('Upload fehlgeschlagen (Netzwerk oder Zeitüberschreitung).');
            }
        }

        mediaSection.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.dataset.ajax !== 'true') {
                return;
            }
            event.preventDefault();
            submitMediaForm(form, event.submitter);
        });

        mediaSection.addEventListener('change', function (event) {
            const target = event.target;
            if (!target || target.id !== 'draft_gallery_files') {
                return;
            }
            const form = target.closest('form');
            if (!form || form.dataset.ajax !== 'true') {
                return;
            }
            if (!target.files || target.files.length === 0) {
                return;
            }
            submitMediaForm(form);
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

        function getDraftId() {
            const node = document.getElementById('draft-id');
            if (!node) return null;
            const value = node.value ? node.value.trim() : '';
            return value.length > 0 ? value : null;
        }
        const cleanupUrl = <?php echo json_encode($createActionUrl); ?>;
        window.addEventListener('beforeunload', function () {
            const draftId = getDraftId();
            if (isSubmitting || !draftId) {
                return;
            }
            const data = new URLSearchParams({ cleanup_draft: '1' });
            navigator.sendBeacon(
                cleanupUrl,
                new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' })
            );
        });
    })();
</script>
