<?php

/**
 * Nach Laravel-Login: kurzlebiger Redirect mit HMAC + Einmal-Nonce — setzt dieselbe PHP-Session wie die klassische Site.
 *
 * Konfiguration: laravel/.env → HANDOFF_SECRET, LEGACY_AFTER_LOGIN_PAGE (optional),
 * Laravel nutzt zusätzlich LEGACY_SITE_URL für den Link hierher.
 */

declare(strict_types=1);

$handoffLog = static function (Throwable $e): void {
    $logDir = __DIR__.'/logs';
    if (! is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir.'/handoff_errors.log',
        date('c').' '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString()."\n\n",
        FILE_APPEND
    );
};

require_once __DIR__ . '/includes/app_env.php';
require_once __DIR__ . '/includes/security_headers.php';
security_headers_send();
require_once __DIR__ . '/includes/rate_limit.php';

if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_params();
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
        header('Location: '.$legacyBase.'/index.php?page=login&handoff_err='.rawurlencode($reason));
        exit;
    }
    header('Location: '.$laravelLogin.'?handoff='.rawurlencode($reason));
    exit;
};

$secret = (string) ($_ENV['HANDOFF_SECRET'] ?? getenv('HANDOFF_SECRET') ?: '');

if ($secret === '') {
    $redirectHandoffFailure('config');
}

$recordHandoffFailure = static function () use ($redirectHandoffFailure): void {
    if (! rate_limit_allow('handoff_fail', 20, 900)) {
        $redirectHandoffFailure('throttle');
    }
    $redirectHandoffFailure('expired');
};

$uid = isset($_GET['uid']) ? (string) $_GET['uid'] : '';
$exp = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
$nonce = isset($_GET['nonce']) ? (string) $_GET['nonce'] : '';
$sig = isset($_GET['sig']) ? (string) $_GET['sig'] : '';

if ($uid === '' || $exp < time() || $nonce === '' || $sig === '') {
    $recordHandoffFailure();
}

$payload = $uid.'|'.$exp.'|'.$nonce;
$expected = hash_hmac('sha256', $payload, $secret);

if (! hash_equals($expected, $sig)) {
    $recordHandoffFailure();
}

try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/user_db.php';
    require_once __DIR__ . '/includes/user_moderation.php';
    require_once __DIR__ . '/includes/authz.php';
    require_once __DIR__ . '/includes/handoff_tokens.php';

    /** @var MongoDB\Database $db */
    [, $db] = get_admin_mongo_connection();
    handoff_token_ensure_indexes($db);

    $consumedUid = handoff_token_consume($db, $nonce, $exp);
    if ($consumedUid === null || $consumedUid !== $uid) {
        if (! rate_limit_allow('handoff_fail', 20, 900)) {
            $redirectHandoffFailure('throttle');
        }
        $redirectHandoffFailure('replay');
    }

    $user = user_find_public_by_id($db, $uid);

    if ($user === null) {
        $redirectHandoffFailure('user');
    }

    if (user_moderation_is_blocked($user)) {
        if ($legacyBase !== '') {
            user_moderation_redirect_blocked($user);
        }
        $redirectHandoffFailure('user');
    }

    if (! headers_sent()) {
        session_regenerate_id(true);
    }

    authz_apply_user_to_session($user, $db);

    $page = (string) ($_ENV['LEGACY_AFTER_LOGIN_PAGE'] ?? getenv('LEGACY_AFTER_LOGIN_PAGE') ?: 'home');
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?: 'home';

    header('Location: index.php?page='.$page);
    exit;
} catch (Throwable $e) {
    $handoffLog($e);
    $redirectHandoffFailure('error');
}
