<?php

require __DIR__.'/_guard.php';
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/_script_input_helpers.php';
require_once __DIR__.'/_mongo_role_privileges.php';

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    echo '<h1>Initialisierung: MongoDB Rollen & Benutzer</h1>';

    mongo_apply_app_custom_roles($db);
    echo '✅ Custom Role viewerRole erstellt/aktualisiert.<br>';
    echo '✅ Custom Role communityMemberRole erstellt/aktualisiert.<br>';
    echo '✅ Custom Role contentManagerRole erstellt/aktualisiert.<br>';

    $userDefinitions = [
        'viewer' => [
            'roles' => [['role' => 'viewerRole', 'db' => mongo_config()['db_name']]],
            'input_key' => 'mongo_viewer_db_password',
            'env' => 'MONGO_VIEWER_DB_PASSWORD',
        ],
        'community_member' => [
            'roles' => [['role' => 'communityMemberRole', 'db' => mongo_config()['db_name']]],
            'input_key' => 'mongo_community_db_password',
            'env' => 'MONGO_COMMUNITY_DB_PASSWORD',
        ],
        'content_manager' => [
            'roles' => [['role' => 'contentManagerRole', 'db' => mongo_config()['db_name']]],
            'input_key' => 'mongo_content_manager_db_password',
            'env' => 'MONGO_CONTENT_MANAGER_DB_PASSWORD',
        ],
        'admin' => [
            'roles' => [['role' => 'dbOwner', 'db' => mongo_config()['db_name']]],
            'input_key' => 'mongo_admin_db_password',
            'env' => 'MONGO_ADMIN_DB_PASSWORD',
        ],
    ];

    foreach ($userDefinitions as $username => $definition) {
        $password = db_script_resolve_password($definition['input_key'], $definition['env']);

        ensure_mongo_user($db, $username, $password, $definition['roles']);
        echo "✅ MongoDB-Benutzer {$username} erstellt/aktualisiert.<br>";
    }

    $locked = implode(', ', mongo_app_role_locked_collections());
    echo "ℹ️ App-Rollen-Allowlist: kein Zugriff auf {$locked}. Lesen veröffentlichter Projekte über View <code>".MONGO_PROJECTS_PUBLISHED_VIEW.'</code>.<br>';
    echo 'ℹ️ users / registration_codes: nur über Admin-DB-User (dbOwner). Öffentliche Kommentar-Namen über author_* Snapshot oder Admin-Lesen in includes/user_db.php.<br>';
    echo "ℹ️ Ownership-Regeln wie 'nur eigene Projekte bearbeiten' bleiben weiterhin Aufgabe der Anwendung.<br>";
} catch (Exception $e) {
    echo '❌ Fehler in '.basename(__FILE__).': '.$e->getMessage().'<br>';
}
