<?php

/**
 * Registrierung von der klassischen Website (pages/account.php): dieselbe Logik wie
 * RegisteredUserController (RegisterInvitedUser), danach Handoff wie bridge_auth.php.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=account');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Services\BridgeRateLimiter::enforceOrRedirect('register');

$sessionToken = $_SESSION['csrf_bridge'] ?? '';
$postToken = (string) ($_POST['_token'] ?? '');
if ($sessionToken === '' || ! hash_equals($sessionToken, $postToken)) {
    header('Location: index.php?page=account&err=csrf');
    exit;
}

$data = [
    'registration_code' => trim((string) ($_POST['reg_code'] ?? '')),
    'username' => (string) ($_POST['username'] ?? ''),
    'email' => (string) ($_POST['email'] ?? ''),
    'password' => (string) ($_POST['password'] ?? ''),
    'password_confirmation' => (string) ($_POST['password_confirmation'] ?? $_POST['password'] ?? ''),
];

try {
    $user = $app->make(App\Services\RegisterInvitedUser::class)->register($data);
} catch (ValidationException $e) {
    $_SESSION['register_validation_errors'] = $e->errors();
    header('Location: index.php?page=account&register_err=1#register-section');
    exit;
}

Auth::login($user);

$handoff = $app->make(App\Services\LegacySiteHandoff::class);

if (! $handoff->isConfigured()) {
    header('Location: index.php?page=account&err=handoff');
    exit;
}

header('Location: '.$handoff->redirectUrl($user));
exit;
