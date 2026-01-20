<?php

require 'vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

// ... restlicher Code

try {
    $client = new Client("mongodb://localhost:27017");
    $dbName = "portfolio_db";
    $collectionName = "projects";
    $db = $client->$dbName;

    // 1. Falls die Collection existiert, löschen wir sie für einen sauberen Neustart
    $db->dropCollection($collectionName);

    // 2. Das "Echte" Datenmodell (Schema Validation) definieren
    $db->createCollection($collectionName, [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['title', 'description', 'thumbnail', 'gallery', 'created_at', 'updated_at'],
                'properties' => [
                    'title' => [
                        'bsonType' => 'string',
                        'description' => 'Titel des Projekts (erforderlich)'
                    ],
                    'description' => [
                        'bsonType' => 'string',
                        'description' => 'Kurzbeschreibung für das Grid'
                    ],
                    'thumbnail' => [
                        'bsonType' => 'string',
                        'description' => 'Pfad zum Vorschaubild'
                    ],
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
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);

    echo "✅ Datenbank-Modell erfolgreich erstellt.<br>";

    // 3. Einen Test-Datensatz einfügen
    $collection = $db->$collectionName;
    $now = new UTCDateTime(); // Erzeugt aktuellen Zeitstempel

    $testProject = [
        'title' => 'Mein Portfolio Projekt',
        'description' => 'Dies ist mein erstes Projekt im Grid mit einer MongoDB Galerie.',
        'thumbnail' => 'img/thumbnails/projekt1.jpg',
        'gallery' => [
            [
                'type' => 'image',
                'url' => 'img/gallery/bild1.jpg',
                'caption' => 'Erste Ansicht'
            ],
            [
                'type' => 'video',
                'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'caption' => 'Projekt Video'
            ]
        ],
        'created_at' => $now,
        'updated_at' => $now
    ];

    $collection->insertOne($testProject);
    echo "✅ Beispiel-Projekt mit Zeitstempeln eingefügt.";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage();
}
