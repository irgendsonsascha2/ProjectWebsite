<?php

require_once __DIR__.'/../includes/two_factor.php';
require_once __DIR__.'/../includes/two_factor_handlers.php';

if (! isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login&err=forbidden&next='.rawurlencode('index.php?page=two_factor'));
    exit;
}

$userId = (string) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    two_factor_maybe_auto_begin($userId);
}

$state = two_factor_handle_post($userId);

if (isset($_POST['two_factor_cancel_setup'])) {
    header('Location: index.php?page=account');
    exit;
}

$message = $state['message'];
$messageClass = $state['messageClass'];
$twoFactorEnabled = $state['twoFactorEnabled'];
$twoFactorSetupSecret = $state['twoFactorSetupSecret'];
$displayBackupCodes = $state['displayBackupCodes'];
$accountEmail = $state['accountEmail'];

$twoFactorPageUrl = 'index.php?page=two_factor';
$twoFactorHelpTitle = 'Was ist Zwei-Faktor-Authentifizierung?';
?>

<div id="two-factor-help-content" hidden>
    <?php require __DIR__.'/../includes/partials/two_factor_help_content.php'; ?>
</div>

<div class="container two-factor-page">
    <p class="field-hint"><a href="index.php?page=account">← Zurück zum Account</a></p>

    <h1 class="two-factor-page-title">Zwei-Faktor-Authentifizierung (2FA)</h1>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="two-factor-panel two-factor-actions">
        <div class="two-factor-panel-head">
            <h2>Dein Konto</h2>
            <div
                data-react-help-button
                data-help-title="<?php echo htmlspecialchars($twoFactorHelpTitle, ENT_QUOTES, 'UTF-8'); ?>"
                data-help-source="two-factor-help-content"
            ></div>
        </div>
        <p class="field-hint">E-Mail: <?php echo htmlspecialchars($accountEmail, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($displayBackupCodes !== null && count($displayBackupCodes) > 0): ?>
            <?php $backupCopyText = implode("\n", $displayBackupCodes); ?>
            <div class="alert alert--success">
                <p><strong>Backup-Codes</strong> — sicher aufbewahren (jeder Code nur einmal):</p>
                <div
                    class="two-factor-backup-copy"
                    data-react-copy-button
                    data-copy-variant="labeled"
                    data-copy-text="<?php echo htmlspecialchars($backupCopyText, ENT_QUOTES, 'UTF-8'); ?>"
                    data-copy-button-text="Backup-Codes kopieren"
                    aria-label="Backup-Codes kopieren"
                ></div>
                <ul>
                    <?php foreach ($displayBackupCodes as $bc): ?>
                        <li><code><?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:0.75rem;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="two_factor_dismiss_backup" value="1">
                    <button type="submit" class="btn-primary">Verstanden — Codes notiert</button>
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
                <button type="submit" class="btn-secondary">Neue Backup-Codes erzeugen</button>
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
            <p>Status: <strong class="two-factor-status--off">Einrichtung läuft</strong></p>
            <h3>App verbinden</h3>
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
            <h3>Bestätigen</h3>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_confirm" value="1">
                <div class="field">
                    <label for="setup_totp">6-stelliger Code aus der App</label>
                    <input type="text" id="setup_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                </div>
                <button type="submit" class="btn-primary">2FA aktivieren</button>
            </form>
            <form method="POST" action="<?php echo htmlspecialchars($twoFactorPageUrl, ENT_QUOTES, 'UTF-8'); ?>" style="margin-top:0.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_cancel_setup" value="1">
                <button type="submit" class="btn-muted">Einrichtung abbrechen</button>
            </form>
        <?php endif; ?>
    </section>
</div>
