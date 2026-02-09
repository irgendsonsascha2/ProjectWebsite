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
    <link rel="stylesheet" href="../../style/admin_index.css">
</head>

<body>

    <div class="container">
        <div class="page-header">
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
