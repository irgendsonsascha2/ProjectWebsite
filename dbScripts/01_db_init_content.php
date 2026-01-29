<?php
if (!class_exists('MongoDB\Client')) {
    require __DIR__ . '/../vendor/autoload.php';
}

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

try {
    $client = new Client("mongodb://localhost:27017");
    $db = $client->portfolio_db;

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
                    'gallery' => [
                        'bsonType' => 'array',
                        'items' => [
                            'bsonType' => 'object',
                            'required' => ['type', 'url'],
                            'properties' => [
                                'type' => ['enum' => ['image', 'video']],
                                'url' => ['bsonType' => 'string'],
                                'caption' => ['bsonType' => 'string']
                            ]
                        ]
                    ],
                    'author_id' => ['bsonType' => 'string'], // Referenz zum User
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    echo "✅ Projects-Struktur erstellt.<br>";

    // Beispiel-Projekt einfügen
    $now = new UTCDateTime();
    $db->projects->insertOne([
        'title' => 'Erstes Portfolio Werk',
        'description' => 'Willkommen in meinem Grid.',
        'gallery' => [
            ['type' => 'image', 'url' => 'img/sample-1.jpg', 'caption' => 'Nahaufnahme']
        ],
        'thumbnail' => 'img/sample-1.jpg',
        'thumbnail_type' => 'image',
        'created_at' => $now,
        'updated_at' => $now
    ]);
    echo "✅ Beispiel-Projekt eingefügt.";

} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage();
}
?>
