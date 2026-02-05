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

// --- LOGIK: DRAFT MEDIA REIHENFOLGE ---
if ($draftObjectId && isset($_POST['move_media']) && isset($_POST['media_index']) && isset($_POST['direction'])) {
    $index = (int)$_POST['media_index'];
    $direction = $_POST['direction'];
    $gallery = normalize_gallery($draftProject['gallery'] ?? []);
    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

    if (isset($gallery[$index]) && isset($gallery[$swapIndex])) {
        $tmp = $gallery[$index];
        $gallery[$index] = $gallery[$swapIndex];
        $gallery[$swapIndex] = $tmp;

        $update = [
            '$set' => [
                'gallery' => $gallery,
                'updated_at' => new UTCDateTime()
            ]
        ];
        if (!empty($gallery[0]['url'])) {
            $update['$set']['thumbnail'] = $gallery[0]['url'];
            $update['$set']['thumbnail_type'] = $gallery[0]['type'] ?? 'image';
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
    if (empty($message) && !empty($title)) {
        $now = new \MongoDB\BSON\UTCDateTime();
        $existingGallery = $draftProject ? normalize_gallery($draftProject['gallery'] ?? []) : [];
        if (!empty($gallery)) {
            $gallery = array_merge($existingGallery, $gallery);
        } else {
            $gallery = $existingGallery;
        }
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
?>

<head>
    <link rel="stylesheet" href="style/create_project.css">
</head>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
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
        <h2>Projekt‑Medien (Entwurf)</h2>

        <?php if ($draftProject && !empty(normalize_gallery($draftProject['gallery'] ?? []))): ?>
            <div class="media-grid">
                <?php foreach (normalize_gallery($draftProject['gallery'] ?? []) as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                    ?>
                    <?php if ($url): ?>
                        <div class="media-tile">
                            <?php if ($type === 'video'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" preload="metadata" muted playsinline></video>
                                <span class="media-badge">Video</span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild" loading="lazy">
                            <?php endif; ?>
                            <div class="media-actions">
                                <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" name="move_media" value="1">↑</button>
                                </form>
                                <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" name="move_media" value="1">↓</button>
                                </form>
                            </div>
                            <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>" class="media-delete">
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

        <form method="POST" action="<?php echo htmlspecialchars($createActionUrl); ?>" enctype="multipart/form-data" class="media-upload-form" id="draft-media-upload-form">
            <label for="draft_gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="draft_gallery_files" name="draft_gallery_files[]" multiple accept="image/*,video/*">
            <input type="hidden" name="upload_media_draft" value="1">
        </form>
    </section>
</div>

<script>
    (function () {
        const fileInput = document.getElementById('draft_gallery_files');
        const form = document.getElementById('draft-media-upload-form');
        if (!fileInput || !form) return;
        let isSubmitting = false;

        document.addEventListener('submit', function () {
            isSubmitting = true;
        }, true);

        fileInput.addEventListener('change', function () {
            if (!fileInput.files || fileInput.files.length === 0) {
                return;
            }
            form.submit();
        });

        const draftId = <?php echo $draftObjectId ? json_encode((string)$draftObjectId) : 'null'; ?>;
        const cleanupUrl = <?php echo json_encode($createActionUrl); ?>;
        window.addEventListener('beforeunload', function () {
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
