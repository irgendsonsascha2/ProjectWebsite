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
} catch (Throwable $e) {
    echo '❌ Fehler: '.htmlspecialchars($e->getMessage()).'<br>';
}
