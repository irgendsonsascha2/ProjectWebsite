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

    // --- A. ROLES_CONFIG (Rechte-Matrix) ---
    $db->dropCollection("roles_config");
    $db->createCollection("roles_config");
    $db->roles_config->insertMany([
        [
            'role' => 'admin', 
            'permissions' => ['create_project', 'edit_all', 'delete_all', 'manage_users', 'generate_codes', 'comment', 'like_dislike']
        ],
        [
            'role' => 'content_manager', 
            'permissions' => ['create_project', 'edit_own', 'comment', 'like_dislike']
        ],
        [
            'role' => 'community_member', 
            'permissions' => ['comment', 'like_dislike']
        ]
    ]);
    echo "✅ Rollen & Rechte definiert.<br>";

    // --- B. REGISTRATION_CODES ---
    $db->dropCollection("registration_codes");
    $db->createCollection("registration_codes", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['code', 'role', 'is_used', 'created_at'],
                'properties' => [
                    'code' => ['bsonType' => 'string'],
                    'role' => ['enum' => ['admin', 'content_manager', 'community_member']],
                    'is_used' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    echo "✅ Einmal-Code System bereit.<br>";

    // --- C. USERS ---
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
                    'role' => ['enum' => ['admin', 'content_manager', 'community_member']],
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
