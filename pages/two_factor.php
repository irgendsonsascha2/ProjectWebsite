<?php

require_once __DIR__.'/../includes/two_factor.php';
require_once __DIR__.'/../includes/two_factor_handlers.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login&err=forbidden&next='.rawurlencode('index.php?page=two_factor'));
    exit;
}

$userId = (string) $_SESSION['user_id'];
$state = two_factor_handle_post($userId);

$message = $state['message'];
$messageClass = $state['messageClass'];
$twoFactorEnabled = $state['twoFactorEnabled'];
$twoFactorSetupSecret = $state['twoFactorSetupSecret'];
$displayBackupCodes = $state['displayBackupCodes'];
$accountEmail = $state['accountEmail'];

$twoFactorPageUrl = 'index.php?page=two_factor';
?>

<div class="container two-factor-page">
    <p class="field-hint"><a href="index.php?page=account">← Zurück zum Account</a></p>

    <h1>Zwei-Faktor-Authentifizierung (2FA)</h1>

    <section class="two-factor-panel two-factor-intro">
        <h2>Was ist das?</h2>
        <p>
            Die Zwei-Faktor-Authentifizierung schützt dein Konto zusätzlich zum Passwort.
            Du richtest eine <strong>Authenticator-App</strong> ein (z.&nbsp;B. Aegis, Google Authenticator, Bitwarden).
            Die App erzeugt alle 30 Sekunden einen neuen <strong>6-stelligen Code</strong>.
        </p>
        <p>
            <strong>Optional:</strong> Du musst 2FA nicht aktivieren. Ohne 2FA funktioniert der Login wie bisher — nur mit E-Mail/Username und Passwort.
        </p>
        <h3>So läuft der Login mit 2FA</h3>
        <ol class="two-factor-steps">
            <li>Passwort wie gewohnt auf der Anmeldeseite eingeben.</li>
            <li>Anschließend den Code aus der Authenticator-App (oder einen Backup-Code) eingeben.</li>
        </ol>
        <p class="field-hint">
            Es gibt <strong>keinen</strong> zweiten Faktor per E-Mail beim Login — nur TOTP in der App.
            Backup-Codes kannst du bei Einrichtung notieren; jeder Code ist nur <strong>einmal</strong> nutzbar, falls du kein Handy hast.
        </p>
    </section>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <section class="two-factor-panel two-factor-actions">
        <h2>Dein Konto</h2>
        <p class="field-hint">E-Mail: <?php echo htmlspecialchars($accountEmail, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($displayBackupCodes !== null && count($displayBackupCodes) > 0): ?>
            <div class="alert alert--success">
                <p><strong>Backup-Codes</strong> — sicher aufbewahren (jeder Code nur einmal):</p>
                <ul>
                    <?php foreach ($displayBackupCodes as $bc): ?>
                        <li><code><?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:0.75rem;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="two_factor_dismiss_backup" value="1">
                    <button type="submit">Verstanden — Codes notiert</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($twoFactorEnabled): ?>
            <p>Status: <strong class="two-factor-status--on">aktiv</strong></p>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="account-2fa-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_regenerate_backup" value="1">
                <div class="field">
                    <label for="regen_totp">Authenticator-Code (für neue Backup-Codes)</label>
                    <input type="text" id="regen_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <button type="submit">Neue Backup-Codes erzeugen</button>
            </form>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="account-2fa-form two-factor-disable-form" onsubmit="return confirm('2FA wirklich deaktivieren?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_disable" value="1">
                <div class="field">
                    <label for="disable_password">Passwort</label>
                    <input type="password" id="disable_password" name="password" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="disable_totp">Authenticator-Code</label>
                    <input type="text" id="disable_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <button type="submit" class="danger">2FA deaktivieren</button>
            </form>
        <?php elseif ($twoFactorSetupSecret !== ''): ?>
            <h3>Schritt 2: App verbinden</h3>
            <?php
                $twoFactorProvisioningUri = two_factor_provisioning_uri($accountEmail, $twoFactorSetupSecret);
                $qrBundlePath = __DIR__ . '/../js/qrcode.bundle.js';
                $qrScriptPath = __DIR__ . '/../js/two-factor-qr.js';
                $qrBundleVer = is_file($qrBundlePath) ? (string) filemtime($qrBundlePath) : (string) time();
                $qrScriptVer = is_file($qrScriptPath) ? (string) filemtime($qrScriptPath) : (string) time();
            ?>
            <div class="account-2fa-qr">
                <p class="field-hint">Scanne den QR-Code mit deiner Authenticator-App:</p>
                <canvas
                    id="two-factor-qr-canvas"
                    width="200"
                    height="200"
                    role="img"
                    aria-label="QR-Code für Zwei-Faktor-Einrichtung"
                    data-provisioning-uri="<?php echo htmlspecialchars($twoFactorProvisioningUri, ENT_QUOTES, 'UTF-8'); ?>"
                ></canvas>
                <p id="two-factor-qr-fallback" class="field-hint alert alert--error" hidden>
                    QR-Code konnte nicht geladen werden — Secret unten manuell eintragen.
                </p>
                <details class="account-2fa-manual-secret">
                    <summary>Secret manuell eingeben</summary>
                    <p class="field-hint"><code><?php echo htmlspecialchars($twoFactorSetupSecret, ENT_QUOTES, 'UTF-8'); ?></code></p>
                </details>
            </div>
            <script src="js/qrcode.bundle.js?v=<?php echo htmlspecialchars($qrBundleVer, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <script src="js/two-factor-qr.js?v=<?php echo htmlspecialchars($qrScriptVer, ENT_QUOTES, 'UTF-8'); ?>"></script>
            <h3>Schritt 3: Bestätigen</h3>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_confirm" value="1">
                <div class="field">
                    <label for="setup_totp">6-stelliger Code aus der App</label>
                    <input type="text" id="setup_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                </div>
                <button type="submit">2FA aktivieren</button>
            </form>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:0.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_cancel_setup" value="1">
                <button type="submit" class="link-button">Einrichtung abbrechen</button>
            </form>
        <?php else: ?>
            <p>Status: <strong class="two-factor-status--off">nicht aktiv</strong></p>
            <h3>Schritt 1: Einrichtung starten</h3>
            <p class="field-hint">Du erhältst einen QR-Code für die Authenticator-App und danach Backup-Codes.</p>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_begin" value="1">
                <button type="submit">2FA jetzt einrichten</button>
            </form>
        <?php endif; ?>
    </section>
</div>
