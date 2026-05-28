<?php

declare(strict_types=1);

/**
 * Ein DB-Skript per CLI ausführen (Admin-Panel-Alternative für Deploy).
 *
 * Usage: php scripts/run-db-script.php 16_db_init_schema_migrations.php
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/run-db-script.php <script-basename>\n");
    exit(2);
}

$name = basename((string) $argv[1]);
if (!preg_match('/^[0-9]{2}_.+\.php$/', $name)) {
    fwrite(STDERR, "Ungültiger Skriptname: {$name}\n");
    exit(2);
}

$root = realpath(__DIR__.'/..');
if ($root === false) {
    fwrite(STDERR, "Projektroot nicht gefunden.\n");
    exit(2);
}

$path = $root.'/dbScripts/'.$name;
if (!is_readable($path)) {
    fwrite(STDERR, "Skript nicht gefunden: {$path}\n");
    exit(2);
}

chdir($root);
define('ALLOW_DB_SCRIPT_EXECUTION', true);

require_once $root.'/includes/db.php';
require_once $root.'/includes/schema_migrations.php';

echo "=== {$name} ===\n";

if (schema_migration_is_destructive($name) && ! schema_migration_destructive_allowed()) {
    fwrite(STDERR, "Destruktives Skript blockiert (ALLOW_DESTRUCTIVE_DB_SCRIPTS=1 setzen um lokal auszuführen): {$name}\n");
    exit(2);
}

ob_start();
include $path;
$output = (string) ob_get_clean();
echo strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $output));

if (schema_migration_trackable($name)) {
    try {
        [, $db] = get_admin_mongo_connection();
        schema_migration_record($db, $name, 'cli');
        echo "\n[schema_migrations] {$name} protokolliert.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Protokoll fehlgeschlagen: ".$e->getMessage()."\n");
        exit(1);
    }
}

echo "\nFertig.\n";
