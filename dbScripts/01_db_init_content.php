<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';

use MongoDB\BSON\UTCDateTime;

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: Content & Projekte</h1>";

    // --- PROJECTS COLLECTION ---
    $db->dropCollection("projects");
    $db->createCollection("projects", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['title', 'description', 'gallery', 'created_at', 'updated_at'],
                'properties' => [
                    'title' => ['bsonType' => 'string'],
                    'description' => ['bsonType' => 'string'],
                    'thumbnail' => ['bsonType' => 'string'],
                    'thumbnail_type' => ['enum' => ['image', 'video']],
                    'tags' => [
                        'bsonType' => 'array',
                        'items' => ['bsonType' => 'string']
                    ],
                    'gallery' => [
                        'bsonType' => 'array',
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['media_id', 'type', 'url'],
                            'properties' => [
                                'media_id' => ['bsonType' => 'objectId'],
                                'type' => ['enum' => ['image', 'video']],
                                'url' => ['bsonType' => 'string']
                            ]
                        ]
                    ],
                    'author_id' => ['bsonType' => 'string'], // Referenz zum User
                    'is_draft' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    echo "✅ Projects-Struktur erstellt.<br>";

    // Beispiel-Projekt einfügen (ohne Medien)
    $now = new UTCDateTime();
    $db->projects->insertOne([
        'title' => 'Erstes Portfolio Werk',
        'description' => 'Willkommen in meinem Grid.',
        'tags' => ['Coding', 'Portfolio'],
        'gallery' => [],
        'thumbnail_type' => 'image',
        'created_at' => $now,
        'updated_at' => $now
    ]);
    echo "✅ Beispiel-Projekt eingefügt (ohne Medien).";

} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage();
}
?>
