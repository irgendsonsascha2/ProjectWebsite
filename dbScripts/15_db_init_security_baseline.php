<?php

/**
 * Idempotente Security-Baseline: Indizes für Handoff, Projects, User-Moderation.
 * Nicht-destruktiv — beliebig wiederholbar (Admin oder db_init_master).
 */

require __DIR__.'/_guard.php';

require_once dirname(__DIR__).'/includes/db.php';
require_once dirname(__DIR__).'/includes/handoff_tokens.php';

if (! function_exists('db_script_collection_exists')) {
    function db_script_collection_exists($db, string $name): bool
    {
        foreach ($db->listCollections(['filter' => ['name' => $name]]) as $info) {
            return true;
        }

        return false;
    }
}

try {
    if (! isset($db)) {
        [, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    echo '<h1>Security-Baseline (Indizes)</h1>';

    handoff_token_ensure_indexes($db);
    echo '✅ handoff_tokens: TTL- und Unique-Index (nonce) gesetzt oder bereits vorhanden.<br>';

    if (! db_script_collection_exists($db, 'projects')) {
        echo '⚠️ Collection projects fehlt — Indizes übersprungen (zuerst 01_db_init_content.php).<br>';
    } else {
        $db->projects->createIndex(['created_at' => -1]);
        $db->projects->createIndex(['author_id' => 1, 'created_at' => -1]);
        $db->projects->createIndex(['is_draft' => 1, 'author_id' => 1]);
        echo '✅ projects: Indizes created_at, author_id, is_draft gesetzt oder bereits vorhanden.<br>';
    }

    if (! db_script_collection_exists($db, 'users')) {
        echo '⚠️ Collection users fehlt — Moderation-Index übersprungen.<br>';
    } else {
        $db->users->createIndex(
            ['account_moderation.status' => 1],
            ['name' => 'users_account_moderation_status', 'sparse' => true]
        );
        echo '✅ users: Index users_account_moderation_status (sparse) gesetzt oder bereits vorhanden.<br>';
    }

    echo '<br>✅ Security-Baseline abgeschlossen.<br>';
} catch (Throwable $e) {
    echo '❌ Fehler in '.basename(__FILE__).': '.htmlspecialchars($e->getMessage()).'<br>';
}
