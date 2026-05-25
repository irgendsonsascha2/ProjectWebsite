<?php

declare(strict_types=1);

/**
 * Idempotente DB-Baseline für Deploy (ohne Master/destruktive Skripte).
 * Führt nacheinander 16, 15, 14 aus, wenn noch nicht protokolliert.
 */

$root = realpath(__DIR__.'/..');
if ($root === false) {
    fwrite(STDERR, "Projektroot nicht gefunden.\n");
    exit(2);
}

$scripts = [
    '16_db_init_schema_migrations.php',
    '15_db_init_security_baseline.php',
    '14_db_init_handoff_tokens.php',
];

require_once $root.'/includes/db.php';
require_once $root.'/includes/schema_migrations.php';

try {
    [, $db] = get_admin_mongo_connection();
} catch (Throwable $e) {
    fwrite(STDERR, 'Mongo nicht erreichbar: '.$e->getMessage()."\n");
    exit(1);
}

$summary = schema_migration_summary($db);
$applied = schema_migration_applied_map($db);
$ran = 0;

foreach ($scripts as $name) {
    if (!schema_migration_trackable($name)) {
        continue;
    }
    if (isset($applied[$name])) {
        echo "Überspringe {$name} (bereits protokolliert).\n";
        continue;
    }

    echo "Starte {$name}...\n";
    $code = 0;
    passthru('php '.escapeshellarg($root.'/scripts/run-db-script.php').' '.escapeshellarg($name), $code);
    if ($code !== 0) {
        fwrite(STDERR, "Fehler bei {$name} (Exit {$code}).\n");
        exit(1);
    }
    $ran++;
}

if ($ran === 0) {
    echo "Baseline OK — keine ausstehenden Skripte unter 14/15/16.\n";
} else {
    echo "Baseline: {$ran} Skript(e) ausgeführt.\n";
}

exit(0);
