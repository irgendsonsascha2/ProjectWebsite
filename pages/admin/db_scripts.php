<?php

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/input_validate.php';
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
    $isDestructive = schema_migration_is_destructive($scriptName);
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

    if ($scriptName === '09_db_init_site_settings.php') {
        $fields = [
            [
                'name' => 'reset_site_settings_defaults',
                'label' => 'Alle Werte auf Standard zurücksetzen (sonst nur fehlende Felder ergänzen, z. B. Website-Name)',
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
    $raw = $_POST[$name] ?? $default;
    if (! is_string($raw)) {
        return $default;
    }

    return trim($raw);
}

function admin_script_email_input(string $name): string
{
    $raw = $_POST[$name] ?? null;

    return input_email(is_string($raw) ? $raw : null) ?? '';
}

function admin_script_password_input(string $name): string
{
    $raw = $_POST[$name] ?? null;
    if (! is_string($raw)) {
        return '';
    }

    return input_password_secret($raw, 8) ?? '';
}

/**
 * Grund, warum ein Betriebspasswort (Seed-Admin, Mongo-Rollen) abgelehnt wurde; null = gültig.
 */
function admin_script_secret_password_failure_reason(?string $raw, int $minLen = 8): ?string
{
    if (! is_string($raw) || $raw === '') {
        return 'fehlt';
    }
    if (str_contains($raw, "\0")) {
        return 'enthält ungültige Zeichen (NUL)';
    }
    $len = mb_strlen($raw, 'UTF-8');
    if ($len < $minLen) {
        return 'mindestens '.$minLen.' Zeichen (eingegeben: '.$len.')';
    }
    if ($len > 512) {
        return 'maximal 512 Zeichen';
    }

    return null;
}

function admin_script_seed_email_failure_reason(?string $raw): ?string
{
    if (! is_string($raw) || trim($raw) === '') {
        return 'fehlt';
    }
    if (input_email($raw) !== null) {
        return null;
    }

    return 'keine gültige E-Mail-Adresse';
}

function admin_script_seed_username_failure_reason(?string $raw): ?string
{
    if (! is_string($raw) || trim($raw) === '') {
        return 'fehlt';
    }
    if (input_slug_key($raw, 64) !== null) {
        return null;
    }

    return '2–64 Zeichen, Kleinbuchstaben a–z, Ziffern und Unterstrich, beginnt mit Buchstabe';
}

function admin_script_mongo_ping_uri(string $uri): bool
{
    try {
        [, $db] = create_mongo_connection($uri);
        $db->command(['ping' => 1]);

        return true;
    } catch (Throwable) {
        return false;
    }
}

function admin_script_mongo_bootstrap_uri(string $uri): ?string
{
    if (! function_exists('app_environment') || app_environment() !== 'local') {
        return null;
    }
    $cfg = mongo_config();
    $parsed = parse_url($uri);
    $host = $parsed['host'] ?? '127.0.0.1';
    $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
    $dbName = $cfg['db_name'];
    if (isset($parsed['path']) && is_string($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
        $dbName = ltrim($parsed['path'], '/');
    }

    return 'mongodb://'.$host.$port.'/'.$dbName;
}

/**
 * Master-Lauf: Formular setzt Mongo-Passwörter erst in 03_* — Verbindung für 00–02 braucht bestehende Credentials.
 *
 * @return array{0: string, 1: string} [uri, source: form|env|bootstrap_no_auth|unverified]
 */
function admin_script_resolve_master_admin_uri(string $adminUri, string $formAdminPass): array
{
    if ($formAdminPass !== '') {
        $withForm = mongo_uri_with_password($adminUri, $formAdminPass);
        if (admin_script_mongo_ping_uri($withForm)) {
            return [$withForm, 'form'];
        }
    }
    if (admin_script_mongo_ping_uri($adminUri)) {
        return [$adminUri, 'env'];
    }
    $bootstrap = admin_script_mongo_bootstrap_uri($adminUri);
    if ($bootstrap !== null && admin_script_mongo_ping_uri($bootstrap)) {
        return [$bootstrap, 'bootstrap_no_auth'];
    }

    $fallback = $formAdminPass !== ''
        ? mongo_uri_with_password($adminUri, $formAdminPass)
        : $adminUri;

    return [$fallback, 'unverified'];
}

/** @return array<string, string> POST key => Kurzlabel für Fehlermeldungen */
function admin_script_mongo_password_field_labels(): array
{
    return [
        'mongo_viewer_db_password' => 'viewer',
        'mongo_community_db_password' => 'community_member',
        'mongo_content_manager_db_password' => 'content_manager',
        'mongo_admin_db_password' => 'admin',
    ];
}

function admin_script_require_mongo_passwords(): void
{
    $labels = admin_script_mongo_password_field_labels();
    $missing = [];
    $invalid = [];
    foreach ($labels as $key => $label) {
        if (($GLOBALS['dbScriptInput'][$key] ?? '') !== '') {
            continue;
        }
        $raw = $_POST[$key] ?? null;
        $reason = admin_script_secret_password_failure_reason(is_string($raw) ? $raw : null);
        if ($reason === 'fehlt') {
            $missing[] = $label;
        } elseif ($reason !== null) {
            $invalid[] = $label.' ('.$reason.')';
        }
    }
    if ($missing === [] && $invalid === []) {
        return;
    }
    $parts = [];
    if ($missing !== []) {
        $parts[] = 'nicht ausgefüllt: '.implode(', ', $missing);
    }
    if ($invalid !== []) {
        $parts[] = 'ungültig: '.implode(', ', $invalid);
    }
    throw new RuntimeException(
        'MongoDB-Passwörter — '.implode('; ', $parts)
        .'. Alle vier Rollen-Felder sind Pflicht (dasselbe Passwort in jedes Feld ist erlaubt).'
    );
}

function admin_script_require_seed_admin(): void
{
    $checks = [
        'seed_admin_email' => [
            'value' => $GLOBALS['dbScriptInput']['seed_admin_email'] ?? '',
            'label' => 'E-Mail',
            'reason' => static fn (?string $raw) => admin_script_seed_email_failure_reason($raw),
        ],
        'seed_admin_username' => [
            'value' => $GLOBALS['dbScriptInput']['seed_admin_username'] ?? '',
            'label' => 'Username',
            'reason' => static fn (?string $raw) => admin_script_seed_username_failure_reason($raw),
        ],
        'seed_admin_password' => [
            'value' => $GLOBALS['dbScriptInput']['seed_admin_password'] ?? '',
            'label' => 'Passwort',
            'reason' => static fn (?string $raw) => admin_script_secret_password_failure_reason($raw, 8),
        ],
    ];
    $bad = [];
    foreach ($checks as $key => $meta) {
        if ($meta['value'] !== '') {
            continue;
        }
        $raw = $_POST[$key] ?? null;
        $reason = ($meta['reason'])(is_string($raw) ? $raw : null);
        $bad[] = $meta['label'].' ('.($reason ?? 'ungültig').')';
    }
    if ($bad === []) {
        return;
    }
    throw new RuntimeException('Seed-Admin-Daten prüfen: '.implode(', ', $bad).'.');
}

function admin_script_username_input(string $name): string
{
    $raw = $_POST[$name] ?? null;

    return input_slug_key(is_string($raw) ? $raw : null, 64) ?? '';
}

function admin_script_checkbox($name) {
    $v = $_POST[$name] ?? null;
    return $v === '1' || $v === 1 || $v === true || $v === 'on';
}

function admin_script_require_destructive_allowed(string $scriptName): void
{
    if (!schema_migration_is_destructive($scriptName)) {
        return;
    }
    if (!schema_migration_destructive_allowed()) {
        throw new RuntimeException('Destruktive Skripte sind deaktiviert. Setze ALLOW_DESTRUCTIVE_DB_SCRIPTS=1 (nur lokal) um fortzufahren.');
    }
}

function update_env_local_file($updates) {
    if (!is_array($updates) || count($updates) === 0) {
        return;
    }

    foreach ($updates as $k => $v) {
        if (!is_string($k) || $k === '') {
            throw new RuntimeException('Ungültiger Env-Key.');
        }
        if (!is_string($v)) {
            throw new RuntimeException('Ungültiger Env-Value für '.$k.'.');
        }
        if (preg_match('/[\\x00-\\x1F\\x7F]/', $v)) {
            throw new RuntimeException('Ungültige Zeichen im Wert für '.$k.' (Control-Characters).');
        }
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
            $out[] = $key . '=' . '"' . addcslashes($updates[$key], "\\\"") . '"';
        } else {
            $out[] = $line;
        }
    }

    foreach ($updates as $k => $v) {
        if (!isset($seen[$k])) {
            $out[] = $k . '=' . '"' . addcslashes($v, "\\\"") . '"';
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

    $requestedScript = input_db_script_basename(
        is_string($_POST['script_name'] ?? null) ? $_POST['script_name'] : null
    );
    $scriptPath = $requestedScript !== null
        ? __DIR__.'/../../dbScripts/'.$requestedScript
        : '';

    if ($requestedScript !== null && in_array($requestedScript, $allowedScriptNames, true) && file_exists($scriptPath)) {
        ob_start();
        try {
            if (!defined('ALLOW_DB_SCRIPT_EXECUTION')) {
                define('ALLOW_DB_SCRIPT_EXECUTION', true);
            }

            admin_script_require_destructive_allowed($requestedScript);

            $GLOBALS['dbScriptInput'] = [
                'seed_admin_email' => admin_script_email_input('seed_admin_email'),
                'seed_admin_username' => admin_script_username_input('seed_admin_username'),
                'seed_admin_password' => admin_script_password_input('seed_admin_password'),
                'mongo_viewer_db_password' => admin_script_password_input('mongo_viewer_db_password'),
                'mongo_community_db_password' => admin_script_password_input('mongo_community_db_password'),
                'mongo_content_manager_db_password' => admin_script_password_input('mongo_content_manager_db_password'),
                'mongo_admin_db_password' => admin_script_password_input('mongo_admin_db_password'),
                'backfill_migration_log' => admin_script_checkbox('backfill_migration_log'),
                'reset_site_settings_defaults' => admin_script_checkbox('reset_site_settings_defaults'),
            ];

            if ($requestedScript === '00_db_init_accounts.php' || $requestedScript === 'db_init_master.php') {
                admin_script_require_seed_admin();
            }
            if ($requestedScript === '03_db_init_mongo_roles.php' || $requestedScript === 'db_init_master.php') {
                admin_script_require_mongo_passwords();
            }

            $cfg = mongo_config();
            $adminUri = $cfg['admin_uri'];
            $adminPass = $GLOBALS['dbScriptInput']['mongo_admin_db_password'] ?? '';
            $masterConnSource = 'form_inject';
            if ($requestedScript === 'db_init_master.php') {
                [$adminUri, $masterConnSource] = admin_script_resolve_master_admin_uri($adminUri, $adminPass);
            } else {
                $adminUri = mongo_uri_with_password($adminUri, $adminPass);
            }

            if ($requestedScript === 'db_init_master.php' && $masterConnSource === 'env') {
                echo '<p><i>ℹ️ Master-Verbindung nutzt <code>ADMIN_DB_URI</code> aus .env.local — die Mongo-Passwörter aus dem Formular werden in <code>03_db_init_mongo_roles.php</code> gesetzt.</i></p>';
            } elseif ($requestedScript === 'db_init_master.php' && $masterConnSource === 'bootstrap_no_auth') {
                echo '<p><i>ℹ️ Master-Verbindung ohne MongoDB-Auth (Erst-Initialisierung).</i></p>';
            }

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

