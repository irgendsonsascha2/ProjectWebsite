<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/site_pages.php';

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

    $result = site_page_seed_home_profile($db, false);
    if ($result === 'inserted') {
        echo "✅ home_profile angelegt (Platzhalter-Daten).<br>";
    } elseif ($result === 'replaced') {
        echo "✅ home_profile auf Platzhalter-Inhalt zurückgesetzt.<br>";
    } else {
        echo "ℹ️ home_profile unverändert.<br>";
    }
    echo "<i>Rechtstexte separat: 06 (Impressum), 07 (Datenschutz), 08 (Nutzungsbedingungen).</i><br>";
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
