<?php

/**
 * Dokumentiert optionale Moderation-Felder auf users (account_moderation).
 * Nicht-destruktiv: optionaler Index für Admin-Listen.
 */

require __DIR__ . '/_guard.php';

echo '<h2>users: Kontosperre / Timeout (account_moderation)</h2>';
echo '<p>Erwartetes Objekt <code>account_moderation</code> (wird von der App bei Bedarf gesetzt):</p>';
echo '<ul>';
echo '<li><code>status</code> — <code>active</code>, <code>suspended</code> (Timeout) oder <code>banned</code></li>';
echo '<li><code>until</code> — Ablaufzeitpunkt bei Timeout (ISO-Datum)</li>';
echo '<li><code>reason_key</code> — vordefinierter Grund (z. B. <code>terms</code>, <code>custom</code>)</li>';
echo '<li><code>reason_custom</code> — Freitext bei <code>custom</code></li>';
echo '<li><code>show_reason</code> — ob der Nutzer den Grund sieht</li>';
echo '<li><code>updated_at</code>, <code>updated_by</code></li>';
echo '</ul>';

try {
    if (! isset($db)) {
        [, $db] = get_admin_mongo_connection();
    }

    $db->users->createIndex(
        ['account_moderation.status' => 1],
        ['name' => 'users_account_moderation_status', 'sparse' => true]
    );
    echo '✅ Index <code>users_account_moderation_status</code> (sparse) gesetzt oder bereits vorhanden.<br>';
} catch (Throwable $e) {
    echo '⚠️ Index konnte nicht gesetzt werden: '.htmlspecialchars($e->getMessage()).'<br>';
}
