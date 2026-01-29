<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// --- BERECHTIGUNGS-CHECK ---
// 1. Ist der User überhaupt eingeloggt?
if (!isset($_SESSION['user_id'])) {
    header('Location: ../account.php'); // Zum Login umleiten
    exit();
}

// 2. Hat der User die Admin-Rolle?
if ($_SESSION['role'] !== 'admin') {
    die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren.</p>");
}

$message = "";

// --- LOGIK: SCRIPT AUSFÜHREN ---
if (isset($_POST['run_script'])) {
    $scriptPath = __DIR__ . '/../../dbScripts/' . basename($_POST['script_name']); // Basename zur Sicherheit

    if (file_exists($scriptPath)) {
        // Output-Buffering, um die Ausgabe des Scripts abzufangen
        ob_start();

        try {
            // Die DB-Verbindung für das inkludierte Script bereitstellen
            include $scriptPath;

            $message = "<h3>Ergebnis für: " . htmlspecialchars($_POST['script_name']) . "</h3><pre>" . ob_get_clean() . "</pre>";
        } catch (Exception $e) {
            ob_end_clean(); // Buffer leeren im Fehlerfall
            $message = "<h3>Fehler in " . htmlspecialchars($_POST['script_name']) . "</h3><pre>" . $e->getMessage() . "</pre>";
        }
    } else {
        $message = "<p>Fehler: Script nicht gefunden.</p>";
    }
}

// Alle verfügbaren DB-Initialisierungs-Scripte finden
$availableScripts = glob(__DIR__ . '/../../dbScripts/*.php');

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .script-list {
            list-style: none;
            padding: 0;
        }

        .script-list li {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px;
        }

        button {
            background: #333;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background: #555;
        }

        .output {
            background: #222;
            color: #0f0;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Admin Dashboard</h1>
            <a href="../../index.php">Zurück zur Hauptseite</a>
        </div>

        <p>Eingeloggt als: <strong><?php echo $_SESSION['email']; ?></strong></p>

        <hr>

        <h2>Datenbank Initialisierungs-Skripte</h2>
        <p>Diese Skripte setzen Teile der Datenbank zurück und initialisieren sie neu. Vorsicht beim Ausführen!</p>

        <ul class="script-list">
            <?php foreach ($availableScripts as $script): ?>
                <li>
                    <span><?php echo htmlspecialchars(basename($script)); ?></span>
                    <form method="POST" onsubmit="return confirm('Achtung! Sind Sie sicher, dass Sie das Skript <?php echo htmlspecialchars(basename($script)); ?> ausführen möchten? Dies kann Daten löschen.');">
                        <input type="hidden" name="script_name" value="<?php echo htmlspecialchars(basename($script)); ?>">
                        <button type="submit" name="run_script">Ausführen</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($message): ?>
            <div class="output"><?php echo $message; ?></div>
        <?php endif; ?>

    </div>

</body>

</html>
