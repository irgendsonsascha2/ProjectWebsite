<?php
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

try {
    $client = new Client("mongodb://localhost:27017");
    $db = $client->portfolio_db;

    echo "<h1>Initialisierung: Account Management</h1>";

    // --- A. ROLES_CONFIG (Rechte-Matrix) ---
    $db->dropCollection("roles_config");
    $db->createCollection("roles_config");
    $db->roles_config->insertMany([
        [
            'role' => 'admin', 
            'permissions' => ['create_project', 'edit_all', 'delete_all', 'manage_users', 'generate_codes']
        ],
        [
            'role' => 'content_manager', 
            'permissions' => ['create_project', 'edit_own']
        ],
        [
            'role' => 'viewer', 
            'permissions' => []
        ]
    ]);
    echo "✅ Rollen & Rechte definiert.<br>";

    // --- B. REGISTRATION_CODES (Einmal-Einladungen) ---
    $db->dropCollection("registration_codes");
    $db->createCollection("registration_codes", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['code', 'role', 'is_used', 'created_at'],
                'properties' => [
                    'code' => ['bsonType' => 'string'],
                    'role' => ['enum' => ['admin', 'content_manager', 'viewer']],
                    'is_used' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    echo "✅ Einmal-Code System bereit.<br>";

    // --- C. USERS (Die eigentlichen Accounts) ---
    $db->dropCollection("users");
    $db->createCollection("users", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['email', 'password', 'role', 'created_at'],
                'properties' => [
                    'email' => ['bsonType' => 'string', 'pattern' => '^.+@.+$'],
                    'password' => ['bsonType' => 'string'],
                    'role' => ['enum' => ['admin', 'content_manager', 'viewer']],
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->users->createIndex(['email' => 1], ['unique' => true]);

    // Initialen Admin anlegen
    $db->users->insertOne([
        'email' => 'admin@test.de',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => new UTCDateTime()
    ]);
    
    echo "✅ Users-Collection & Master-Admin erstellt.<br>";

} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage();
}
?>