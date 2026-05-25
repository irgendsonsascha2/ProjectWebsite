<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/_script_input_helpers.php';

use MongoDB\BSON\UTCDateTime;

try {
    // HYBRIDE VERBINDUNG: Prüfen ob Master bereits $db bereitgestellt hat
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: Account Management</h1>";

    // --- A. PERMISSIONS_CONFIG (Berechtigungen) ---
    $db->dropCollection("permissions_config");
    $db->createCollection("permissions_config");
    $db->permissions_config->insertMany([
        ['key' => 'view_projects', 'label' => 'Projekte ansehen', 'description' => 'Projekte im Frontend ansehen'],
        ['key' => 'view_comments', 'label' => 'Kommentare ansehen', 'description' => 'Kommentare lesen'],
        ['key' => 'view_likes', 'label' => 'Likes/Dislikes ansehen', 'description' => 'Like/Dislike-Zahlen anzeigen'],
        ['key' => 'create_project', 'label' => 'Projekt erstellen', 'description' => 'Neue Projekte anlegen'],
        ['key' => 'edit_all', 'label' => 'Alle Projekte bearbeiten', 'description' => 'Beliebige Projekte bearbeiten'],
        ['key' => 'edit_own', 'label' => 'Eigene Projekte bearbeiten', 'description' => 'Nur eigene Projekte bearbeiten'],
        ['key' => 'delete_all', 'label' => 'Projekte löschen', 'description' => 'Projekte löschen (inkl. Kommentare/Likes)'],
        ['key' => 'delete_comments', 'label' => 'Kommentare löschen', 'description' => 'Kommentare anderer Nutzer löschen (Rollenzuordnung)'],
        ['key' => 'comment_limit', 'label' => 'Kommentar-Limit', 'description' => 'Kommentar-Anzahl pro Rolle begrenzen'],
        ['key' => 'manage_users', 'label' => 'Benutzer verwalten', 'description' => 'Admin-Funktionen für Benutzer/Einladungen'],
        ['key' => 'generate_codes', 'label' => 'Einladungscodes erzeugen', 'description' => 'Registrierungs-Codes erstellen'],
        ['key' => 'comment', 'label' => 'Kommentieren', 'description' => 'Kommentare erstellen/bearbeiten'],
        ['key' => 'like_dislike', 'label' => 'Likes/Dislikes', 'description' => 'Likes und Dislikes vergeben']
    ]);
    $db->permissions_config->createIndex(['key' => 1], ['unique' => true]);
    echo "✅ Berechtigungen definiert.<br>";

    // --- B. ROLES_CONFIG (Rechte-Matrix) ---
    $db->dropCollection("roles_config");
    $db->createCollection("roles_config");
    $db->roles_config->insertMany([
        [
            'role' => 'viewer',
            'label' => 'Viewer',
            'permissions' => ['view_projects', 'view_comments', 'view_likes'],
            'comment_delete_roles' => [],
            'comment_limit' => 0
        ],
        [
            'role' => 'admin',
            'label' => 'Administrator',
            'permissions' => ['view_projects', 'view_comments', 'view_likes', 'create_project', 'edit_all', 'delete_all', 'delete_comments', 'manage_users', 'generate_codes', 'comment', 'like_dislike'],
            'comment_delete_roles' => ['admin', 'content_manager', 'community_member', 'viewer'],
            'comment_limit' => 0
        ],
        [
            'role' => 'content_manager',
            'label' => 'Content Manager',
            'permissions' => ['view_projects', 'view_comments', 'view_likes', 'create_project', 'edit_all', 'comment', 'like_dislike', 'delete_comments'],
            'comment_delete_roles' => ['content_manager', 'community_member', 'viewer'],
            'comment_limit' => 0
        ],
        [
            'role' => 'community_member',
            'label' => 'Community Member',
            'permissions' => ['view_projects', 'view_comments', 'view_likes', 'comment', 'like_dislike', 'comment_limit'],
            'comment_delete_roles' => [],
            'comment_limit' => 10
        ]
    ]);
    echo "✅ Rollen & Rechte definiert.<br>";

    // --- C. REGISTRATION_CODES ---
    $db->dropCollection("registration_codes");
    $db->createCollection("registration_codes", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['code', 'role', 'is_used', 'created_at'],
                'properties' => [
                    'code' => ['bsonType' => 'string'],
                    'role' => ['bsonType' => 'string'],
                    'is_used' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    echo "✅ Registrierungscode-System bereit.<br>";

    // --- C2. REGISTRATION_CODE_REQUESTS (E-Mail-Verifikation + Admin-Freigabe) ---
    // Nutzer können (ohne Code) eine Anfrage stellen, bestätigen ihre E-Mail über Token,
    // danach kann der Admin einen Code freigeben und per Mail senden.
    $db->dropCollection("registration_code_requests");
    $db->createCollection("registration_code_requests", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['email', 'token', 'created_at', 'expires_at', 'verified_at', 'approved_at', 'code', 'privacy_consent_at'],
                'properties' => [
                    'email' => ['bsonType' => 'string', 'pattern' => '^.+@.+$'],
                    'token' => ['bsonType' => 'string'],
                    'created_at' => ['bsonType' => 'date'],
                    'expires_at' => ['bsonType' => 'date'],
                    'verified_at' => ['bsonType' => ['date', 'null']],
                    'approved_at' => ['bsonType' => ['date', 'null']],
                    'code' => ['bsonType' => ['string', 'null']],
                    'privacy_consent_at' => ['bsonType' => 'date'],
                    'requested_ip' => ['bsonType' => 'string'],
                    'user_agent' => ['bsonType' => 'string'],
                ],
                'additionalProperties' => true,
            ],
        ],
    ]);
    $db->registration_code_requests->createIndex(['token' => 1], ['unique' => true]);
    $db->registration_code_requests->createIndex(['email' => 1]);
    $db->registration_code_requests->createIndex(['verified_at' => 1, 'approved_at' => 1]);
    echo "✅ Registrierungscode-Anfragen bereit.<br>";

    // --- D. USERS ---
    $db->dropCollection("users");
    $db->createCollection("users", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['email', 'username', 'password', 'role', 'created_at'],
                'properties' => [
                    'email' => ['bsonType' => 'string', 'pattern' => '^.+@.+$'],
                    'username' => ['bsonType' => 'string'],
                    'password' => ['bsonType' => 'string'],
                    'role' => ['bsonType' => 'string'],
                    'created_at' => ['bsonType' => 'date'],
                ],
                /* Laravel (mongodb/laravel): remember_token, email_verified_at u. a. — ohne dies schlägt Insert fehl. */
                'additionalProperties' => true,
            ],
        ],
    ]);
    $db->users->createIndex(['email' => 1], ['unique' => true]);
    $db->users->createIndex(['username' => 1], ['unique' => true]);

    $seedAdminEmail = db_script_input_value('seed_admin_email');
    $seedAdminUsername = db_script_input_value('seed_admin_username');
    $seedAdminPassword = db_script_input_value('seed_admin_password');

    if ($seedAdminEmail === '' || $seedAdminUsername === '' || $seedAdminPassword === '') {
        throw new RuntimeException('Seed-Admin-E-Mail, Username und Passwort müssen beim Ausführen angegeben werden.');
    }

    // Initialen Admin anlegen
    $db->users->insertOne([
        'email' => $seedAdminEmail,
        'username' => $seedAdminUsername,
        'password' => password_hash($seedAdminPassword, PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => new UTCDateTime()
    ]);
    
    echo "✅ Users-Collection & Master-Admin erstellt.<br>";

} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
?>
