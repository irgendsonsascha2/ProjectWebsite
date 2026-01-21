<?php
/**
 * Master Database Initializer
 * Scans for scripts starting with 00, 01, 02... and runs them.
 */

require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

echo "<h1>🚀 MongoDB Master-Initialisierung</h1>";
echo "<div style='font-family: monospace; background: #222; color: #0f0; padding: 20px; border-radius: 5px;'>";

try {
    // Zentraler Verbindungsaufbau: Diese Variablen werden von den Unter-Scripten genutzt
    $client = new Client("mongodb://localhost:27017");
    $db = $client->portfolio_db;

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
            
            echo "<br>✅ $filename fertig.<br><br>";
        }
    }

} catch (Exception $e) {
    echo "<br>❌ KRITISCHER FEHLER im Master: " . $e->getMessage() . "<br>";
}

echo "--- Alle Prozesse beendet ---";
echo "</div>";