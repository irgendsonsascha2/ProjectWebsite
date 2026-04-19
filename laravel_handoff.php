<?php

/**
 * Nach Laravel-Login: kurzlebiger Redirect mit HMAC — setzt dieselbe PHP-Session wie pages/account.php.
 *
 * Konfiguration: laravel/.env → HANDOFF_SECRET, LEGACY_AFTER_LOGIN_PAGE (optional),
 * Laravel nutzt zusätzlich LEGACY_SITE_URL für den Link hierher.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/laravel/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__.'/laravel')->safeLoad();

$legacyBase = rtrim((string) ($_ENV['LEGACY_SITE_URL'] ?? getenv('LEGACY_SITE_URL') ?: ''), '/');
$laravelLogin = rtrim((string) ($_ENV['APP_URL'] ?? 'http://127.0.0.1:8000'), '/').'/login';

/**
 * Bei Handoff-Fehlern: zurück zur klassischen Account-Seite (falls LEGACY_SITE_URL gesetzt),
 * sonst Laravel-/login — sonst bricht die Verbindung ab, wenn :8000 nicht läuft.
 */
$redirectHandoffFailure = static function (string $reason) use ($legacyBase, $laravelLogin): void {
    if ($legacyBase !== '') {
        header('Location: '.$legacyBase.'/index.php?page=account&handoff_err='.rawurlencode($reason));
        exit;
    }
    header('Location: '.$laravelLogin.'?handoff='.rawurlencode($reason));
    exit;
};

$secret = (string) ($_ENV['HANDOFF_SECRET'] ?? getenv('HANDOFF_SECRET') ?: '');

if ($secret === '') {
    $redirectHandoffFailure('config');
}

$uid = isset($_GET['uid']) ? (string) $_GET['uid'] : '';
$exp = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
$sig = isset($_GET['sig']) ? (string) $_GET['sig'] : '';

if ($uid === '' || $exp < time() || $sig === '') {
    $redirectHandoffFailure('expired');
}

$payload = $uid.'|'.$exp;
$expected = hash_hmac('sha256', $payload, $secret);

if (! hash_equals($expected, $sig)) {
    $redirectHandoffFailure('sig');
}

require_once __DIR__ . '/includes/db.php';

/** @var MongoDB\Database $db */
[$_client, $db] = get_app_mongo_connection();

$user = null;
try {
    $oid = new MongoDB\BSON\ObjectId($uid);
    $user = $db->users->findOne(['_id' => $oid]);
} catch (Throwable) {
    $user = $db->users->findOne(['_id' => $uid]);
}

if (! $user) {
    $redirectHandoffFailure('user');
}

$_SESSION['user_id'] = (string) $user['_id'];
$_SESSION['email'] = $user['email'] ?? '';
$_SESSION['username'] = $user['username'] ?? '';
$_SESSION['role'] = $user['role'] ?? 'viewer';

$roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
if ($roleData && isset($roleData['permissions'])) {
    $_SESSION['permissions'] = iterator_to_array($roleData['permissions']);
} else {
    $_SESSION['permissions'] = [];
}

$page = (string) ($_ENV['LEGACY_AFTER_LOGIN_PAGE'] ?? getenv('LEGACY_AFTER_LOGIN_PAGE') ?: 'home');
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?: 'home';

header('Location: index.php?page='.$page);
exit;
