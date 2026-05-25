<?php

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';
require_once __DIR__ . '/../../includes/schema_migrations.php';

// The DB scripts page is intentionally separate from the dashboard.

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
            ['name' => 'seed_admin_email', 'label' => 'Seed Admin E-Mail', 'type' => 'email', 'required' => true, 'placeholder' => 'admin@example.com'],
            ['name' => 'seed_admin_username', 'label' => 'Seed Admin Username', 'type' => 'text', 'required' => true, 'placeholder' => 'admin'],
            ['name' => 'seed_admin_password', 'label' => 'Seed Admin Passwort', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_viewer_db_password', 'label' => 'MongoDB Passwort viewer', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_community_db_password', 'label' => 'MongoDB Passwort community_member', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_content_manager_db_password', 'label' => 'MongoDB Passwort content_manager', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_admin_db_password', 'label' => 'MongoDB Passwort admin', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
        ];
    }

    $fields = [];

    if ($scriptName === '00_db_init_accounts.php') {
        $fields = [
            ['name' => 'seed_admin_email', 'label' => 'Seed Admin E-Mail', 'type' => 'email', 'required' => true, 'placeholder' => 'admin@example.com'],
            ['name' => 'seed_admin_username', 'label' => 'Seed Admin Username', 'type' => 'text', 'required' => true, 'placeholder' => 'admin'],
            ['name' => 'seed_admin_password', 'label' => 'Seed Admin Passwort', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
        ];
    }

    if ($scriptName === '16_db_init_schema_migrations.php') {
        $fields = [
            [
                'name' => 'backfill_migration_log',
                'label' => 'Protokoll auffüllen (fehlende Einträge für 03_…–16_, z. B. nach älterem Master-Lauf)',
                'type' => 'checkbox',
                'required' => false,
                'placeholder' => '',
            ],
        ];
    }

    if ($scriptName === '03_db_init_mongo_roles.php') {
        $fields = [
            ['name' => 'mongo_viewer_db_password', 'label' => 'MongoDB Passwort viewer', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_community_db_password', 'label' => 'MongoDB Passwort community_member', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_content_manager_db_password', 'label' => 'MongoDB Passwort content_manager', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'mongo_admin_db_password', 'label' => 'MongoDB Passwort admin', 'type' => 'password', 'required' => true, 'placeholder' => 'Passwort setzen'],
            ['name' => 'update_env_local', 'label' => 'Auch .env.local aktualisieren (URIs mit neuen Passwörtern)', 'type' => 'checkbox', 'required' => false, 'placeholder' => ''],
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

$message = "";
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

// --- LOGIK: SCRIPT AUSFÜHREN ---
if (isset($_POST['run_script'])) {
    if (! csrf_verify()) {
        $message = '<p class="alert alert--error">Formular ungültig oder Sitzung abgelaufen (CSRF).</p>';
    } else {
    $reauth = admin_reauth_require_fresh_or_post();
    if (! $reauth['ok']) {
        $message = '<p class="alert alert--error">'.htmlspecialchars($reauth['error'], ENT_QUOTES, 'UTF-8').'</p>';
        $adminReauthFresh = false;
    } else {
    $adminReauthFresh = admin_reauth_is_fresh();
    $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

    $requestedScript = basename((string)($_POST['script_name'] ?? ''));
    $scriptPath = __DIR__ . '/../../dbScripts/' . $requestedScript;

    if (in_array($requestedScript, $allowedScriptNames, true) && file_exists($scriptPath)) {
        ob_start();
        try {
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
                'mongo_admin_db_password' => admin_script_input('mongo_admin_db_password'),
                'backfill_migration_log' => admin_script_checkbox('backfill_migration_log'),
            ];

            $cfg = mongo_config();
            $adminUri = $cfg['admin_uri'];
            $adminPass = $GLOBALS['dbScriptInput']['mongo_admin_db_password'] ?? '';
            $adminUri = mongo_uri_with_password($adminUri, $adminPass);

            [$client, $db] = create_mongo_connection($adminUri);
            include $scriptPath;

            if (schema_migration_trackable($requestedScript)) {
                schema_migration_record($db, $requestedScript, schema_migration_actor_for_run());
                echo '<br>📋 Migration in <code>schema_migrations</code> protokolliert.<br>';
            }

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
            ob_end_clean();
            $message = "<h3>Fehler in " . htmlspecialchars($requestedScript) . "</h3><pre>" . $e->getMessage() . "</pre>";
        }
    } else {
        $message = "<p>Fehler: Script nicht gefunden.</p>";
    }
    }
    }
}

$migrationAppliedMap = [];
try {
    [, $migrationDb] = get_admin_mongo_connection();
    $migrationAppliedMap = schema_migration_applied_map($migrationDb);
} catch (Throwable $e) {
    $migrationAppliedMap = [];
}

admin_render_page('DB-Skripte', 'db_scripts', function () use ($availableScripts, $message, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft, $migrationAppliedMap) { ?>
    <div class="page-header">
        <h1>DB-Skripte</h1>
    </div>

    <p class="muted">Diese Skripte setzen Teile der Datenbank zurück und initialisieren sie neu. Vorsicht beim Ausführen!</p>
    <p class="muted">Skripte <code>00</code>–<code>02</code> werden nicht protokolliert. Ab <code>03</code> nach Ausführung (einzeln oder via <code>db_init_master</code>) Eintrag in <code>schema_migrations</code>. Älterer Master ohne Protokoll: <code>16_…</code> mit Checkbox „Protokoll auffüllen“. Status: <a href="deploy_status.php">Deploy-Status</a>.</p>

    <?php if ($adminReauthFresh): ?>
        <p class="alert">Admin-Bestätigung aktiv — noch ca. <?php echo (int) $reauthMinutesLeft; ?> Min. gültig. Danach erneut Passwort<?php echo $adminReauthNeeds2fa ? ' und 2FA-Code' : ''; ?> eingeben.</p>
    <?php endif; ?>

    <ul class="script-list">
        <?php foreach ($availableScripts as $script): ?>
            <li>
                <span class="script-list__title">
                    <?php echo htmlspecialchars(basename($script)); ?>
                    <?php echo schema_migration_status_badge_html(basename($script), $migrationAppliedMap); ?>
                </span>
                <div class="script-actions">
                    <button type="button" class="icon-button" data-dialog-open="script-info-<?php echo htmlspecialchars(basename($script)); ?>" aria-label="Skript anzeigen" title="Skript anzeigen">ℹ</button>
                    <?php $scriptName = basename($script); ?>
                    <button type="button" data-dialog-open="script-run-<?php echo htmlspecialchars($scriptName); ?>">Ausführen</button>
                </div>

                <dialog id="script-info-<?php echo htmlspecialchars(basename($script)); ?>">
                    <div class="dialog-card">
                        <div class="dialog-header">
                            <h2><?php echo htmlspecialchars(basename($script)); ?></h2>
                            <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                        </div>
                        <div class="code-block"><?php echo htmlspecialchars(file_get_contents($script)); ?></div>
                    </div>
                </dialog>

                <?php $scriptFields = admin_script_fields($scriptName); ?>
                <dialog id="script-run-<?php echo htmlspecialchars($scriptName); ?>">
                    <div class="dialog-card">
                        <div class="dialog-header">
                            <h2><?php echo htmlspecialchars($scriptName); ?> ausführen</h2>
                            <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                        </div>
                        <form method="POST" data-dialog-close-on-submit data-confirm-submit="Achtung! Sind Sie sicher, dass Sie das Skript <?php echo htmlspecialchars($scriptName, ENT_QUOTES, 'UTF-8'); ?> ausführen möchten? Dies kann Daten löschen.">
                            <?php echo csrf_field(); ?>
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
                            <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                            <button type="submit" name="run_script">Ausführen</button>
                        </form>
                    </div>
                </dialog>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($message): ?>
        <div class="output"><?php echo $message; ?></div>
    <?php endif; ?>
<?php });

