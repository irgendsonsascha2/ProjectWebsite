<?php
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

// --- LOGIK: PROJEKT ERSTELLEN ---
if (isset($_POST['create_project'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $tags = array_map('trim', explode(',', $_POST['tags']));

    // --- BILD-UPLOAD ---
    $imagePath = null;
    if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../img/thumbnails/';
        $filename = uniqid() . '-' . basename($_FILES['project_image']['name']);
        $targetFile = $uploadDir . $filename;

        $check = getimagesize($_FILES['project_image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['project_image']['tmp_name'], $targetFile)) {
                $imagePath = 'img/thumbnails/' . $filename;
            } else {
                $message = "Fehler beim Verschieben des Bildes.";
            }
        } else {
            $message = "Die hochgeladene Datei ist kein gültiges Bild.";
        }
    }

    if (empty($message) && !empty($title)) {
        // Die $db Variable wird von der index.php bereitgestellt
        $db->projects->insertOne([
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'image' => $imagePath,
            'author_id' => new \MongoDB\BSON\ObjectId($_SESSION['user_id']),
            'created_at' => new UTCDateTime()
        ]);

        $message = "✅ Projekt erfolgreich erstellt!";
    } elseif (empty($message)) {
        $message = "❌ Bitte geben Sie mindestens einen Titel an.";
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

        <label for="project_image">Vorschaubild</label>
        <input type="file" id="project_image" name="project_image" accept="image/*">

        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

        <button type="submit" name="create_project">Projekt erstellen</button>
    </form>
</div>