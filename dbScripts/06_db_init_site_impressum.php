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

    echo "<h1>Initialisierung: Impressum (site_pages)</h1>";

    $result = site_page_seed_legal_page($db, 'impressum', false);
    if ($result === 'inserted') {
        echo "✅ impressum angelegt (Platzhalter-Daten).<br>";
    } elseif ($result === 'replaced') {
        echo "✅ impressum auf Platzhalter-Inhalt zurückgesetzt.<br>";
    } else {
        echo "ℹ️ impressum unverändert.<br>";
    }
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
