<?php
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/site_settings.php';

try {
    if (!isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo "<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>";
    } else {
        echo "<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>";
    }

    echo "<h1>Initialisierung: Allgemeine Einstellungen (site_pages)</h1>";

    $result = site_settings_seed($db, true);
    if ($result === 'inserted') {
        echo "✅ site_settings angelegt (Standardwerte).<br>";
    } else {
        echo "✅ site_settings auf Standardwerte zurückgesetzt.<br>";
    }
} catch (Exception $e) {
    echo "❌ Fehler in " . basename(__FILE__) . ": " . $e->getMessage() . "<br>";
}
