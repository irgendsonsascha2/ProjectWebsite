<?php

/**
 * Registrierung von der klassischen Website (pages/account.php): dieselbe Logik wie
 * RegisteredUserController (RegisterInvitedUser), danach Handoff wie bridge_auth.php.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=register');
    exit;
}

require_once __DIR__ . '/includes/app_env.php';

if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_params();
    session_start();
}

require_once __DIR__ . '/includes/csrf.php';

require_once __DIR__ . '/laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Services\BridgeRateLimiter::enforceOrRedirect('register');

if (! csrf_verify()) {
    header('Location: index.php?page=register&err=csrf');
    exit;
}

$privacyOk = isset($_POST['privacy_consent_register']) && (string) $_POST['privacy_consent_register'] === '1';
if (! $privacyOk) {
    header('Location: index.php?page=register&err=privacy');
    exit;
}

$contentOk = isset($_POST['content_responsibility_consent_register']) && (string) $_POST['content_responsibility_consent_register'] === '1';
if (! $contentOk) {
    header('Location: index.php?page=register&err=content');
    exit;
}

$data = [
    'registration_code' => trim((string) ($_POST['reg_code'] ?? '')),
    'username' => (string) ($_POST['username'] ?? ''),
    'email' => (string) ($_POST['email'] ?? ''),
    'password' => (string) ($_POST['password'] ?? ''),
    'password_confirmation' => (string) ($_POST['password_confirmation'] ?? $_POST['password'] ?? ''),
    'content_responsibility_consent' => true,
];

try {
    $user = $app->make(App\Services\RegisterInvitedUser::class)->register($data);
} catch (ValidationException $e) {
    $_SESSION['register_validation_errors'] = $e->errors();
    $code = rawurlencode($data['registration_code'] ?? '');
    $qs = $code !== '' ? '&reg_token='.$code : '';
    header('Location: index.php?page=register&register_err=1'.$qs.'#register-section');
    exit;
}

Auth::login($user);

$handoff = $app->make(App\Services\LegacySiteHandoff::class);

if (! $handoff->isConfigured()) {
    header('Location: index.php?page=register&err=handoff');
    exit;
}

header('Location: '.$handoff->redirectUrl($user));
exit;
