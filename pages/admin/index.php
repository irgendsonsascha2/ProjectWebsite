<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// --- BERECHTIGUNGS-CHECK ---
// 1. Ist der User überhaupt eingeloggt?
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?page=account'); // Zum Login umleiten
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

        .script-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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

        .icon-button {
            background: #f2f2f2;
            color: #111;
            border: 1px solid #ccc;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 16px;
        }

        .icon-button:hover {
            background: #e7e7e7;
        }

        dialog {
            border: none;
            border-radius: 10px;
            padding: 0;
            width: min(900px, 92vw);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
        }

        dialog::backdrop {
            background: rgba(0, 0, 0, 0.4);
        }

        .dialog-card {
            padding: 18px;
        }

        .dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .dialog-header h2 {
            margin: 0;
        }

        .close-button {
            background: #666;
        }

        .code-block {
            background: #111;
            color: #eaeaea;
            padding: 14px;
            border-radius: 8px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 60vh;
            overflow: auto;
        }

        .admin-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 10px 0 20px;
        }

        .admin-nav a {
            text-decoration: none;
            color: #111;
            background: #f2f2f2;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .admin-nav a:hover {
            background: #e9e9e9;
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

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="roles.php">Rollen</a>
            <a href="permissions.php">Berechtigungen</a>
            <a href="../../index.php">Zur Hauptseite</a>
        </div>

        <hr>

        <h2>Datenbank Initialisierungs-Skripte</h2>
        <p>Diese Skripte setzen Teile der Datenbank zurück und initialisieren sie neu. Vorsicht beim Ausführen!</p>

        <ul class="script-list">
            <?php foreach ($availableScripts as $script): ?>
                <li>
                    <span><?php echo htmlspecialchars(basename($script)); ?></span>
                    <div class="script-actions">
                        <button type="button" class="icon-button" data-dialog-open="script-info-<?php echo htmlspecialchars(basename($script)); ?>" aria-label="Skript anzeigen" title="Skript anzeigen">ℹ</button>
                        <form method="POST" onsubmit="return confirm('Achtung! Sind Sie sicher, dass Sie das Skript <?php echo htmlspecialchars(basename($script)); ?> ausführen möchten? Dies kann Daten löschen.');">
                            <input type="hidden" name="script_name" value="<?php echo htmlspecialchars(basename($script)); ?>">
                            <button type="submit" name="run_script">Ausführen</button>
                        </form>
                    </div>

                    <dialog id="script-info-<?php echo htmlspecialchars(basename($script)); ?>">
                        <div class="dialog-card">
                            <div class="dialog-header">
                                <h2><?php echo htmlspecialchars(basename($script)); ?></h2>
                                <button type="button" class="close-button" data-dialog-close>Schließen</button>
                            </div>
                            <div class="code-block"><?php echo htmlspecialchars(file_get_contents($script)); ?></div>
                        </div>
                    </dialog>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($message): ?>
            <div class="output"><?php echo $message; ?></div>
        <?php endif; ?>

    </div>

    <script>
    (() => {
        const openButtons = Array.from(document.querySelectorAll('[data-dialog-open]'));
        const closeButtons = Array.from(document.querySelectorAll('[data-dialog-close]'));
        const dialogs = Array.from(document.querySelectorAll('dialog'));

        openButtons.forEach((button) => {
            const dialogId = button.dataset.dialogOpen;
            const dialog = dialogId ? document.getElementById(dialogId) : null;
            if (!dialog || typeof dialog.showModal !== 'function') return;
            button.addEventListener('click', () => {
                dialog.showModal();
            });
        });

        closeButtons.forEach((button) => {
            const dialog = button.closest('dialog');
            if (!dialog) return;
            button.addEventListener('click', () => {
                dialog.close();
            });
        });

        dialogs.forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });
    })();
    </script>

</body>

</html>
