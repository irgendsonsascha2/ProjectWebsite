<?php
/**
 * Master Database Initializer
 * Scans for scripts starting with 00, 01, 02... and runs them.
 */

require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/schema_migrations.php';

echo "<h1>🚀 MongoDB Master-Initialisierung</h1>";
echo "<div style='font-family: monospace; background: #222; color: #0f0; padding: 20px; border-radius: 5px;'>";

try {
    // Zentraler Verbindungsaufbau: Diese Variablen werden von den Unter-Scripten genutzt
    if (!isset($db) || !isset($client)) {
        [$client, $db] = get_admin_mongo_connection();
    }

    // 1. Alle .php Dateien im aktuellen Verzeichnis finden, die mit 00, 01 etc. beginnen
    $scripts = glob(__DIR__ . "/[0-9][0-9]*.php");

    // 2. Sortieren (00, 01, 02...)
    sort($scripts);

    if (empty($scripts)) {
        echo "❌ Keine Initialisierungs-Scripte (00_..., 01_...) gefunden.<br>";
    } else {
        foreach ($scripts as $script) {
            $filename = basename($script);
            
            // Verhindern, dass sich das Master-Script selbst aufruft
            if ($filename === basename(__FILE__)) continue;

            echo "--- Starte: <b>$filename</b> ---<br>";
            
            // Hier nutzen wir include. Die Variablen $client und $db sind im $script verfügbar!
            include $script;

            if (schema_migration_trackable($filename)) {
                schema_migration_record($db, $filename, schema_migration_actor_for_run());
                echo "📋 <code>schema_migrations</code>: $filename protokolliert.<br>";
            }
            
            echo "<br>✅ $filename fertig.<br><br>";
        }
    }

} catch (Exception $e) {
    echo "<br>❌ KRITISCHER FEHLER im Master: " . $e->getMessage() . "<br>";
}

echo "--- Alle Prozesse beendet ---";
echo "</div>";
