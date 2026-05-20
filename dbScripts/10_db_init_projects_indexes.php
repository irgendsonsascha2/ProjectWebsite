<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';

if (!function_exists('db_script_collection_exists')) {
    function db_script_collection_exists($db, string $name): bool
    {
        foreach ($db->listCollections(['filter' => ['name' => $name]]) as $info) {
            return true;
        }

        return false;
    }
}

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    echo '<h1>Initialisierung: Projects-Indizes</h1>';

    if (!db_script_collection_exists($db, 'projects')) {
        echo '⚠️ Collection projects fehlt — zuerst 01_db_init_content.php ausführen.<br>';
    } else {
        $db->projects->createIndex(['created_at' => -1]);
        $db->projects->createIndex(['author_id' => 1, 'created_at' => -1]);
        $db->projects->createIndex(['is_draft' => 1, 'author_id' => 1]);
        echo '✅ Indizes auf projects angelegt (created_at, author_id, is_draft).<br>';
    }
} catch (Exception $e) {
    echo '❌ Fehler in '.basename(__FILE__).': '.$e->getMessage().'<br>';
}
