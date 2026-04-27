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

    echo "<h1>Initialisierung: Site Pages</h1>";

    // Site content is "single-doc per key" (e.g. _id = home_profile)
    $db->dropCollection("site_pages");
    $db->createCollection("site_pages", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['display_name', 'kicker', 'lead', 'body', 'created_at', 'updated_at'],
                'properties' => [
                    '_id' => ['bsonType' => 'string'],
                    'display_name' => ['bsonType' => 'string'],
                    'kicker' => ['bsonType' => 'string'],
                    'lead' => ['bsonType' => 'string'],
                    'body' => ['bsonType' => 'string'],
                    'portrait_url' => ['bsonType' => 'string'],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                    'updated_by' => ['bsonType' => 'string'],
                ],
                'additionalProperties' => true,
            ]
        ]
    ]);
    echo "✅ site_pages-Collection erstellt.<br>";

    $now = new UTCDateTime();

    $db->site_pages->insertOne([
        '_id' => 'home_profile',
        'display_name' => 'Dein Name',
        'kicker' => 'Deine Position / Spezialisierung',
        'lead' => "Kurze Beschreibung.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'body' => "Langer Text.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.\n\nSed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris.",
        'portrait_url' => '',
        'created_at' => $now,
        'updated_at' => $now,
        'updated_by' => ''
    ]);
    echo "✅ home_profile eingefügt.<br>";
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}

