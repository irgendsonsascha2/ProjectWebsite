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

// --- LOGIK: PROJEKT ERSTELLEN ---
if (isset($_POST['create_project'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $tags = array_map('trim', explode(',', $_POST['tags']));
    $gallery = [];
    $uploadedFiles = [];
    $uploadErrors = [];

    if (isset($_FILES['gallery_files'])) {
        $files = $_FILES['gallery_files'];
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
                $gallery[] = [
                    'type' => $type,
                    'url' => $publicPath,
                    'caption' => ''
                ];
                $uploadedFiles[] = $targetFile;
            } else {
                $uploadErrors[] = "Fehler beim Speichern: " . htmlspecialchars($files['name'][$i]);
            }
        }
    }

    // --- LOGIK: PROJEKT ERSTELLEN ---
    if (empty($message) && !empty($title)) {
        $now = new \MongoDB\BSON\UTCDateTime();
        $thumb = !empty($gallery) && !empty($gallery[0]['url']) ? $gallery[0]['url'] : null;
        $thumbType = !empty($gallery) && !empty($gallery[0]['type']) ? $gallery[0]['type'] : null;

        // Wir bauen das Dokument exakt nach Schema-Vorgabe
        $document = [
            'title'       => $title,
            'description' => $description,
            'gallery'     => $gallery, // Muss vorhanden sein (Array) laut Schema
            'author_id'   => $_SESSION['user_id'], // Als STRING senden, wie im Schema definiert
            'created_at'  => $now,
            'updated_at'  => $now  // Muss vorhanden sein laut Schema
        ];
        if ($thumb) {
            $document['thumbnail'] = $thumb;
            $document['thumbnail_type'] = $thumbType ?? 'image';
        }

        try {
            $db->projects->insertOne($document);
            $message = "✅ Projekt erfolgreich erstellt!";
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
        <a href="index.php?page=project_grid">Zurück zur Übersicht</a>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="title">Projekttitel</label>
        <input type="text" id="title" name="title" required>

        <label for="description">Beschreibung</label>
        <textarea id="description" name="description"></textarea>

        <label for="tags">Tags (kommagetrennt)</label>
        <input type="text" id="tags" name="tags" placeholder="z.B. Coding, Musik, Gym">

        <label for="gallery_files">Bilder/Videos hinzufügen</label>
        <input type="file" id="gallery_files" name="gallery_files[]" multiple accept="image/*,video/*">

        <button type="submit" name="create_project">Projekt erstellen</button>
    </form>
</div>
