<?php

declare(strict_types=1);

/**
 * Master-DB-Init per CLI (entspricht Admin → db_init_master.php).
 *
 * Usage:
 *   php scripts/run-db-init-master-cli.php
 *   SEED_ADMIN_PASSWORD='…' MONGO_DB_PASSWORD='…' php scripts/run-db-init-master-cli.php
 */

/** @param array<string, string> $updates */
function cli_update_env_local_file(string $root, array $updates): void
{
    if ($updates === []) {
        return;
    }
    foreach ($updates as $k => $v) {
        if (! is_string($k) || $k === '') {
            throw new RuntimeException('Ungültiger Env-Key.');
        }
        if (! is_string($v)) {
            throw new RuntimeException('Ungültiger Env-Value für '.$k.'.');
        }
        if (preg_match('/[\\x00-\\x1F\\x7F]/', $v)) {
            throw new RuntimeException('Ungültige Zeichen im Wert für '.$k.'.');
        }
    }
    $path = $root.'/.env.local';
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
            $out[] = $key.'="'.addcslashes($updates[$key], "\\\"").'"';
        } else {
            $out[] = $line;
        }
    }
    foreach ($updates as $k => $v) {
        if (! isset($seen[$k])) {
            $out[] = $k.'="'.addcslashes($v, "\\\"").'"';
        }
    }
    if (file_put_contents($path, implode("\n", $out)."\n") === false) {
        throw new RuntimeException('Konnte .env.local nicht schreiben.');
    }
}

function cli_update_laravel_env_mongodb_uri(string $root, string $adminUri): void
{
    $path = $root.'/laravel/.env';
    if (! is_file($path) || ! is_readable($path)) {
        throw new RuntimeException('laravel/.env nicht gefunden.');
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (! is_array($lines)) {
        throw new RuntimeException('laravel/.env konnte nicht gelesen werden.');
    }
    $out = [];
    $updated = false;
    foreach ($lines as $line) {
        if (preg_match('/^\s*MONGODB_URI\s*=/', $line)) {
            $out[] = 'MONGODB_URI='.$adminUri;
            $updated = true;
        } else {
            $out[] = $line;
        }
    }
    if (! $updated) {
        $out[] = 'MONGODB_URI='.$adminUri;
    }
    if (file_put_contents($path, implode("\n", $out)."\n") === false) {
        throw new RuntimeException('Konnte laravel/.env nicht schreiben.');
    }
}

$root = realpath(__DIR__.'/..');
if ($root === false) {
    fwrite(STDERR, "Projektroot nicht gefunden.\n");
    exit(2);
}

chdir($root);
define('ALLOW_DB_SCRIPT_EXECUTION', true);

require_once $root.'/includes/db.php';
require_once $root.'/includes/schema_migrations.php';
require_once $root.'/includes/input_validate.php';

if (! schema_migration_destructive_allowed()) {
    fwrite(STDERR, "Destruktive Skripte blockiert (ALLOW_DESTRUCTIVE_DB_SCRIPTS=1 in .env.local).\n");
    exit(2);
}

$mongoPw = getenv('MONGO_DB_PASSWORD');
if (! is_string($mongoPw) || $mongoPw === '') {
    $mongoPw = '00000000';
}
$seedPw = getenv('SEED_ADMIN_PASSWORD');
if (! is_string($seedPw) || $seedPw === '') {
    $seedPw = '00000000';
}

$seedEmail = getenv('SEED_ADMIN_EMAIL');
if (! is_string($seedEmail) || $seedEmail === '') {
    $seedEmail = 'sascha.faehling@gmail.com';
}
$seedUser = getenv('SEED_ADMIN_USERNAME');
if (! is_string($seedUser) || $seedUser === '') {
    $seedUser = 'sascha';
}

foreach (
    [
        'MONGO_DB_PASSWORD' => $mongoPw,
        'SEED_ADMIN_PASSWORD' => $seedPw,
    ] as $label => $value
) {
    if (input_secret_password($value, 8) === null) {
        fwrite(STDERR, "{$label} ungültig (min. 8 Zeichen).\n");
        exit(2);
    }
}

if (input_email($seedEmail) === null || input_slug_key($seedUser, 64) === null) {
    fwrite(STDERR, "Seed-Admin E-Mail oder Username ungültig.\n");
    exit(2);
}

$GLOBALS['dbScriptInput'] = [
    'seed_admin_email' => $seedEmail,
    'seed_admin_username' => $seedUser,
    'seed_admin_password' => $seedPw,
    'mongo_viewer_db_password' => $mongoPw,
    'mongo_community_db_password' => $mongoPw,
    'mongo_content_manager_db_password' => $mongoPw,
    'mongo_admin_db_password' => $mongoPw,
];

$cfg = mongo_config();
$connectPass = '0';
$adminUri = $cfg['admin_uri'];
if (preg_match('/^mongodb(?:\+srv)?:\/\/[^:\/]+:[^@]*@/', $adminUri)) {
    $connectPass = '0';
} elseif (! str_contains($adminUri, '@')) {
    $connectPass = '';
}
$adminUri = mongo_uri_with_password($adminUri, $connectPass);

echo "=== db_init_master.php (CLI) ===\n";
echo "Seed: {$seedUser} <{$seedEmail}>\n";

try {
    [$client, $db] = create_mongo_connection($adminUri);
} catch (Throwable $e) {
    fwrite(STDERR, 'Mongo-Verbindung fehlgeschlagen: '.$e->getMessage()."\n");
    fwrite(STDERR, "Tipp: URIs ohne Auth setzen (README → Erste Initialisierung).\n");
    exit(1);
}

ob_start();
include $root.'/dbScripts/db_init_master.php';
$output = (string) ob_get_clean();
echo strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $output));

$updates = [
    'APP_DB_NAME' => $cfg['db_name'],
    'VIEWER_DB_URI' => mongo_uri_with_password($cfg['viewer_uri'], $mongoPw),
    'COMMUNITY_DB_URI' => mongo_uri_with_password($cfg['community_member_uri'], $mongoPw),
    'CONTENT_MANAGER_DB_URI' => mongo_uri_with_password($cfg['content_manager_uri'], $mongoPw),
    'ADMIN_DB_URI' => mongo_uri_with_password($cfg['admin_uri'], $mongoPw),
    'APP_DB_URI' => mongo_uri_with_password($cfg['viewer_uri'], $mongoPw),
];

$adminUriUpdated = $updates['ADMIN_DB_URI'];

try {
    cli_update_env_local_file($root, $updates);
    echo "\n.env.local aktualisiert (Mongo-URIs mit neuen Passwörtern).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nWarnung: .env.local nicht aktualisiert: ".$e->getMessage()."\n");
}

try {
    cli_update_laravel_env_mongodb_uri($root, $adminUriUpdated);
    echo "laravel/.env: MONGODB_URI angepasst (Admin-URI wie .env.local).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Warnung: laravel/.env nicht aktualisiert: ".$e->getMessage()."\n");
    fwrite(STDERR, "Manuell MONGODB_URI in laravel/.env auf ADMIN_DB_URI aus .env.local setzen.\n");
}

echo "\nFertig. PHP-Server (8080) und Laravel (artisan serve) neu starten, dann einloggen.\n";
