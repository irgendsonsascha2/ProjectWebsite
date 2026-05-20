<?php

/**
 * Dokumentiert optionale 2FA-Felder auf users (kein Schema-Zwang — additionalProperties).
 * Optionaler Index für Abfragen nach aktivem 2FA.
 */

require __DIR__ . '/_guard.php';

echo '<h2>users: optionale 2FA-Felder</h2>';
echo '<p>Erwartete Felder (werden von der App bei Bedarf gesetzt):</p>';
echo '<ul>';
echo '<li><code>two_factor_enabled</code> (bool)</li>';
echo '<li><code>two_factor_totp_secret</code> (string, Base32)</li>';
echo '<li><code>two_factor_backup_codes</code> (array of password hashes)</li>';
echo '<li><code>two_factor_confirmed_at</code> (date)</li>';
echo '</ul>';

try {
    if (! isset($db)) {
        [, $db] = get_admin_mongo_connection();
    }

    $db->users->createIndex(
        ['two_factor_enabled' => 1],
        ['name' => 'users_two_factor_enabled', 'sparse' => true]
    );
    echo '✅ Index <code>users_two_factor_enabled</code> (sparse) gesetzt oder bereits vorhanden.<br>';
} catch (Throwable $e) {
    echo '⚠️ Index konnte nicht gesetzt werden: ' . htmlspecialchars($e->getMessage()) . '<br>';
}

echo '<p>Einrichtung und Login-2FA: <code>pages/account.php</code>, <code>bridge_auth.php</code>, <code>bridge_auth_2fa.php</code>.</p>';
