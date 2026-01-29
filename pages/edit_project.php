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

if (!is_dir($contentImageDir)) {
    mkdir($contentImageDir, 0755, true);
}
if (!is_dir($contentVideoDir)) {
    mkdir($contentVideoDir, 0755, true);
}

function media_target_dir($type, $imageDir, $videoDir) {
    return $type === 'video' ? $videoDir : $imageDir;
}

// --- LOGIK: MEDIA REIHENFOLGE ---
if (isset($_POST['move_media']) && isset($_POST['media_index']) && isset($_POST['direction'])) {
    $index = (int)$_POST['media_index'];
    $direction = $_POST['direction'];
    $gallery = normalize_gallery($project['gallery'] ?? []);
    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

    if (isset($gallery[$index]) && isset($gallery[$swapIndex])) {
        $tmp = $gallery[$index];
        $gallery[$index] = $gallery[$swapIndex];
        $gallery[$swapIndex] = $tmp;

        $update = [
            '$set' => [
                'gallery' => $gallery,
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
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

        $db->projects->updateOne(['_id' => $projectObjectId], $update);
        $project = $db->projects->findOne(['_id' => $projectObjectId]);
        $message = "✅ Reihenfolge aktualisiert.";
    }
}

// --- LOGIK: CAPTIONS SPEICHERN ---
if (isset($_POST['save_captions']) && isset($_POST['caption']) && is_array($_POST['caption'])) {
    $gallery = normalize_gallery($project['gallery'] ?? []);
    foreach ($gallery as $i => $item) {
        if (isset($_POST['caption'][$i])) {
            $gallery[$i]['caption'] = trim($_POST['caption'][$i]);
        }
    }
    $db->projects->updateOne(
        ['_id' => $projectObjectId],
        ['$set' => [
            'gallery' => $gallery,
            'updated_at' => new \MongoDB\BSON\UTCDateTime()
        ]]
    );
    $project = $db->projects->findOne(['_id' => $projectObjectId]);
    $message = "✅ Captions gespeichert.";
}

// --- LOGIK: MEDIA LÖSCHEN ---
if (isset($_POST['delete_media']) && isset($_POST['media_index'])) {
    $index = (int)$_POST['media_index'];
    $gallery = normalize_gallery($project['gallery'] ?? []);

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
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
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
            ['_id' => $projectObjectId],
            $update
        );
        $project = $db->projects->findOne(['_id' => $projectObjectId]);
        $message = "✅ Medium gelöscht.";
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
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $type = detect_media_type($tmpPath);
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
                'url' => $publicPath,
                'caption' => ''
            ];
            $uploadedFiles[] = $targetFile;
        } else {
            $uploadErrors[] = "Fehler beim Speichern: " . htmlspecialchars($files['name'][$i]);
        }
    }

    if (!empty($newItems)) {
        $gallery = normalize_gallery($project['gallery'] ?? []);
        $gallery = array_merge($gallery, $newItems);
        $update = [
            '$set' => [
                'gallery' => $gallery,
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
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
            $db->projects->updateOne(['_id' => $projectObjectId], $update);
            $project = $db->projects->findOne(['_id' => $projectObjectId]);
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

// --- LOGIK: PROJEKT AKTUALISIEREN ---
if (isset($_POST['update_project'])) {
    $updateData = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        // Tags sind im Schema nicht explizit als Pflichtfeld im Validator, 
        // aber wir behalten sie bei.
        'tags' => array_map('trim', explode(',', $_POST['tags'])),
        'updated_at' => new \MongoDB\BSON\UTCDateTime() // PFLICHT laut Schema
    ];

    if (empty($message) && !empty($updateData['title'])) {
        try {
            $db->projects->updateOne(
                ['_id' => $projectObjectId],
                ['$set' => $updateData]
            );

            $project = $db->projects->findOne(['_id' => $projectObjectId]);
            $message = "✅ Projekt erfolgreich aktualisiert!";
        } catch (Exception $e) {
            $message = "❌ Datenbankfehler: " . $e->getMessage();
        }
    } elseif (empty($message)) {
        $message = "❌ Bitte geben Sie mindestens einen Titel an.";
    }
}
?>

<head>
    <link rel="stylesheet" href="style/edit_project.css">
</head>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Projekt bearbeiten</h1>
        <a href="index.php?page=project_detail&id=<?php echo (string)$projectObjectId; ?>">Zurück zum Projekt</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="title">Projekttitel</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($project['description']); ?></textarea>

        <label for="tags">Tags (kommagetrennt)</label>
        <?php
            $tags = [];
            if (isset($project['tags']) && is_array($project['tags'])) {
                $tags = $project['tags'];
            }
        ?>
        <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', $tags)); ?>" placeholder="z.B. Coding, Musik, Gym">

        <button type="submit" name="update_project">Änderungen speichern</button>
    </form>

    <section id="media-upload" class="media-manager">
        <h2>Projekt‑Medien</h2>

        <?php if (!empty(normalize_gallery($project['gallery'] ?? []))): ?>
            <div class="media-grid">
                <?php foreach (normalize_gallery($project['gallery'] ?? []) as $index => $item): ?>
                    <?php
                        $type = $item['type'] ?? 'image';
                        $url = $item['url'] ?? '';
                        $caption = $item['caption'] ?? '';
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
                                <form method="POST">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" name="move_media" value="1">↑</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" name="move_media" value="1">↓</button>
                                </form>
                            </div>
                            <input class="media-caption" type="text" name="caption[<?php echo (int)$index; ?>]" form="caption-form" value="<?php echo htmlspecialchars($caption); ?>" placeholder="Caption...">
                            <form method="POST" class="media-delete">
                                <input type="hidden" name="media_index" value="<?php echo (int)$index; ?>">
                                <button type="submit" name="delete_media">Löschen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <form id="caption-form" method="POST" class="caption-form">
                <button type="submit" name="save_captions">Captions speichern</button>
            </form>
        <?php else: ?>
            <p>Noch keine Medien vorhanden.</p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="media-upload-form">
            <label for="gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="gallery_files" name="gallery_files[]" multiple accept="image/*,video/*">
            <button type="submit" name="upload_media">Medien hochladen</button>
        </form>
    </section>
</div>
