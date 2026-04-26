<?php

/**
 * Einmal ausführen auf bestehenden DBs: lockert den MongoDB-Validator für `users`,
 * damit Laravel zusätzliche Felder speichern kann (remember_token etc.).
 *
 * Neue Installationen: bereits durch angepasstes 00_db_init_accounts.php abgedeckt.
 */

require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/db.php';

try {
    if (! isset($db)) {
        [, $db] = get_admin_mongo_connection();
        echo '<i>(Eigenständiger Modus)</i><br>';
    }

    echo '<h1>users: Validator für Laravel ergänzen</h1>';

    $validator = [
        '$jsonSchema' => [
            'bsonType' => 'object',
            'required' => ['email', 'username', 'password', 'role', 'created_at'],
            'properties' => [
                'email' => ['bsonType' => 'string', 'pattern' => '^.+@.+$'],
                'username' => ['bsonType' => 'string'],
                'password' => ['bsonType' => 'string'],
                'role' => ['bsonType' => 'string'],
                'created_at' => ['bsonType' => 'date'],
            ],
            'additionalProperties' => true,
        ],
    ];

    $db->command([
        'collMod' => 'users',
        'validator' => $validator,
        'validationLevel' => 'strict',
        'validationAction' => 'error',
    ]);

    echo '✅ collMod für Collection <code>users</code> ausgeführt (<code>additionalProperties: true</code>).<br>';
    echo '<p>Registrierung über Laravel / bridge_register sollte nun nicht mehr mit „Document failed validation“ abbrechen.</p>';

    echo '<hr>';
    echo '<h2>users: Unique-Index sicherstellen</h2>';
    echo '<p class="muted">Hinweis: Wenn bereits doppelte E-Mails/Usernames existieren, schlägt der Unique-Index fehl. Dann Duplikate bereinigen und das Script erneut ausführen.</p>';

    try {
        $db->users->createIndex(['email' => 1], ['unique' => true]);
        echo '✅ Unique-Index auf <code>users.email</code> ist gesetzt.<br>';
    } catch (Throwable $e) {
        echo '⚠️ Konnte Unique-Index auf <code>users.email</code> nicht setzen: ' . htmlspecialchars($e->getMessage()) . '<br>';
    }

    try {
        $db->users->createIndex(['username' => 1], ['unique' => true]);
        echo '✅ Unique-Index auf <code>users.username</code> ist gesetzt.<br>';
    } catch (Throwable $e) {
        echo '⚠️ Konnte Unique-Index auf <code>users.username</code> nicht setzen: ' . htmlspecialchars($e->getMessage()) . '<br>';
    }
} catch (Throwable $e) {
    echo '❌ Fehler: '.htmlspecialchars($e->getMessage()).'<br>';
}
