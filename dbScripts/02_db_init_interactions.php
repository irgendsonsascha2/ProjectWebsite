<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';

try {
    // HYBRIDE VERBINDUNG: Prüfen ob Master bereits $db bereitgestellt hat
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: User Interactions</h1>";

    // --- A. COMMENTS ---
    $db->dropCollection("comments");
    $db->createCollection("comments", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['project_id', 'media_id', 'user_id', 'text', 'created_at', 'updated_at'],
                'properties' => [
                    'project_id' => ['bsonType' => 'objectId'],
                    'media_id' => ['bsonType' => 'objectId'],
                    'user_id' => ['bsonType' => 'objectId'],
                    'parent_comment_id' => ['bsonType' => 'objectId'],
                    'text' => ['bsonType' => 'string', 'maxLength' => 400],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                    'author_username' => ['bsonType' => 'string'],
                    'author_role' => ['bsonType' => 'string'],
                ],
                'additionalProperties' => true,
            ]
        ]
    ]);
    $db->comments->createIndex(['project_id' => 1, 'media_id' => 1]);
    $db->comments->createIndex(['user_id' => 1]);
    $db->comments->createIndex(['parent_comment_id' => 1]);
    echo "✅ Comments-Collection erstellt.<br>";

    // --- B. LIKES ---
    $db->dropCollection("likes");
    $db->createCollection("likes", [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['project_id', 'media_id', 'user_id', 'type', 'created_at'],
                'properties' => [
                    'project_id' => ['bsonType' => 'objectId'],
                    'media_id' => ['bsonType' => 'objectId'],
                    'user_id' => ['bsonType' => 'objectId'],
                    'type' => ['enum' => ['like', 'dislike']],
                    'created_at' => ['bsonType' => 'date']
                ]
            ]
        ]
    ]);
    $db->likes->createIndex(['project_id' => 1, 'media_id' => 1]);
    $db->likes->createIndex(['user_id' => 1]);
    $db->likes->createIndex(['project_id' => 1, 'media_id' => 1, 'user_id' => 1], ['unique' => true]);
    echo "✅ Likes-Collection erstellt.<br>";

} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
?>
