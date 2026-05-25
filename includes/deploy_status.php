<?php

declare(strict_types=1);

require_once __DIR__.'/app_env.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/schema_migrations.php';

if (!function_exists('deploy_status_project_root')) {
    function deploy_status_project_root(): string
    {
        $root = realpath(__DIR__.'/..');

        return $root !== false ? $root : dirname(__DIR__);
    }
}

if (!function_exists('deploy_status_env_is_set')) {
    function deploy_status_env_is_set(string $key): bool
    {
        $v = getenv($key);

        return $v !== false && trim((string) $v) !== '';
    }
}

if (!function_exists('deploy_status_laravel_env_value')) {
    function deploy_status_laravel_env_value(string $key): ?string
    {
        $path = deploy_status_project_root().'/laravel/.env';
        if (!is_readable($path)) {
            return null;
        }
        $pattern = '/^'.preg_quote($key, '/').'=(.+)$/';
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match($pattern, $line, $m)) {
                return trim($m[1], " \t\n\r\0\x0B\"'");
            }
        }

        return null;
    }
}

if (!function_exists('deploy_status_git_short_hash')) {
    function deploy_status_git_short_hash(string $root): ?string
    {
        if (!is_dir($root.'/.git')) {
            return null;
        }
        $cmd = 'git -C '.escapeshellarg($root).' rev-parse --short HEAD 2>/dev/null';
        $out = trim((string) @shell_exec($cmd));

        return $out !== '' ? $out : null;
    }
}

if (!function_exists('deploy_status_version_file')) {
    function deploy_status_version_file(string $root): ?string
    {
        $path = $root.'/version.txt';
        if (!is_readable($path)) {
            return null;
        }
        $line = trim((string) file_get_contents($path));

        return $line !== '' ? $line : null;
    }
}

if (!function_exists('deploy_status_react_dist_ok')) {
    function deploy_status_react_dist_ok(string $root): array
    {
        $manifest = $root.'/react-dist/.vite/manifest.json';
        if (!is_readable($manifest)) {
            return ['ok' => false, 'detail' => 'react-dist/.vite/manifest.json fehlt (npm run build nötig)'];
        }
        $data = json_decode((string) file_get_contents($manifest), true);
        if (!is_array($data) || $data === []) {
            return ['ok' => false, 'detail' => 'Manifest leer oder ungültig'];
        }
        $keys = array_keys($data);

        return ['ok' => true, 'detail' => 'Manifest OK ('.count($keys).' Einträge)'];
    }
}

if (!function_exists('deploy_status_dir_writable')) {
    function deploy_status_dir_writable(string $root, string $relative): bool
    {
        $path = $root.'/'.$relative;
        if (!is_dir($path)) {
            return false;
        }

        return is_writable($path);
    }
}

if (!function_exists('deploy_status_check')) {
    /**
     * @param 'ok'|'warn'|'fail' $status
     */
    function deploy_status_check(string $id, string $label, string $status, string $detail, string $hint = ''): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'hint' => $hint,
        ];
    }
}

if (!function_exists('deploy_status_collect_checks')) {
    /**
     * @return array<int, array{id: string, label: string, status: string, detail: string, hint: string}>
     */
    function deploy_status_collect_checks(): array
    {
        $root = deploy_status_project_root();
        $checks = [];
        $isProd = app_is_production();

        $react = deploy_status_react_dist_ok($root);
        $checks[] = deploy_status_check(
            'react_dist',
            'Frontend-Build (react-dist/)',
            $react['ok'] ? 'ok' : 'fail',
            $react['detail'],
            'cd frontend && npm install && npm run build'
        );

        $laravelEnv = is_readable($root.'/laravel/.env');
        $appEnv = deploy_status_laravel_env_value('APP_ENV') ?? '(nicht gesetzt)';
        $appDebug = deploy_status_laravel_env_value('APP_DEBUG') ?? '(nicht gesetzt)';
        $checks[] = deploy_status_check(
            'laravel_env',
            'Laravel-Konfiguration (laravel/.env)',
            $laravelEnv ? 'ok' : 'warn',
            $laravelEnv
                ? 'APP_ENV='.$appEnv.', APP_DEBUG='.$appDebug
                : 'laravel/.env nicht lesbar',
            'Vorlage: laravel/.env.example'
        );

        if ($isProd && $laravelEnv && strtolower($appDebug) === 'true') {
            $checks[count($checks) - 1]['status'] = 'fail';
            $checks[count($checks) - 1]['detail'] .= ' — APP_DEBUG sollte in Produktion false sein';
        }

        $handoff = deploy_status_laravel_env_value('HANDOFF_SECRET') ?? '';
        $handoffOk = strlen($handoff) >= 32;
        $checks[] = deploy_status_check(
            'handoff_secret',
            'HANDOFF_SECRET (Laravel)',
            $handoffOk ? 'ok' : ($laravelEnv ? 'fail' : 'warn'),
            $handoffOk ? 'gesetzt ('.strlen($handoff).' Zeichen)' : 'fehlt oder zu kurz (< 32 Zeichen)',
            'In laravel/.env setzen; muss mit Handoff-Brücke übereinstimmen'
        );

        $mongoCheck = deploy_status_mongo_uri_check();
        $checks[] = deploy_status_check(
            'mongo_uris',
            'MongoDB-Verbindungen (effektiv)',
            $mongoCheck['status'],
            $mongoCheck['detail'],
            $mongoCheck['hint']
        );

        $devDefaults = app_allow_dev_db_defaults();
        $checks[] = deploy_status_check(
            'dev_defaults',
            'APP_ALLOW_DEV_DB_DEFAULTS',
            ($isProd && $devDefaults) ? 'fail' : ($devDefaults ? 'warn' : 'ok'),
            $devDefaults ? 'aktiv (1/true/yes oder Default außerhalb production)' : 'nicht aktiv',
            $isProd ? 'In Produktion explizit 0 setzen' : 'Lokal oft OK'
        );

        $secure = session_cookie_is_secure();
        $secureEnv = deploy_status_env_is_set('SESSION_SECURE');
        $checks[] = deploy_status_check(
            'session_secure',
            'Sichere Session-Cookies',
            ($isProd && !$secure) ? 'warn' : ($secure ? 'ok' : 'warn'),
            $secure
                ? 'SESSION_SECURE oder HTTPS/Port 443'
                : ($secureEnv ? 'SESSION_SECURE gesetzt, aber nicht aktiv' : 'SESSION_SECURE nicht gesetzt'),
            $isProd ? 'SESSION_SECURE=1 hinter TLS empfohlen' : 'Lokal ohne TLS normal'
        );

        $checks[] = deploy_status_check(
            'php_ext_mongodb',
            'PHP MongoDB-Erweiterung',
            extension_loaded('mongodb') ? 'ok' : 'fail',
            'PHP '.PHP_VERSION.(extension_loaded('mongodb') ? ', ext/mongodb geladen' : ', ext/mongodb fehlt'),
            ''
        );

        $hash = deploy_status_git_short_hash($root);
        $versionFile = deploy_status_version_file($root);
        if ($hash !== null) {
            $checks[] = deploy_status_check('git_revision', 'Git-Revision', 'ok', $hash, 'Aus .git (read-only)');
        } elseif ($versionFile !== null) {
            $checks[] = deploy_status_check('version_file', 'Deploy-Version (version.txt)', 'ok', $versionFile, 'Optional bei Deploy ohne .git');
        } else {
            $checks[] = deploy_status_check(
                'git_revision',
                'Code-Revision',
                'warn',
                'Weder .git noch version.txt',
                'Beim Server-Deploy optional version.txt mit Commit schreiben'
            );
        }

        $dirs = [
            'content/images' => 'Bild-Uploads',
            'content/videos' => 'Video-Uploads',
            'logs' => 'Log-Dateien',
        ];
        $dirProblems = [];
        foreach ($dirs as $rel => $label) {
            if (!deploy_status_dir_writable($root, $rel)) {
                $dirProblems[] = $rel;
            }
        }
        $checks[] = deploy_status_check(
            'writable_dirs',
            'Schreibbare Verzeichnisse',
            $dirProblems === [] ? 'ok' : 'fail',
            $dirProblems === [] ? 'content/images, content/videos, logs' : 'Nicht beschreibbar: '.implode(', ', $dirProblems),
            ''
        );

        $migrationCheck = deploy_status_migration_tracking_check();
        $checks[] = deploy_status_check(
            'migration_tracking',
            'DB-Skripte (Ausführungs-Protokoll)',
            $migrationCheck['status'],
            $migrationCheck['detail'],
            $migrationCheck['hint']
        );

        $mailCheck = deploy_status_mail_smtp_check();
        $checks[] = deploy_status_check(
            'mail_smtp',
            'E-Mail (SMTP / TLS)',
            $mailCheck['status'],
            $mailCheck['detail'],
            $mailCheck['hint']
        );

        $laravelMailDev = deploy_status_laravel_mail_dev_check();
        if ($laravelMailDev !== null) {
            $checks[] = $laravelMailDev;
        }

        return $checks;
    }
}

if (!function_exists('deploy_status_mongo_uri_check')) {
    /**
     * @return array{status: string, detail: string, hint: string}
     */
    function deploy_status_mongo_uri_check(): array
    {
        $uriEnvMap = [
            'APP_DB_URI' => 'app_uri',
            'VIEWER_DB_URI' => 'viewer_uri',
            'COMMUNITY_DB_URI' => 'community_member_uri',
            'CONTENT_MANAGER_DB_URI' => 'content_manager_uri',
            'ADMIN_DB_URI' => 'admin_uri',
        ];

        try {
            $cfg = mongo_config();
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'detail' => 'mongo_config() fehlgeschlagen: '.$e->getMessage(),
                'hint' => 'URIs in .env.local setzen oder APP_ALLOW_DEV_DB_DEFAULTS=1 (nur lokal)',
            ];
        }

        $missingRequired = [];
        $devFallback = [];
        $lines = [];
        foreach ($uriEnvMap as $envKey => $cfgKey) {
            $fromEnv = deploy_status_env_is_set($envKey);
            $effective = mask_mongo_uri((string) ($cfg[$cfgKey] ?? ''));
            $lines[] = $envKey.' = '.$effective.' [env: '.($fromEnv ? 'ja' : 'nein').']';
            if (!$fromEnv) {
                if (app_allow_dev_db_defaults()) {
                    $devFallback[] = $envKey;
                } else {
                    $missingRequired[] = $envKey;
                }
            }
        }

        $dbName = (string) ($cfg['db_name'] ?? '');
        $lines[] = 'APP_DB_NAME = '.$dbName.' [env: '.(deploy_status_env_is_set('APP_DB_NAME') ? 'ja' : 'nein').']';

        if ($missingRequired !== []) {
            return [
                'status' => 'fail',
                'detail' => 'Fehlende Env-Variablen (ohne Dev-Default): '.implode(', ', $missingRequired).'. Effektiv: '.implode(' | ', $lines),
                'hint' => 'Werte in .env.local im Projektroot',
            ];
        }

        if ($devFallback !== []) {
            return [
                'status' => 'warn',
                'detail' => 'Läuft mit Dev-Defaults für: '.implode(', ', $devFallback).'. Effektiv: '.implode(' | ', $lines),
                'hint' => 'In Produktion alle URIs explizit setzen und APP_ALLOW_DEV_DB_DEFAULTS=0',
            ];
        }

        return [
            'status' => 'ok',
            'detail' => implode(' | ', $lines),
            'hint' => 'Passwörter maskiert; entspricht dem laufenden PHP-Prozess',
        ];
    }
}

if (!function_exists('deploy_status_migration_tracking_check')) {
    /**
     * @return array{status: string, detail: string, hint: string}
     */
    function deploy_status_migration_tracking_check(): array
    {
        try {
            [, $adminDb] = get_admin_mongo_connection();
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'detail' => 'Admin-Mongo nicht erreichbar: '.$e->getMessage(),
                'hint' => 'ADMIN_DB_URI in .env.local prüfen (Passwort in URI oder Mongo-Auth)',
            ];
        }

        $summary = schema_migration_summary($adminDb);
        if ($summary['error'] !== null) {
            return [
                'status' => 'fail',
                'detail' => 'Lesen von schema_migrations fehlgeschlagen: '.$summary['error'],
                'hint' => 'Rechte der Admin-Mongo-Rolle prüfen',
            ];
        }

        $total = (int) $summary['total'];
        $applied = (int) $summary['applied_count'];
        $pending = $summary['pending'];

        if (!$summary['collection_exists']) {
            return [
                'status' => 'warn',
                'detail' => 'Collection schema_migrations fehlt — '.$total.' trackbare Skripte (ab 03_), noch kein Protokoll. Zuerst 16_db_init_schema_migrations.php ausführen.',
                'hint' => '„Nicht protokolliert“ ≠ Datenbank veraltet; nur: im Admin noch nicht als ausgeführt eingetragen',
            ];
        }

        if ($pending === []) {
            return [
                'status' => 'ok',
                'detail' => $applied.' von '.$total.' trackbaren Skripten protokolliert (Collection schema_migrations).',
                'hint' => 'Idempotente Skripte dürfen wiederholt laufen; Protokoll = letzte dokumentierte Ausführung im Admin',
            ];
        }

        $pendingList = implode(', ', $pending);
        if (strlen($pendingList) > 280) {
            $pendingList = substr($pendingList, 0, 277).'…';
        }

        return [
            'status' => 'warn',
            'detail' => $applied.' von '.$total.' protokolliert; nicht protokolliert: '.$pendingList,
            'hint' => 'Nur Skripte ausführen, die du brauchst; danach erscheinen sie unter DB-Skripte als „Angewendet“',
        ];
    }
}

if (!function_exists('deploy_status_laravel_mail_dev_check')) {
    /**
     * Lokal: Hinweis wenn Passwort-Reset nur ins Laravel-Log geht (MAIL_MAILER=log).
     *
     * @return array{id: string, label: string, status: string, detail: string, hint: string}|null
     */
    function deploy_status_laravel_mail_dev_check(): ?array
    {
        if (app_is_production() || app_environment() !== 'local') {
            return null;
        }

        $mailer = deploy_status_laravel_env_value('MAIL_MAILER');
        if ($mailer === null) {
            return null;
        }

        $mailer = strtolower(trim($mailer));
        if ($mailer !== 'log') {
            return deploy_status_check(
                'laravel_mail_dev',
                'Laravel Mail (Passwort-Reset)',
                'ok',
                'MAIL_MAILER='.$mailer.' — Reset-Mails gehen nicht nur ins Log',
                'MailHog-UI: http://127.0.0.1:8025/ (wenn smtp und MailHog läuft)'
            );
        }

        return deploy_status_check(
            'laravel_mail_dev',
            'Laravel Mail (Passwort-Reset)',
            'warn',
            'MAIL_MAILER=log — Erfolgsmeldung ohne Eintrag in MailHog',
            'laravel/.env: MAIL_MAILER=smtp, MAIL_HOST=127.0.0.1, MAIL_PORT=1025 — siehe docs/local_mail_setup.md'
        );
    }
}

if (!function_exists('deploy_status_mail_smtp_check')) {
    /**
     * @return array{status: string, detail: string, hint: string}
     */
    function deploy_status_mail_smtp_check(): array
    {
        require_once __DIR__.'/mail.php';

        $host = trim((string) (getenv('MAIL_SMTP_HOST') ?: ''));
        $encryption = function_exists('mail_smtp_encryption') ? mail_smtp_encryption() : 'none';
        $isProd = app_is_production();

        if ($host === '') {
            if ($isProd) {
                return [
                    'status' => 'warn',
                    'detail' => 'MAIL_SMTP_HOST nicht gesetzt — Registrierung/Invite nutzen mail() oder Laravel',
                    'hint' => 'SMTP mit MAIL_SMTP_ENCRYPTION=tls setzen oder Laravel MAIL_* konfigurieren',
                ];
            }

            return [
                'status' => 'ok',
                'detail' => 'Kein SMTP (lokal: MailHog optional oder mail.log)',
                'hint' => '',
            ];
        }

        $port = (int) (getenv('MAIL_SMTP_PORT') ?: 25);
        $user = trim((string) (getenv('MAIL_SMTP_USERNAME') ?: ''));
        $detail = $host.':'.$port.', Verschlüsselung='.$encryption
            .($user !== '' ? ', Auth=ja' : ', Auth=nein');

        if ($isProd && $encryption === 'none') {
            return [
                'status' => 'fail',
                'detail' => $detail.' — Plain SMTP in Produktion nicht erlaubt',
                'hint' => 'MAIL_SMTP_ENCRYPTION=tls (Port 587) oder ssl (Port 465); siehe .env.production.example',
            ];
        }

        if ($isProd && $user === '') {
            return [
                'status' => 'warn',
                'detail' => $detail.' — kein MAIL_SMTP_USERNAME (Provider verlangt oft Auth)',
                'hint' => 'MAIL_SMTP_USERNAME und MAIL_SMTP_PASSWORD setzen',
            ];
        }

        return [
            'status' => 'ok',
            'detail' => $detail,
            'hint' => $isProd ? '' : 'Lokal: encryption=none für MailHog OK',
        ];
    }
}

if (!function_exists('deploy_status_status_label')) {
    function deploy_status_status_label(string $status): string
    {
        return match ($status) {
            'ok' => 'OK',
            'fail' => 'Fehler',
            default => 'Hinweis',
        };
    }
}

if (!function_exists('deploy_status_status_class')) {
    function deploy_status_status_class(string $status): string
    {
        return match ($status) {
            'ok' => 'deploy-check--ok',
            'fail' => 'deploy-check--fail',
            default => 'deploy-check--warn',
        };
    }
}
