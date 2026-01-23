<?php
// --- HILFSFUNKTIONEN ---
if (!function_exists('can')) {
    function can($permission) {
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

// --- LOGIK: PROJEKT AKTUALISIEREN ---
if (isset($_POST['update_project'])) {
    $updateData = [
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'tags' => array_map('trim', explode(',', $_POST['tags'])),
    ];

    // --- BILD-UPLOAD ---
    if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../img/thumbnails/';
        $filename = uniqid() . '-' . basename($_FILES['project_image']['name']);
        $targetFile = $uploadDir . $filename;
        
        $check = getimagesize($_FILES['project_image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['project_image']['tmp_name'], $targetFile)) {
                // Altes Bild löschen, falls vorhanden
                if (!empty($project['image']) && file_exists(__DIR__ . '/../' . $project['image'])) {
                    unlink(__DIR__ . '/../' . $project['image']);
                }
                $updateData['image'] = 'img/thumbnails/' . $filename;
            } else {
                $message = "Fehler beim Verschieben des Bildes.";
            }
        } else {
            $message = "Die hochgeladene Datei ist kein gültiges Bild.";
        }
    }

    if (empty($message) && !empty($updateData['title'])) {
        $db->projects->updateOne(
            ['_id' => $projectObjectId],
            ['$set' => $updateData]
        );
        
        // Projekt-Daten neu laden, um die Änderungen im Formular anzuzeigen
        $project = $db->projects->findOne(['_id' => $projectObjectId]);
        $message = "✅ Projekt erfolgreich aktualisiert!";
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
        <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars(implode(', ', iterator_to_array($project['tags']))); ?>" placeholder="z.B. Coding, Musik, Gym">
        
        <label for="project_image">Vorschaubild</label>
        <?php if (!empty($project['image'])): ?>
            <p>Aktuelles Bild:</p>
            <img src="<?php echo htmlspecialchars($project['image']); ?>" alt="Vorschaubild" class="current-image">
        <?php endif; ?>
        <input type="file" id="project_image" name="project_image" accept="image/*">
        <p style="font-size: 0.8rem; color: #666;">Laden Sie ein neues Bild hoch, um das aktuelle zu ersetzen.</p>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

        <button type="submit" name="update_project">Änderungen speichern</button>
    </form>
</div>