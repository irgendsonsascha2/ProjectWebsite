<?php

/**
 * Idempotent: site_settings in site_pages (Website-Name, Upload-Limits, Schutzmodus).
 * Legt fehlende Felder an — u. a. stress_auto_* für automatischen Schutzmodus bei Überlastung.
 */

require __DIR__.'/_guard.php';
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/site_settings.php';

try {
    if (! isset($db)) {
        [$client, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus: Neue Verbindung aufgebaut)</i><br>';
    } else {
        echo '<i>(Master-Modus: Bestehende Verbindung wird genutzt)</i><br>';
    }

    $reset = site_settings_wants_reset_from_input();
    echo '<h1>Initialisierung: Allgemeine Einstellungen (site_pages)</h1>';
    if ($reset) {
        echo '<i>Modus: alle Werte auf Standard zurücksetzen.</i><br>';
    } else {
        echo '<i>Modus: Datensatz anlegen oder fehlende Felder ergänzen (Upload-Limits, <code>site_name</code>, <strong>Schutzmodus</strong>), bestehende Werte bleiben.</i><br>';
    }

    $existing = $db->site_pages->findOne(['_id' => site_settings_document_id()]);
    $missingBefore = [];
    if ($existing && ! $reset) {
        $docArray = is_array($existing) ? $existing : iterator_to_array($existing);
        $missingBefore = site_settings_keys_missing_from_defaults($docArray);
    }

    $result = site_settings_seed($db, true, $reset);
    $settings = site_settings_load($db, true);

    if ($result === 'inserted') {
        echo '✅ site_settings angelegt (Standardwerte).<br>';
    } elseif ($result === 'replaced') {
        echo '✅ site_settings auf Standardwerte zurückgesetzt.<br>';
    } else {
        echo '✅ site_settings aktualisiert (fehlende Felder ergänzt, bestehende Werte beibehalten).<br>';
        if ($missingBefore !== []) {
            echo '<i>Ergänzte Schlüssel: '.htmlspecialchars(implode(', ', $missingBefore), ENT_QUOTES, 'UTF-8').'</i><br>';
        }
    }

    echo '<h2>Schutzmodus (nach Seed)</h2>';
    echo '<ul>';
    echo '<li>Manuell dauerhaft: '.(! empty($settings['stress_mode_enabled']) ? 'an' : 'aus').'</li>';
    echo '<li>Automatik bei Überlastung: '.(! empty($settings['stress_auto_enabled']) ? 'an' : 'aus').'</li>';
    echo '<li>Aktivierung ab: '.(int) ($settings['stress_auto_activate_rpm'] ?? 0).' Requests/min.</li>';
    echo '<li>Freigabe unter: '.(int) ($settings['stress_auto_release_rpm'] ?? 0).' Requests/min.</li>';
    echo '<li>Mindestdauer nach Auslösung: '.(int) ($settings['stress_auto_hold_minutes'] ?? 0).' Min.</li>';
    echo '</ul>';
    echo '<i>Laufzeit-Zähler und Auto-Status liegen in <code>logs/</code> (kein Mongo). Anpassung: Admin → Einstellungen.</i><br>';
} catch (Exception $e) {
    echo '❌ Fehler in '.basename(__FILE__).': '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'<br>';
}
