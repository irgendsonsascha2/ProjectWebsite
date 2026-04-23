<?php

require_once __DIR__ . '/../includes/laravel_app_url.php';

$message = '';
$messageClass = 'alert';

// CSRF für bridge_register.php
if (! isset($_SESSION['user_id'])) {
    if (empty($_SESSION['csrf_bridge'])) {
        $_SESSION['csrf_bridge'] = bin2hex(random_bytes(32));
    }
}

if (! empty($_SESSION['register_validation_errors'])) {
    $errs = $_SESSION['register_validation_errors'];
    unset($_SESSION['register_validation_errors']);
    $parts = [];
    foreach ($errs as $msgs) {
        foreach ((array) $msgs as $m) {
            $parts[] = $m;
        }
    }
    if ($parts !== []) {
        $message = '❌ '.implode(' ', $parts);
        $messageClass = 'alert alert--error';
    }
}

if (isset($_GET['err']) && $_GET['err'] === 'csrf') {
    $message = '❌ Formular ungültig oder Sitzung abgelaufen — bitte erneut versuchen.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'handoff') {
    $message = '❌ Registrierung nicht möglich: In laravel/.env fehlen HANDOFF_SECRET oder LEGACY_SITE_URL passt nicht zur Website-URL.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'throttle') {
    $w = isset($_GET['wait']) ? (int) $_GET['wait'] : 0;
    $message = $w > 0
        ? "❌ Zu viele Versuche — bitte {$w} Sekunden warten und erneut versuchen."
        : '❌ Zu viele Versuche — bitte kurz warten und erneut versuchen.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['handoff_err'])) {
    $h = (string) $_GET['handoff_err'];
    if ($h === 'config') {
        $message = '❌ Handoff: HANDOFF_SECRET fehlt in laravel/.env (oder .env nicht lesbar).';
    } elseif ($h === 'expired') {
        $message = '❌ Anmelde-Link abgelaufen — bitte erneut versuchen.';
    } elseif ($h === 'sig') {
        $message = '❌ Anmelde-Link ungültig (Signatur) — bitte erneut versuchen.';
    } elseif ($h === 'user') {
        $message = '❌ Benutzer in der Datenbank nicht gefunden.';
    } else {
        $message = '❌ Registrierung (Handoff) fehlgeschlagen.';
    }
    $messageClass = 'alert alert--error';
}

$prefilledCode = isset($_GET['reg_token']) ? htmlspecialchars((string) $_GET['reg_token'], ENT_QUOTES, 'UTF-8') : '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=account');
    exit;
}
?>

<div class="container">
    <h1>Registrieren</h1>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <section id="register-section">
        <form method="POST" id="register-form" action="bridge_register.php">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_bridge'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" id="reg_code" name="reg_code" placeholder="Einmal-Code" value="<?php echo $prefilledCode; ?>" autocomplete="one-time-code" required>
            <input type="text" id="reg_username" name="username" placeholder="Username" autocomplete="new-username" required>
            <small class="field-hint">3–20 Zeichen: a–z, 0–9, . _ -</small>
            <input type="email" id="reg_email" name="email" placeholder="E-Mail Adresse" autocomplete="email" required>
            <input type="password" id="reg_password" name="password" placeholder="Passwort wählen" autocomplete="new-password" required>
            <button type="submit" name="register">Konto erstellen</button>
        </form>
    </section>

    <hr class="account-divider">

    <p class="field-hint">Schon ein Konto? <a href="index.php?page=login">Anmelden</a></p>
</div>

