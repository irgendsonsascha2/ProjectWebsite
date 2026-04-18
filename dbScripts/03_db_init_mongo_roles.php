<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/_script_input_helpers.php';

function mongo_role_exists($db, $roleName) {
    $result = $db->command([
        'rolesInfo' => $roleName,
        'showPrivileges' => true,
        'showBuiltinRoles' => false
    ])->toArray();

    if (!isset($result[0]->roles)) {
        return false;
    }

    return count((array)$result[0]->roles) > 0;
}

function mongo_user_exists($db, $username) {
    $result = $db->command([
        'usersInfo' => $username,
        'showPrivileges' => false,
        'showCredentials' => false
    ])->toArray();

    if (!isset($result[0]->users)) {
        return false;
    }

    return count((array)$result[0]->users) > 0;
}

function ensure_mongo_role($db, $roleName, $privileges, $inheritedRoles = []) {
    $command = [
        'createRole' => $roleName,
        'privileges' => $privileges,
        'roles' => $inheritedRoles
    ];

    if (mongo_role_exists($db, $roleName)) {
        $command = [
            'updateRole' => $roleName,
            'privileges' => $privileges,
            'roles' => $inheritedRoles
        ];
    }

    $db->command($command)->toArray();
}

function ensure_mongo_user($db, $username, $password, $roles) {
    $command = [
        'createUser' => $username,
        'pwd' => $password,
        'roles' => $roles
    ];

    if (mongo_user_exists($db, $username)) {
        $command = [
            'updateUser' => $username,
            'pwd' => $password,
            'roles' => $roles
        ];
    }

    $db->command($command)->toArray();
}

function db_collection_actions($collection, $actions) {
    return [
        'resource' => [
            'db' => mongo_config()['db_name'],
            'collection' => $collection
        ],
        'actions' => $actions
    ];
}

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: MongoDB Rollen & Benutzer</h1>";

    $viewerPrivileges = [
        db_collection_actions('projects', ['find']),
        db_collection_actions('comments', ['find']),
        db_collection_actions('likes', ['find']),
        db_collection_actions('roles_config', ['find']),
        db_collection_actions('permissions_config', ['find']),
        db_collection_actions('users', ['find']),
        db_collection_actions('registration_codes', ['find'])
    ];

    $communityPrivileges = [
        db_collection_actions('projects', ['find']),
        db_collection_actions('comments', ['find', 'insert', 'update', 'remove']),
        db_collection_actions('likes', ['find', 'insert', 'update', 'remove']),
        db_collection_actions('roles_config', ['find']),
        db_collection_actions('permissions_config', ['find']),
        db_collection_actions('users', ['find']),
        db_collection_actions('registration_codes', ['find'])
    ];

    $contentManagerPrivileges = [
        db_collection_actions('projects', ['find', 'insert', 'update', 'remove']),
        db_collection_actions('comments', ['find', 'insert', 'update', 'remove']),
        db_collection_actions('likes', ['find', 'insert', 'update', 'remove']),
        db_collection_actions('roles_config', ['find']),
        db_collection_actions('permissions_config', ['find']),
        db_collection_actions('users', ['find']),
        db_collection_actions('registration_codes', ['find'])
    ];

    ensure_mongo_role($db, 'viewerRole', $viewerPrivileges);
    echo "✅ Custom Role viewerRole erstellt/aktualisiert.<br>";

    ensure_mongo_role($db, 'communityMemberRole', $communityPrivileges);
    echo "✅ Custom Role communityMemberRole erstellt/aktualisiert.<br>";

    ensure_mongo_role($db, 'contentManagerRole', $contentManagerPrivileges);
    echo "✅ Custom Role contentManagerRole erstellt/aktualisiert.<br>";

    $passwords = [
        'viewer' => db_script_input_value('mongo_viewer_db_password', getenv('MONGO_VIEWER_DB_PASSWORD') ?: ''),
        'community_member' => db_script_input_value('mongo_community_db_password', getenv('MONGO_COMMUNITY_DB_PASSWORD') ?: ''),
        'content_manager' => db_script_input_value('mongo_content_manager_db_password', getenv('MONGO_CONTENT_MANAGER_DB_PASSWORD') ?: ''),
        'admin' => db_script_input_value('mongo_admin_db_password', getenv('MONGO_ADMIN_DB_PASSWORD') ?: '')
    ];

    $userDefinitions = [
        'viewer' => [
            'roles' => [['role' => 'viewerRole', 'db' => mongo_config()['db_name']]],
            'password_env' => 'MONGO_VIEWER_DB_PASSWORD'
        ],
        'community_member' => [
            'roles' => [['role' => 'communityMemberRole', 'db' => mongo_config()['db_name']]],
            'password_env' => 'MONGO_COMMUNITY_DB_PASSWORD'
        ],
        'content_manager' => [
            'roles' => [['role' => 'contentManagerRole', 'db' => mongo_config()['db_name']]],
            'password_env' => 'MONGO_CONTENT_MANAGER_DB_PASSWORD'
        ],
        'admin' => [
            'roles' => [['role' => 'dbOwner', 'db' => mongo_config()['db_name']]],
            'password_env' => 'MONGO_ADMIN_DB_PASSWORD'
        ]
    ];

    foreach ($userDefinitions as $username => $definition) {
        $password = $passwords[$username];
        if ($password === '') {
            throw new RuntimeException("Passwort für MongoDB-Benutzer {$username} fehlt ({$definition['password_env']} oder Formularfeld).");
        }

        ensure_mongo_user($db, $username, $password, $definition['roles']);
        echo "✅ MongoDB-Benutzer {$username} erstellt/aktualisiert.<br>";
    }

    echo "ℹ️ Hinweis: Ownership-Regeln wie 'nur eigene Projekte bearbeiten' bleiben weiterhin Aufgabe der Anwendung.<br>";
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
