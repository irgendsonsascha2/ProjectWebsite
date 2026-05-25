<?php

/**
 * Idempotent: Collection schema_migrations + Unique-Index auf script.
 * Optional (nur Admin-Dialog): Protokoll für bereits ausgeführte trackbare Skripte auffüllen.
 */

require __DIR__.'/_guard.php';

require_once dirname(__DIR__).'/includes/db.php';
require_once dirname(__DIR__).'/includes/schema_migrations.php';

try {
    if (!isset($db)) {
        [, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    echo '<h1>Schema-Migrations-Tracking</h1>';

    $exists = schema_migration_collection_exists($db);
    if (!$exists) {
        $db->createCollection('schema_migrations');
        echo '✅ Collection schema_migrations angelegt.<br>';
    } else {
        echo '✅ Collection schema_migrations bereits vorhanden.<br>';
    }

    $db->schema_migrations->createIndex(
        ['script' => 1],
        ['unique' => true, 'name' => 'schema_migrations_script_unique']
    );
    echo '✅ Unique-Index auf script gesetzt oder bereits vorhanden.<br>';

    if (schema_migration_wants_backfill_from_input()) {
        echo '<h2>Protokoll auffüllen</h2>';
        $result = schema_migration_backfill_log($db);
        echo '✅ Neu protokolliert ('.count($result['added']).'): ';
        echo $result['added'] === [] ? 'keine' : htmlspecialchars(implode(', ', $result['added']), ENT_QUOTES, 'UTF-8');
        echo '<br>';
        echo 'ℹ️ Bereits im Log ('.count($result['skipped']).'): ';
        echo $result['skipped'] === [] ? 'keine' : htmlspecialchars(implode(', ', $result['skipped']), ENT_QUOTES, 'UTF-8');
        echo '<br>';
        $summary = schema_migration_summary($db);
        echo '<br><b>Stand:</b> '.$summary['applied_count'].' von '.$summary['total'].' trackbaren Skripten protokolliert.<br>';
    } elseif (!isset($GLOBALS['dbScriptInput'])) {
        echo '<br><i>Master/CLI: nur Collection/Index. Im Admin Skript 16 mit Checkbox „Protokoll auffüllen“, wenn Master schon lief, aber das Protokoll leer ist.</i><br>';
    }
} catch (Throwable $e) {
    echo '❌ Fehler: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'<br>';
}
