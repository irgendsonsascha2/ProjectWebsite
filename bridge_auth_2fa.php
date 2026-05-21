<?php

/**
 * Zweite Login-Phase: TOTP oder Backup-Code nach erfolgreichem Passwort (bridge_auth.php).
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=login&step=2fa');
    exit;
}

require_once __DIR__ . '/includes/app_env.php';

if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_params();
    session_start();
}

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/two_factor.php';

if (! csrf_verify()) {
    header('Location: index.php?page=login&step=2fa&err=csrf');
    exit;
}

if (! two_factor_login_pending_valid()) {
    header('Location: index.php?page=login&login_err=1');
    exit;
}

$userId = (string) $_SESSION['login_2fa_user_id'];
$user = two_factor_find_user_by_id($userId);

if ($user === null || ! two_factor_user_enabled($user)) {
    two_factor_clear_login_pending();
    header('Location: index.php?page=login&login_err=1');
    exit;
}

require_once __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Services\BridgeRateLimiter::enforceOrRedirect('login_2fa');

$totp = trim((string) ($_POST['totp_code'] ?? ''));
$backup = trim((string) ($_POST['backup_code'] ?? ''));
$secret = (string) (is_array($user) ? ($user['two_factor_totp_secret'] ?? '') : ($user->two_factor_totp_secret ?? ''));

$ok = false;
if ($totp !== '' && two_factor_verify_totp($secret, $totp)) {
    $ok = true;
} elseif ($backup !== '' && two_factor_consume_backup_code($user, $backup)) {
    $ok = true;
}

if (! $ok) {
    header('Location: index.php?page=login&step=2fa&err=2fa');
    exit;
}

two_factor_clear_login_pending();

$laravelUser = App\Models\User::query()->find($userId);
if ($laravelUser === null) {
    header('Location: index.php?page=login&login_err=1');
    exit;
}

require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/user_db.php';
require_once __DIR__.'/includes/user_moderation.php';
[, $modDb] = get_admin_mongo_connection();
$modUser = user_moderation_find_by_id($modDb, $userId);
if ($modUser !== null && user_moderation_is_blocked($modUser)) {
    two_factor_clear_login_pending();
    user_moderation_redirect_blocked($modUser);
}

$handoff = $app->make(App\Services\LegacySiteHandoff::class);
if (! $handoff->isConfigured()) {
    header('Location: index.php?page=login&err=handoff');
    exit;
}

header('Location: '.$handoff->redirectUrl($laravelUser));
exit;
