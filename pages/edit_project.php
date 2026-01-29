<?php
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

function detect_media_type($tmpPath) {
    $mime = mime_content_type($tmpPath);
    if (strpos($mime, 'image/') === 0) {
        return 'image';
    }
    if (strpos($mime, 'video/') === 0) {
        return 'video';
    }
    return null;
}

function media_target_dir($type, $imageDir, $videoDir) {
    return $type === 'video' ? $videoDir : $imageDir;
}

// --- LOGIK: MEDIA LÖSCHEN ---
if (isset($_POST['delete_media']) && isset($_POST['media_index'])) {
    $index = (int)$_POST['media_index'];
    $gallery = isset($project['gallery']) && is_array($project['gallery']) ? $project['gallery'] : [];

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

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmpPath = $files['tmp_name'][$i];
        $type = detect_media_type($tmpPath);
        if (!$type) {
            continue;
        }
        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $safeExt = $ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '';
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
        }
    }

    if (!empty($newItems)) {
        $gallery = isset($project['gallery']) && is_array($project['gallery']) ? $project['gallery'] : [];
        $isEmptyBefore = empty($gallery);
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
        $db->projects->updateOne(
            ['_id' => $projectObjectId],
            $update
        );
        $project = $db->projects->findOne(['_id' => $projectObjectId]);
        $message = "✅ Medien hochgeladen.";
    } elseif (!$message) {
        $message = "❌ Keine gültigen Medien zum Upload gefunden.";
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

        <?php if (!empty($project['gallery']) && is_array($project['gallery'])): ?>
            <div class="media-grid">
                <?php foreach ($project['gallery'] as $index => $item): ?>
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
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="Bild">
                            <?php endif; ?>
                            <form method="POST" class="media-delete">
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

        <form method="POST" enctype="multipart/form-data" class="media-upload-form">
            <label for="gallery_files">Bilder/Videos hinzufügen</label>
            <input type="file" id="gallery_files" name="gallery_files[]" multiple accept="image/*,video/*">
            <button type="submit" name="upload_media">Medien hochladen</button>
        </form>
    </section>
</div>
