<?php

require_once __DIR__ . '/../includes/laravel_app_url.php';
require_once __DIR__ . '/../includes/app_env.php';

$bridgeAuthUrl = legacy_site_base_url().'/bridge_auth.php';
$bridgeAuth2faUrl = legacy_site_base_url().'/bridge_auth_2fa.php';

$message = '';
$messageClass = 'alert';

// CSRF-Token für bridge_auth.php (csrf_field / csrf_token)
if (! isset($_SESSION['user_id'])) {
    csrf_token();
}

// Rückmeldungen vom Login-Bridge
if (isset($_GET['login_err'])) {
    $message = '❌ Fehler: E-Mail oder Passwort falsch.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'csrf') {
    $message = '❌ Formular ungültig oder Sitzung abgelaufen — bitte erneut versuchen.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'handoff') {
    $message = '❌ Anmeldung nicht möglich: In laravel/.env fehlen HANDOFF_SECRET oder LEGACY_SITE_URL passt nicht zur Website-URL.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'forbidden') {
    $message = '❌ Du hast keine Berechtigung für diese Aktion. Bitte melde dich an.';
    $messageClass = 'alert alert--error';
    $next = isset($_GET['next']) ? trim((string) $_GET['next']) : '';
    if ($next !== '') {
        $safeNext = htmlspecialchars($next, ENT_QUOTES, 'UTF-8');
        $message .= ' <a href="'.$safeNext.'">Zurück</a>';
    }
}
if (isset($_GET['err']) && $_GET['err'] === '2fa') {
    $message = '❌ Ungültiger Authenticator- oder Backup-Code.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && $_GET['err'] === 'moderation') {
    $message = '❌ ';
    if (! empty($_SESSION['moderation_message_html'])) {
        $message .= (string) $_SESSION['moderation_message_html'];
        unset($_SESSION['moderation_message_html'], $_SESSION['moderation_message']);
    } elseif (! empty($_SESSION['moderation_message'])) {
        $message .= htmlspecialchars((string) $_SESSION['moderation_message'], ENT_QUOTES, 'UTF-8');
        unset($_SESSION['moderation_message']);
    } else {
        $message .= 'Dein Konto ist derzeit gesperrt.';
    }
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
        $message = '❌ Anmelde-Link abgelaufen — bitte erneut anmelden.';
    } elseif ($h === 'sig') {
        $message = '❌ Anmelde-Link ungültig (Signatur) — bitte erneut anmelden.';
    } elseif ($h === 'user') {
        $message = '❌ Benutzer in der Datenbank nicht gefunden.';
    } else {
        $message = '❌ Anmeldung (Handoff) fehlgeschlagen.';
    }
    $messageClass = 'alert alert--error';
}

require_once __DIR__ . '/../includes/two_factor.php';

$show2faStep = isset($_GET['step']) && (string) $_GET['step'] === '2fa' && two_factor_login_pending_valid();

if (isset($_SESSION['user_id']) && ! $show2faStep && (! isset($_GET['err']) || $_GET['err'] !== 'forbidden')) {
    header('Location: index.php?page=account');
    exit;
}
?>

<div class="container">
    <h1>Anmelden</h1>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($show2faStep): ?>
    <section class="login-2fa-section">
        <p class="field-hint" style="margin-bottom:1rem;">Passwort OK — gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
        <form method="POST" id="login-2fa-form" action="<?php echo htmlspecialchars($bridgeAuth2faUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="totp_code">Authenticator-Code</label>
                <input type="text" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" autocomplete="one-time-code" autofocus>
            </div>

            <p class="field-hint login-2fa-alt-link">
                <a href="#" id="login-2fa-alt-toggle" aria-expanded="false" aria-controls="login-2fa-alt-panel">Andere Auth-Methoden</a>
            </p>

            <div id="login-2fa-alt-panel" class="login-2fa-alt-panel" hidden>
                <div class="field">
                    <label for="backup_code">Backup-Code</label>
                    <input type="text" id="backup_code" name="backup_code" placeholder="XXXXXXXXXX" autocomplete="off" spellcheck="false">
                </div>
            </div>

            <button type="submit">Anmeldung abschließen</button>
        </form>
        <script>
        (function () {
            var toggle = document.getElementById('login-2fa-alt-toggle');
            var panel = document.getElementById('login-2fa-alt-panel');
            if (!toggle || !panel) return;
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                var open = panel.hidden;
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    var backup = document.getElementById('backup_code');
                    if (backup) backup.focus();
                }
            });
            <?php if (isset($_GET['err']) && (string) $_GET['err'] === '2fa'): ?>
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            <?php endif; ?>
        })();
        </script>
        <p class="field-hint" style="margin-top:1rem;"><a href="index.php?page=login">Abbrechen und neu anmelden</a></p>
    </section>
    <?php else: ?>
    <section>
        <p class="field-hint" style="margin-bottom:1rem;">Hier meldest du dich mit Nutzerdaten und Passwort aus der Datenbank an (technisch dieselbe Prüfung wie bei der geschützten Login-Routine).</p>
        <?php if (laravel_app_reachable()): ?>
            <p class="field-hint" style="margin-bottom:1rem;">
                <a href="<?php echo htmlspecialchars(laravel_app_url().'/forgot-password', ENT_QUOTES, 'UTF-8'); ?>">Passwort vergessen</a>
                (Laravel unter <?php echo htmlspecialchars(laravel_app_url(), ENT_QUOTES, 'UTF-8'); ?>)
            </p>
        <?php else: ?>
            <p class="field-hint" style="margin-bottom:1rem;">
                Passwort vergessen ist lokal über Laravel verfügbar, aber die Laravel-App läuft gerade nicht unter
                <?php echo htmlspecialchars(laravel_app_url(), ENT_QUOTES, 'UTF-8'); ?>.
                Starte sie z. B. mit: <code>cd laravel &amp;&amp; php artisan serve --host 127.0.0.1 --port 8000</code>
            </p>
        <?php endif; ?>
        <form method="POST" id="login-form" action="<?php echo htmlspecialchars($bridgeAuthUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo csrf_field(); ?>
            <input type="text" id="login_id" name="login_id" placeholder="E-Mail oder Username" autocomplete="username" required>
            <input type="password" id="login_password" name="password" placeholder="Passwort" autocomplete="current-password" required>
            <button type="submit">Login</button>
        </form>
    </section>
    <?php endif; ?>

    <hr class="account-divider">

    <p class="field-hint">Noch kein Konto? <a href="index.php?page=register">Registrieren</a></p>
</div>

