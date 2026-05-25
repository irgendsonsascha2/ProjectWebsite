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

    $reset = site_settings_wants_reset_from_input();
    echo '<h1>Initialisierung: Allgemeine Einstellungen (site_pages)</h1>';
    if ($reset) {
        echo '<i>Modus: alle Werte auf Standard zurücksetzen.</i><br>';
    } else {
        echo '<i>Modus: Datensatz anlegen oder fehlende Felder ergänzen (z. B. <code>site_name</code>), bestehende Limits bleiben.</i><br>';
    }

    $result = site_settings_seed($db, true, $reset);
    if ($result === 'inserted') {
        echo '✅ site_settings angelegt (Standardwerte).<br>';
    } elseif ($result === 'replaced') {
        echo '✅ site_settings auf Standardwerte zurückgesetzt.<br>';
    } else {
        echo '✅ site_settings aktualisiert (fehlende Felder ergänzt, bestehende Werte beibehalten).<br>';
    }
} catch (Exception $e) {
    echo '❌ Fehler in ' . basename(__FILE__) . ': ' . $e->getMessage() . '<br>';
}
