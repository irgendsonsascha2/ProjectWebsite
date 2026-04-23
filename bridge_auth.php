<?php

/**
 * Login von der klassischen Website (pages/account.php): gleiche Credentials wie Laravel,
 * Weiterleitung über laravel_handoff.php zur PHP-Session.
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=login');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Services\BridgeRateLimiter::enforceOrRedirect('login');

$sessionToken = $_SESSION['csrf_bridge'] ?? '';
$postToken = (string) ($_POST['_token'] ?? '');
if ($sessionToken === '' || ! hash_equals($sessionToken, $postToken)) {
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

$handoff = $app->make(App\Services\LegacySiteHandoff::class);

if (! $handoff->isConfigured()) {
    header('Location: index.php?page=login&err=handoff');
    exit;
}

header('Location: '.$handoff->redirectUrl($user));
exit;
