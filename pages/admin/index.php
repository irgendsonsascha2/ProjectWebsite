<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/db.php';

// --- BERECHTIGUNGS-CHECK ---
// 1. Ist der User überhaupt eingeloggt?
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?page=login'); // Zum Login umleiten
    exit();
}

// 2. Hat der User die Admin-Rolle?
if ($_SESSION['role'] !== 'admin') {
    die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren.</p>");
}

$message = "";

function list_admin_db_scripts() {
    $scriptDir = __DIR__ . '/../../dbScripts';
    $scripts = glob($scriptDir . '/[0-9][0-9]*.php');
    sort($scripts);

    $masterScript = $scriptDir . '/db_init_master.php';
    if (is_file($masterScript)) {
        array_unshift($scripts, $masterScript);
    }

    return $scripts;
}

function admin_script_fields($scriptName) {
    if ($scriptName === 'db_init_master.php') {
        return [
            [
                'name' => 'seed_admin_email',
                'label' => 'Seed Admin E-Mail',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'admin@example.com'
            ],
            [
                'name' => 'seed_admin_username',
                'label' => 'Seed Admin Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'admin'
            ],
            [
                'name' => 'seed_admin_password',
                'label' => 'Seed Admin Passwort',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_viewer_db_password',
                'label' => 'MongoDB Passwort viewer',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_community_db_password',
                'label' => 'MongoDB Passwort community_member',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_content_manager_db_password',
                'label' => 'MongoDB Passwort content_manager',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_admin_db_password',
                'label' => 'MongoDB Passwort admin',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ]
        ];
    }

    $fields = [];

    if ($scriptName === '00_db_init_accounts.php') {
        $fields = [
            [
                'name' => 'seed_admin_email',
                'label' => 'Seed Admin E-Mail',
                'type' => 'email',
                'required' => true,
                'placeholder' => 'admin@example.com'
            ],
            [
                'name' => 'seed_admin_username',
                'label' => 'Seed Admin Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'admin'
            ],
            [
                'name' => 'seed_admin_password',
                'label' => 'Seed Admin Passwort',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ]
        ];
    }

    if ($scriptName === '03_db_init_mongo_roles.php') {
        $fields = [
            [
                'name' => 'mongo_viewer_db_password',
                'label' => 'MongoDB Passwort viewer',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_community_db_password',
                'label' => 'MongoDB Passwort community_member',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_content_manager_db_password',
                'label' => 'MongoDB Passwort content_manager',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ],
            [
                'name' => 'mongo_admin_db_password',
                'label' => 'MongoDB Passwort admin',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Passwort setzen'
            ]
        ];
    }

    return $fields;
}

function admin_script_input($name, $default = '') {
    $value = $_POST[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

$availableScripts = list_admin_db_scripts();
$allowedScriptNames = array_map('basename', $availableScripts);

// --- LOGIK: SCRIPT AUSFÜHREN ---
if (isset($_POST['run_script'])) {
    $requestedScript = basename((string)($_POST['script_name'] ?? ''));
    $scriptPath = __DIR__ . '/../../dbScripts/' . $requestedScript;

    if (in_array($requestedScript, $allowedScriptNames, true) && file_exists($scriptPath)) {
        // Output-Buffering, um die Ausgabe des Scripts abzufangen
        ob_start();

        try {
            // Die DB-Verbindung für das inkludierte Script bereitstellen
            if (!defined('ALLOW_DB_SCRIPT_EXECUTION')) {
                define('ALLOW_DB_SCRIPT_EXECUTION', true);
            }

            $GLOBALS['dbScriptInput'] = [
                'seed_admin_email' => admin_script_input('seed_admin_email'),
                'seed_admin_username' => admin_script_input('seed_admin_username'),
                'seed_admin_password' => admin_script_input('seed_admin_password'),
                'mongo_viewer_db_password' => admin_script_input('mongo_viewer_db_password'),
                'mongo_community_db_password' => admin_script_input('mongo_community_db_password'),
                'mongo_content_manager_db_password' => admin_script_input('mongo_content_manager_db_password'),
                'mongo_admin_db_password' => admin_script_input('mongo_admin_db_password')
            ];

            [$client, $db] = get_admin_mongo_connection();
            include $scriptPath;

            $message = "<h3>Ergebnis für: " . htmlspecialchars($requestedScript) . "</h3><pre>" . ob_get_clean() . "</pre>";
        } catch (Exception $e) {
            ob_end_clean(); // Buffer leeren im Fehlerfall
            $message = "<h3>Fehler in " . htmlspecialchars($requestedScript) . "</h3><pre>" . $e->getMessage() . "</pre>";
        }
    } else {
        $message = "<p>Fehler: Script nicht gefunden.</p>";
    }
}

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
                        <?php $scriptName = basename($script); ?>
                        <?php $scriptFields = admin_script_fields($scriptName); ?>
                        <?php if (count($scriptFields) > 0): ?>
                            <button type="button" data-dialog-open="script-run-<?php echo htmlspecialchars($scriptName); ?>">Ausführen</button>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Achtung! Sind Sie sicher, dass Sie das Skript <?php echo htmlspecialchars($scriptName); ?> ausführen möchten? Dies kann Daten löschen.');">
                                <input type="hidden" name="script_name" value="<?php echo htmlspecialchars($scriptName); ?>">
                                <button type="submit" name="run_script">Ausführen</button>
                            </form>
                        <?php endif; ?>
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

                    <?php if (count($scriptFields) > 0): ?>
                        <dialog id="script-run-<?php echo htmlspecialchars($scriptName); ?>">
                            <div class="dialog-card">
                                <div class="dialog-header">
                                    <h2><?php echo htmlspecialchars($scriptName); ?> ausführen</h2>
                                    <button type="button" class="close-button" data-dialog-close>Schließen</button>
                                </div>
                                <form method="POST" onsubmit="return confirm('Achtung! Sind Sie sicher, dass Sie das Skript <?php echo htmlspecialchars($scriptName); ?> ausführen möchten? Dies kann Daten löschen.');">
                                    <input type="hidden" name="script_name" value="<?php echo htmlspecialchars($scriptName); ?>">
                                    <?php foreach ($scriptFields as $field): ?>
                                        <p>
                                            <label>
                                                <?php echo htmlspecialchars($field['label']); ?><br>
                                                <input
                                                    type="<?php echo htmlspecialchars($field['type']); ?>"
                                                    name="<?php echo htmlspecialchars($field['name']); ?>"
                                                    <?php if (!empty($field['placeholder'])): ?>
                                                        placeholder="<?php echo htmlspecialchars($field['placeholder']); ?>"
                                                    <?php endif; ?>
                                                    <?php if (!empty($field['required'])): ?>
                                                        required
                                                    <?php endif; ?>
                                                >
                                            </label>
                                        </p>
                                    <?php endforeach; ?>
                                    <button type="submit" name="run_script">Ausführen</button>
                                </form>
                            </div>
                        </dialog>
                    <?php endif; ?>
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
        const forms = Array.from(document.querySelectorAll('dialog form[method="POST"]'));

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

        forms.forEach((form) => {
            form.addEventListener('submit', () => {
                const dialog = form.closest('dialog');
                if (!dialog) return;
                dialog.close();
            });
        });
    })();
    </script>

</body>

</html>
