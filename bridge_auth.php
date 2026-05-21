<?php

/**
 * Login von der klassischen Website (pages/login.php): gleiche Credentials wie Laravel,
 * Weiterleitung über laravel_handoff.php zur PHP-Session.
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=login');
    exit;
}

require_once __DIR__ . '/includes/app_env.php';

if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_params();
    session_start();
}

require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/two_factor.php';

require_once __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Services\BridgeRateLimiter::enforceOrRedirect('login');

if (! csrf_verify()) {
    header('Location: index.php?page=login&err=csrf');
    exit;
}

$login = trim((string) ($_POST['login_id'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

/** @var App\Models\User|null $user */
$user = App\Models\User::query()
    ->where(function ($q) use ($login) {
        $q->where('email', $login)->orWhere('username', strtolower($login));
    })
    ->first();

if ($user === null || ! Illuminate\Support\Facades\Hash::check($password, $user->password)) {
    header('Location: index.php?page=login&login_err=1');
    exit;
}

require_once __DIR__.'/includes/user_db.php';
require_once __DIR__.'/includes/user_moderation.php';
if (user_moderation_is_blocked($user->getAttributes())) {
    user_moderation_redirect_blocked($user->getAttributes());
}

if (two_factor_user_enabled($user)) {
    two_factor_set_login_pending((string) $user->getAuthIdentifier());
    header('Location: index.php?page=login&step=2fa');
    exit;
}

$handoff = $app->make(App\Services\LegacySiteHandoff::class);

if (! $handoff->isConfigured()) {
    header('Location: index.php?page=login&err=handoff');
    exit;
}

header('Location: '.$handoff->redirectUrl($user));
exit;
