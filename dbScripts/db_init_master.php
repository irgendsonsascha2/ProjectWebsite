<?php
/**
 * Master Database Initializer
 * Scans for scripts starting with 00, 01, 02... and runs them.
 */

require __DIR__ . '/../vendor/autoload.php';

echo "<h1>🚀 MongoDB Master-Initialisierung</h1>";
echo "<div style='font-family: monospace; background: #222; color: #0f0; padding: 20px; border-radius: 5px;'>";

// 1. Alle .php Dateien im aktuellen Verzeichnis finden
$scripts = glob(__DIR__ . "/[0-9][0-9]*.php");

// 2. Sortieren (00, 01, 02...)
sort($scripts);

if (empty($scripts)) {
    echo "❌ Keine Initialisierungs-Scripte (00_..., 01_...) gefunden.<br>";
} else {
    foreach ($scripts as $script) {
        $filename = basename($script);
        
        // Verhindern, dass sich das Master-Script selbst aufruft (falls es mit Zahlen beginnt)
        if ($filename === basename(__FILE__)) continue;

        echo "--- Starte: <b>$filename</b> ---<br>";
        
        try {
            // Script einbinden und ausführen
            // Hinweis: Da die Scripte 'require' nutzen, werden sie direkt ausgeführt.
            include $script;
            echo "<br>✅ Abgeschlossen.<br><br>";
        } catch (Exception $e) {
            echo "<br>❌ FEHLER in $filename: " . $e->getMessage() . "<br>";
            // Optional: Abbrechen, wenn ein Fehler auftritt
            break; 
        }
    }
}

echo "--- Alle Prozesse beendet ---";
echo "</div>";