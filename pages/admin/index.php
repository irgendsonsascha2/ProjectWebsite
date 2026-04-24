<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/vite_assets.php';

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
            ],
            [
                'name' => 'update_env_local',
                'label' => 'Auch .env.local aktualisieren (URIs mit neuen Passwörtern)',
                'type' => 'checkbox',
                'required' => false,
                'placeholder' => ''
            ]
        ];
    }

    return $fields;
}

function admin_script_input($name, $default = '') {
    $value = $_POST[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function admin_script_checkbox($name) {
    $v = $_POST[$name] ?? null;
    return $v === '1' || $v === 1 || $v === true || $v === 'on';
}

function update_env_local_file($updates) {
    if (!is_array($updates) || count($updates) === 0) {
        return;
    }

    $root = realpath(__DIR__ . '/../../');
    if (!$root) {
        throw new RuntimeException('Projektroot konnte nicht aufgelöst werden.');
    }
    $path = $root . '/.env.local';

    $lines = [];
    if (is_file($path) && is_readable($path)) {
        $existing = file($path, FILE_IGNORE_NEW_LINES);
        if (is_array($existing)) {
            $lines = $existing;
        }
    }

    $seen = [];
    $out = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '#') || strpos($trim, '=') === false) {
            $out[] = $line;
            continue;
        }
        $eqPos = strpos($line, '=');
        $key = trim(substr($line, 0, $eqPos));
        if ($key === '') {
            $out[] = $line;
            continue;
        }
        if (array_key_exists($key, $updates)) {
            $seen[$key] = true;
            $out[] = $key . '=' . $updates[$key];
        } else {
            $out[] = $line;
        }
    }

    foreach ($updates as $k => $v) {
        if (!isset($seen[$k])) {
            $out[] = $k . '=' . $v;
        }
    }

    $content = implode("\n", $out) . "\n";
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Konnte .env.local nicht schreiben.');
    }
}

$availableScripts = list_admin_db_scripts();
$allowedScriptNames = array_map('basename', $availableScripts);

function env_is_set($key) {
    $v = getenv($key);
    return $v !== false && trim((string)$v) !== '';
}

function admin_effective_db_config_html() {
    $cfg = mongo_config();
    $rows = [
        ['APP_DB_NAME', $cfg['db_name'], env_is_set('APP_DB_NAME')],
        ['APP_DB_URI', mask_mongo_uri($cfg['app_uri']), env_is_set('APP_DB_URI')],
        ['VIEWER_DB_URI', mask_mongo_uri($cfg['viewer_uri']), env_is_set('VIEWER_DB_URI')],
        ['COMMUNITY_DB_URI', mask_mongo_uri($cfg['community_member_uri']), env_is_set('COMMUNITY_DB_URI')],
        ['CONTENT_MANAGER_DB_URI', mask_mongo_uri($cfg['content_manager_uri']), env_is_set('CONTENT_MANAGER_DB_URI')],
        ['ADMIN_DB_URI', mask_mongo_uri($cfg['admin_uri']), env_is_set('ADMIN_DB_URI')],
    ];

    $html = '<div class="admin-card"><h2>Effektive DB-Konfiguration (laufender PHP-Prozess)</h2>';
    $html .= '<p class="muted">Passwörter sind maskiert. „Env gesetzt“ heißt: die Variable ist im PHP-Prozess wirklich vorhanden (nicht nur in deiner Shell).</p>';
    $html .= '<div class="code-block"><pre>';
    foreach ($rows as $r) {
        [$name, $value, $isSet] = $r;
        $flag = $isSet ? 'yes' : 'no';
        $html .= htmlspecialchars(str_pad($name, 24)) . ' = ' . htmlspecialchars((string)$value) . '   [env: ' . $flag . ']' . "\n";
    }
    $html .= '</pre></div></div>';
    return $html;
}

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

            // Force DB scripts to run with the configured ADMIN_DB_URI.
            // If the password is provided via dialog, use it for this connection too,
            // so the script can't accidentally run with a stale default password.
            $cfg = mongo_config();
            $adminUri = $cfg['admin_uri'];
            $adminPass = $GLOBALS['dbScriptInput']['mongo_admin_db_password'] ?? '';
            $adminUri = mongo_uri_with_password($adminUri, $adminPass);

            [$client, $db] = create_mongo_connection($adminUri);
            include $scriptPath;

            if ($requestedScript === '03_db_init_mongo_roles.php' && admin_script_checkbox('update_env_local')) {
                $cfg = mongo_config();
                $updates = [
                    'APP_DB_NAME' => $cfg['db_name'],
                    'VIEWER_DB_URI' => mongo_uri_with_password($cfg['viewer_uri'], $GLOBALS['dbScriptInput']['mongo_viewer_db_password'] ?? ''),
                    'COMMUNITY_DB_URI' => mongo_uri_with_password($cfg['community_member_uri'], $GLOBALS['dbScriptInput']['mongo_community_db_password'] ?? ''),
                    'CONTENT_MANAGER_DB_URI' => mongo_uri_with_password($cfg['content_manager_uri'], $GLOBALS['dbScriptInput']['mongo_content_manager_db_password'] ?? ''),
                    'ADMIN_DB_URI' => mongo_uri_with_password($cfg['admin_uri'], $GLOBALS['dbScriptInput']['mongo_admin_db_password'] ?? ''),
                ];
                update_env_local_file($updates);
                echo "<br>✅ <b>.env.local</b> wurde aktualisiert (Passwörter gesetzt).<br>";
            }

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
    <script>
        (function () {
            try {
                var KEY = 'portfolio-theme';
                var t = localStorage.getItem(KEY);
                if (t !== 'dark' && t !== 'light') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <?php vite_react_assets('src/main.tsx'); ?>
</head>

<body class="admin-page">

    <div class="container">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
        </div>

        <p>Eingeloggt als: <strong><?php echo $_SESSION['email']; ?></strong></p>

        <?php echo admin_effective_db_config_html(); ?>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="registration_requests.php">Registrierungsanfragen</a>
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
                                            <?php if (($field['type'] ?? '') === 'checkbox'): ?>
                                                <label>
                                                    <input type="checkbox" name="<?php echo htmlspecialchars($field['name']); ?>" value="1" checked>
                                                    <?php echo htmlspecialchars($field['label']); ?>
                                                </label>
                                            <?php else: ?>
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
                                            <?php endif; ?>
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
