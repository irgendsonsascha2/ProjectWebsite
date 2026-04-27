<?php

require_once __DIR__ . '/../includes/laravel_app_url.php';
require_once __DIR__ . '/../includes/mail.php';

$message = '';
$messageClass = 'alert';

function site_base_url_from_request(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(str_replace(basename($script), '', $script), '/');
    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

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
if (isset($_GET['err']) && $_GET['err'] === 'privacy') {
    $message = '❌ Bitte bestätige den Datenschutz-Hinweis, um fortzufahren.';
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
$hasPrefilledCode = $prefilledCode !== '';

// Invite-Code anfragen (ohne reg_token Navigation)
if (isset($_POST['request_registration_code'])) {
    $postToken = (string)($_POST['_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_bridge'] ?? '');
    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $message = '❌ Formular ungültig oder Sitzung abgelaufen — bitte erneut versuchen.';
        $messageClass = 'alert alert--error';
    } else {
        $email = trim((string)($_POST['request_email'] ?? ''));
        $privacyOk = isset($_POST['privacy_consent']) && (string)$_POST['privacy_consent'] === '1';
        if (!$privacyOk) {
            $message = '❌ Bitte bestätige den Datenschutz-Hinweis, um fortzufahren.';
            $messageClass = 'alert alert--error';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '❌ Bitte eine gültige E-Mail-Adresse angeben.';
            $messageClass = 'alert alert--error';
        } else {
            $now = time();
            $last = (int)($_SESSION['registration_code_request_last_at'] ?? 0);
            if ($last > 0 && ($now - $last) < 60) {
                $message = '❌ Bitte kurz warten und dann erneut versuchen.';
                $messageClass = 'alert alert--error';
            } else {
                $_SESSION['registration_code_request_last_at'] = $now;

                $token = bin2hex(random_bytes(32));
                $expiresAt = new \MongoDB\BSON\UTCDateTime(($now + 60 * 60 * 24) * 1000);

                // Duplicate requests: allow re-send if not verified yet
                $db->registration_code_requests->insertOne([
                    'email' => $email,
                    'token' => $token,
                    'created_at' => new \MongoDB\BSON\UTCDateTime(),
                    'expires_at' => $expiresAt,
                    'verified_at' => null,
                    'approved_at' => null,
                    'code' => null,
                    'privacy_consent_at' => new \MongoDB\BSON\UTCDateTime(),
                    'requested_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]);

                $base = site_base_url_from_request();
                $verifyUrl = $base . '/index.php?page=verify_registration_request&token=' . rawurlencode($token);
                $body = "Hallo!\n\nBitte bestätige deine E-Mail-Adresse, um einen Registrierungscode anzufragen:\n\n{$verifyUrl}\n\nDer Link ist 24 Stunden gültig.\n";
                $mailOk = send_plain_mail($email, 'E-Mail bestätigen: Registrierungscode', $body);

                if (!$mailOk) {
                    $message = '❌ Konnte E-Mail nicht senden (Server-Mail nicht konfiguriert?).';
                    $messageClass = 'alert alert--error';
                } else {
                    $message = '✅ Fast fertig! Bitte prüfe dein Postfach und bestätige den Link.';
                    $messageClass = 'alert alert--success';
                }
            }
        }
    }
}

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
        <?php if (!$hasPrefilledCode): ?>
            <p class="field-hint" style="margin-bottom: 1rem;">
                Für die Registrierung brauchst du einen <b>Registrierungscode</b>. Du kannst ihn hier manuell eingeben oder anfragen.
            </p>
            <button type="button" id="open-request-code-dialog">Registrierungscode anfragen</button>
        <?php endif; ?>

        <form method="POST" id="register-form" action="bridge_register.php" style="margin-top: 1rem;">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_bridge'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" id="reg_code" name="reg_code" placeholder="Registrierungscode" value="<?php echo $prefilledCode; ?>" autocomplete="one-time-code" required>
            <input type="text" id="reg_username" name="username" placeholder="Username" autocomplete="new-username" required>
            <small class="field-hint">3–20 Zeichen: a–z, 0–9, . _ -</small>
            <input type="email" id="reg_email" name="email" placeholder="E-Mail Adresse" autocomplete="email" required>
            <input type="password" id="reg_password" name="password" placeholder="Passwort wählen" autocomplete="new-password" required>
            <label class="privacy-consent">
                <input type="checkbox" name="privacy_consent_register" value="1" required>
                <span class="privacy-consent__text">
                    Ich habe die&nbsp;<a href="index.php?page=datenschutz">Datenschutzerklärung</a>&nbsp;gelesen und bin mit der Verarbeitung meiner Daten für die Registrierung einverstanden.
                </span>
            </label>
            <button type="submit" name="register">Konto erstellen</button>
        </form>
    </section>

    <?php if (!$hasPrefilledCode): ?>
        <dialog id="request-code-dialog">
            <div class="dialog-card">
                <div class="dialog-header">
                    <h2>Registrierungscode anfragen</h2>
                    <button type="button" class="close-button" data-dialog-close>Schließen</button>
                </div>
                <p class="field-hint" style="margin-bottom: 1rem;">
                    Du bekommst zuerst eine E-Mail zum Bestätigen. Nach Freigabe durch den Admin erhältst du deinen Code per E-Mail.
                </p>
                <form method="POST">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_bridge'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="email" name="request_email" placeholder="E-Mail Adresse" autocomplete="email" required>
                    <label class="privacy-consent privacy-consent--dialog">
                        <input type="checkbox" name="privacy_consent" value="1" required>
                        <span class="privacy-consent__text">
                            Ich habe die&nbsp;<a href="index.php?page=datenschutz">Datenschutzerklärung</a>&nbsp;gelesen und bin mit der Verarbeitung meiner Daten für diese Anfrage einverstanden.
                        </span>
                    </label>
                    <div style="display:flex; gap:0.5rem; margin-top: 1rem; align-items:center;">
                        <button type="submit" name="request_registration_code" value="1">Anfragen</button>
                        <button type="button" data-dialog-close>Abbrechen</button>
                    </div>
                </form>
            </div>
        </dialog>

        <script>
        (() => {
            const dialog = document.getElementById('request-code-dialog');
            const openBtn = document.getElementById('open-request-code-dialog');
            if (!dialog || !openBtn) return;

            function openDialog() {
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    // Fallback: wenn <dialog> nicht unterstützt wird
                    window.location.href = 'index.php?page=register#request-code';
                }
            }

            function closeDialog() {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                }
            }

            openBtn.addEventListener('click', openDialog);
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) closeDialog();
            });
            dialog.querySelectorAll('[data-dialog-close]').forEach((btn) => {
                btn.addEventListener('click', closeDialog);
            });

            // Auto-open if server returned a message from request action (success/error)
            const msg = document.querySelector('.<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>');
            if (msg && (msg.textContent || '').includes('Postfach')) {
                openDialog();
            }
        })();
        </script>
    <?php endif; ?>

    <hr class="account-divider">

    <p class="field-hint">Schon ein Konto? <a href="index.php?page=login">Anmelden</a></p>
</div>

