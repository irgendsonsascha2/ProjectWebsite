<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/site_pages.php';

use MongoDB\BSON\UTCDateTime;

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: Site Pages (Collection + Startseite)</h1>";

    $db->dropCollection("site_pages");
    $db->createCollection("site_pages", [
        'validator' => site_page_collection_validator(),
    ]);
    echo "✅ site_pages-Collection erstellt.<br>";

    $now = new UTCDateTime();

    $db->site_pages->insertOne([
        '_id' => 'home_profile',
        'page_kind' => 'home_profile',
        'display_name' => 'Dein Name',
        'kicker' => 'Deine Position / Spezialisierung',
        'lead' => "Kurze Beschreibung.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
        'body' => "Langer Text.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.\n\nSed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris.",
        'portrait_url' => '',
        'created_at' => $now,
        'updated_at' => $now,
        'updated_by' => '',
    ]);
    echo "✅ home_profile eingefügt.<br>";
    echo "<i>Rechtstexte separat: 06 (Impressum), 07 (Datenschutz), 08 (Nutzungsbedingungen).</i><br>";
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
