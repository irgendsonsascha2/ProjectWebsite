<?php
// Nur Autoload laden, wenn Klassen nicht bereits durch Master geladen wurden
if (!class_exists('MongoDB\Client')) {
    require __DIR__ . '/../vendor/autoload.php';
}

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

try {
    // HYBRIDE VERBINDUNG: Prüfen ob Master bereits $db bereitgestellt hat
    if (!isset($db)) {
        $client = new Client("mongodb://localhost:27017");
        $db = $client->portfolio_db;
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: Account Management</h1>";

    // --- A. PERMISSIONS_CONFIG (Berechtigungen) ---
    $db->dropCollection("permissions_config");
    $db->createCollection("permissions_config");
    $db->permissions_config->insertMany([
        ['key' => 'create_project', 'label' => 'Projekt erstellen', 'description' => 'Neue Projekte anlegen'],
        ['key' => 'edit_all', 'label' => 'Alle Projekte bearbeiten', 'description' => 'Beliebige Projekte bearbeiten'],
        ['key' => 'edit_own', 'label' => 'Eigene Projekte bearbeiten', 'description' => 'Nur eigene Projekte bearbeiten'],
        ['key' => 'delete_all', 'label' => 'Projekte löschen', 'description' => 'Projekte löschen (inkl. Kommentare/Likes)'],
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
            'permissions' => []
        ],
        [
            'role' => 'admin',
            'label' => 'Administrator',
            'permissions' => ['create_project', 'edit_all', 'delete_all', 'manage_users', 'generate_codes', 'comment', 'like_dislike']
        ],
        [
            'role' => 'content_manager',
            'label' => 'Content Manager',
            'permissions' => ['create_project', 'edit_own', 'comment', 'like_dislike']
        ],
        [
            'role' => 'community_member',
            'label' => 'Community Member',
            'permissions' => ['comment', 'like_dislike']
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
    echo "✅ Einmal-Code System bereit.<br>";

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
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->users->createIndex(['email' => 1], ['unique' => true]);
    $db->users->createIndex(['username' => 1], ['unique' => true]);

    // Initialen Admin anlegen
    $db->users->insertOne([
        'email' => 'admin@test.de',
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => new UTCDateTime()
    ]);
    
    echo "✅ Users-Collection & Master-Admin erstellt.<br>";

} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
?>
