<?php

/**
 * Idempotent: Read-View für veröffentlichte Projekte + Mongo-Rollen (viewer/community ohne find auf projects).
 */

require __DIR__.'/_guard.php';

require_once dirname(__DIR__).'/includes/db.php';
require_once __DIR__.'/_mongo_role_privileges.php';

if (!function_exists('mongo_script_collection_exists')) {
    function mongo_script_collection_exists($db, string $name): bool
    {
        foreach ($db->listCollections(['filter' => ['name' => $name]]) as $info) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mongo_ensure_projects_published_view')) {
    function mongo_ensure_projects_published_view($db): void
    {
        if (!mongo_script_collection_exists($db, 'projects')) {
            echo '⚠️ Collection projects fehlt — View übersprungen (zuerst 01_db_init_content.php).<br>';

            return;
        }

        $pipeline = [
            ['$match' => ['is_draft' => ['$ne' => true]]],
        ];

        $viewName = MONGO_PROJECTS_PUBLISHED_VIEW;

        if (mongo_script_collection_exists($db, $viewName)) {
            $db->command([
                'collMod' => $viewName,
                'viewOn' => 'projects',
                'pipeline' => $pipeline,
            ]);
            echo "✅ View {$viewName}: Pipeline aktualisiert.<br>";

            return;
        }

        $db->createCollection($viewName, [
            'viewOn' => 'projects',
            'pipeline' => $pipeline,
        ]);
        echo "✅ View {$viewName}: angelegt (nur veröffentlichte Projekte).<br>";
    }
}

try {
    if (!isset($db)) {
        [, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    echo '<h1>MongoDB Read-Views &amp; App-Rollen</h1>';

    mongo_ensure_projects_published_view($db);

    mongo_apply_app_custom_roles($db);
    echo '✅ viewerRole, communityMemberRole, contentManagerRole aktualisiert.<br>';

    $locked = implode(', ', mongo_app_role_locked_collections());
    echo "ℹ️ viewer/community: <code>find</code> nur auf <code>".MONGO_PROJECTS_PUBLISHED_VIEW.'</code>, nicht auf <code>projects</code>.<br>';
    echo "ℹ️ Keine App-Rollen-Rechte auf: {$locked}.<br>";
    echo "ℹ️ content_manager behält vollen Zugriff auf <code>projects</code> (inkl. Entwürfe).<br>";
    echo '<br>✅ Read-Views &amp; Rollen abgeschlossen.<br>';
} catch (Throwable $e) {
    echo '❌ Fehler in '.basename(__FILE__).': '.htmlspecialchars($e->getMessage()).'<br>';
}
