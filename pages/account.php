<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__.'/../includes/laravel_app_url.php';
require_once __DIR__.'/../includes/two_factor.php';

// Registrierung läuft über bridge_register.php + Laravel (RegisterInvitedUser).

// --- LOGOUT (nur POST + CSRF; zentral in bootstrap geprüft) ---
if (isset($_POST['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: index.php?page=login');
    exit();
}

// Legacy-Kompatibilität: alte Invite-Links zeigten auf ?page=account&reg_token=...#register-section
// Außerdem ist die Account-Seite jetzt nur noch das "eingeloggt"-Dashboard.
if (! isset($_SESSION['user_id'])) {
    if (isset($_GET['reg_token']) && (string) $_GET['reg_token'] !== '') {
        header('Location: index.php?page=register&reg_token='.rawurlencode((string) $_GET['reg_token']).'#register-section');
        exit();
    }
    header('Location: index.php?page=login');
    exit();
}

$message = '';
$messageClass = 'alert';

$userId = (string) ($_SESSION['user_id'] ?? '');
$accountUser = $userId !== '' ? two_factor_find_user_by_id($userId) : null;
$twoFactorEnabled = two_factor_user_enabled($accountUser);
$twoFactorSetupSecret = (string) ($_SESSION['two_factor_setup_secret'] ?? '');
$displayBackupCodes = $_SESSION['two_factor_display_backup_codes'] ?? null;
if (is_array($displayBackupCodes)) {
    $displayBackupCodes = array_values(array_filter($displayBackupCodes, 'is_string'));
} else {
    $displayBackupCodes = null;
}

if (isset($_POST['two_factor_begin'])) {
    $twoFactorSetupSecret = two_factor_generate_secret();
    $_SESSION['two_factor_setup_secret'] = $twoFactorSetupSecret;
    unset($_SESSION['two_factor_display_backup_codes']);
    $message = '✅ Secret erzeugt — scanne den Code oder trage das Secret manuell ein, dann bestätige mit einem 6-stelligen Code.';
    $messageClass = 'alert alert--success';
}

if (isset($_POST['two_factor_cancel_setup'])) {
    unset($_SESSION['two_factor_setup_secret']);
    $twoFactorSetupSecret = '';
    $message = 'Einrichtung abgebrochen.';
    $messageClass = 'alert';
}

if (isset($_POST['two_factor_confirm']) && $twoFactorSetupSecret !== '') {
    $code = trim((string) ($_POST['totp_code'] ?? ''));
    if (! two_factor_verify_totp($twoFactorSetupSecret, $code)) {
        $message = '❌ Code ungültig — bitte erneut versuchen.';
        $messageClass = 'alert alert--error';
    } else {
        $backup = two_factor_generate_backup_codes();
        $now = new UTCDateTime();
        if (two_factor_update_user($userId, [
            'two_factor_enabled' => true,
            'two_factor_totp_secret' => $twoFactorSetupSecret,
            'two_factor_backup_codes' => $backup['hashed'],
            'two_factor_confirmed_at' => $now,
        ])) {
            unset($_SESSION['two_factor_setup_secret']);
            $twoFactorSetupSecret = '';
            $_SESSION['two_factor_display_backup_codes'] = $backup['plain'];
            $displayBackupCodes = $backup['plain'];
            $twoFactorEnabled = true;
            $message = '✅ Zwei-Faktor-Authentifizierung ist aktiv. Speichere die Backup-Codes sicher.';
            $messageClass = 'alert alert--success';
        } else {
            $message = '❌ Konnte 2FA nicht speichern.';
            $messageClass = 'alert alert--error';
        }
    }
}

if (isset($_POST['two_factor_disable']) && $twoFactorEnabled && $accountUser !== null) {
    $code = trim((string) ($_POST['totp_code'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $hash = is_array($accountUser) ? (string) ($accountUser['password'] ?? '') : (string) ($accountUser->password ?? '');
    $secret = is_array($accountUser) ? (string) ($accountUser['two_factor_totp_secret'] ?? '') : (string) ($accountUser->two_factor_totp_secret ?? '');
    if ($password === '' || ! password_verify($password, $hash)) {
        $message = '❌ Passwort falsch.';
        $messageClass = 'alert alert--error';
    } elseif (! two_factor_verify_totp($secret, $code)) {
        $message = '❌ Authenticator-Code ungültig.';
        $messageClass = 'alert alert--error';
    } elseif (two_factor_update_user($userId, [
        'two_factor_enabled' => false,
        'two_factor_totp_secret' => null,
        'two_factor_backup_codes' => [],
        'two_factor_confirmed_at' => null,
    ])) {
        $twoFactorEnabled = false;
        unset($_SESSION['two_factor_setup_secret'], $_SESSION['two_factor_display_backup_codes']);
        $displayBackupCodes = null;
        $message = '✅ Zwei-Faktor-Authentifizierung deaktiviert.';
        $messageClass = 'alert alert--success';
    }
}

if (isset($_POST['two_factor_regenerate_backup']) && $twoFactorEnabled && $accountUser !== null) {
    $code = trim((string) ($_POST['totp_code'] ?? ''));
    $secret = is_array($accountUser) ? (string) ($accountUser['two_factor_totp_secret'] ?? '') : (string) ($accountUser->two_factor_totp_secret ?? '');
    if (! two_factor_verify_totp($secret, $code)) {
        $message = '❌ Authenticator-Code ungültig.';
        $messageClass = 'alert alert--error';
    } else {
        $backup = two_factor_generate_backup_codes();
        if (two_factor_update_user($userId, ['two_factor_backup_codes' => $backup['hashed']])) {
            $_SESSION['two_factor_display_backup_codes'] = $backup['plain'];
            $displayBackupCodes = $backup['plain'];
            $message = '✅ Neue Backup-Codes erzeugt — alte Codes sind ungültig.';
            $messageClass = 'alert alert--success';
        }
    }
}

if (isset($_POST['two_factor_dismiss_backup'])) {
    unset($_SESSION['two_factor_display_backup_codes']);
    $displayBackupCodes = null;
}

$accountEmail = '';
if (is_array($accountUser)) {
    $accountEmail = (string) ($accountUser['email'] ?? $_SESSION['email'] ?? '');
} elseif (is_object($accountUser)) {
    $accountEmail = (string) ($accountUser->email ?? $_SESSION['email'] ?? '');
} else {
    $accountEmail = (string) ($_SESSION['email'] ?? '');
}

// Hinweise aus Session (z. B. nach Redirects)
if (! empty($_SESSION['register_validation_errors'])) {
    unset($_SESSION['register_validation_errors']);
}

// --- LOGIK: CODE GENERIEREN (Nur Admin) ---
if (isset($_POST['generate_code']) && can('generate_codes')) {
    $targetRole = $_POST['target_role'];

    // Ensure uniqueness with both DB constraint and application retry.
    // (Index exists in dbScripts/00_db_init_accounts.php, but may be missing on legacy DBs.)
    try {
        $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    } catch (Exception $e) {
        // ignore - generation below still handles duplicate keys
    }

    $maxAttempts = 10;
    $newCode = null;
    for ($i = 0; $i < $maxAttempts; $i++) {
        // 16 hex chars (2^64 possibilities) vs old 8 chars (2^32).
        $candidate = strtoupper(bin2hex(random_bytes(8)));
        try {
            $db->registration_codes->insertOne([
                'code' => $candidate,
                'role' => $targetRole,
                'is_used' => false,
                'created_at' => new UTCDateTime()
            ]);
            $newCode = $candidate;
            break;
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            // Duplicate key error (unique index collision) => retry with a new random code.
            $writeResult = $e->getWriteResult();
            $writeErrors = $writeResult ? $writeResult->getWriteErrors() : [];
            $isDuplicate = false;
            foreach ($writeErrors as $we) {
                if (method_exists($we, 'getCode') && (int)$we->getCode() === 11000) {
                    $isDuplicate = true;
                    break;
                }
            }
            if ($isDuplicate) {
                continue;
            }
            throw $e;
        }
    }

    if ($newCode === null) {
        $message = "❌ Konnte keinen eindeutigen Code generieren (bitte erneut versuchen).";
        $messageClass = 'alert alert--error';
    } else {
        $message = "✅ Neuer Code generiert: <b>$newCode</b>";
        $messageClass = 'alert alert--success';
    }
}

// (Login/Register-Fehler werden auf den jeweiligen Seiten angezeigt)

$roleOptions = [];
try {
    $roleOptions = iterator_to_array($db->roles_config->find([], ['sort' => ['role' => 1]]));
} catch (Exception $e) {
    $roleOptions = [];
}
if (count($roleOptions) === 0) {
    $roleOptions = [
        ['role' => 'content_manager', 'label' => 'Content Manager'],
        ['role' => 'community_member', 'label' => 'Community Member'],
        ['role' => 'admin', 'label' => 'Admin']
    ];
}
if (!empty($roleOptions)) {
    $roleOptions = array_values(array_filter($roleOptions, function ($roleOption) {
        return isset($roleOption['role']) && $roleOption['role'] !== 'viewer';
    }));
}
?>

<div class="container">
    <h1>Account System</h1>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="account-header">
        <p>Eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['email']); ?></strong> (<?php echo htmlspecialchars($_SESSION['email']); ?>) (Rolle: <?php echo $_SESSION['role']; ?>)</p>
        <form method="POST" action="index.php?page=account" class="account-logout-form" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="link-button">Abmelden</button>
        </form>
    </div>

    <hr class="account-divider">

    <section class="account-2fa">
        <h2>Zwei-Faktor-Authentifizierung (optional)</h2>
        <p class="field-hint">TOTP per Authenticator-App (z.&nbsp;B. Aegis, Google Authenticator). Beim Login bleibt das Passwort; danach ggf. der 6-stellige Code.</p>

        <?php if ($displayBackupCodes !== null && count($displayBackupCodes) > 0): ?>
            <div class="alert alert--success">
                <p><strong>Backup-Codes</strong> (jeder Code nur einmal):</p>
                <ul>
                    <?php foreach ($displayBackupCodes as $bc): ?>
                        <li><code><?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" action="index.php?page=account" style="margin-top:0.75rem;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="two_factor_dismiss_backup" value="1">
                    <button type="submit">Verstanden — Codes notiert</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($twoFactorEnabled): ?>
            <p>Status: <strong>aktiv</strong></p>
            <form method="POST" action="index.php?page=account" class="account-2fa-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_regenerate_backup" value="1">
                <div class="field">
                    <label for="regen_totp">Authenticator-Code (für neue Backup-Codes)</label>
                    <input type="text" id="regen_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <button type="submit">Neue Backup-Codes erzeugen</button>
            </form>
            <form method="POST" action="index.php?page=account" class="account-2fa-form" style="margin-top:1.5rem;" onsubmit="return confirm('2FA wirklich deaktivieren?');">
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
            <p class="field-hint">Secret (manuell): <code><?php echo htmlspecialchars($twoFactorSetupSecret, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p class="field-hint">otpauth-URI: <code style="word-break:break-all;"><?php echo htmlspecialchars(two_factor_provisioning_uri($accountEmail, $twoFactorSetupSecret), ENT_QUOTES, 'UTF-8'); ?></code></p>
            <form method="POST" action="index.php?page=account">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_confirm" value="1">
                <div class="field">
                    <label for="setup_totp">6-stelliger Code zur Bestätigung</label>
                    <input type="text" id="setup_totp" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </div>
                <button type="submit">2FA aktivieren</button>
            </form>
            <form method="POST" action="index.php?page=account" style="margin-top:0.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_cancel_setup" value="1">
                <button type="submit" class="link-button">Abbrechen</button>
            </form>
        <?php else: ?>
            <form method="POST" action="index.php?page=account">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="two_factor_begin" value="1">
                <button type="submit">2FA einrichten</button>
            </form>
        <?php endif; ?>
    </section>

    <hr class="account-divider">

    <?php if (can('generate_codes')): ?>
        <section class="admin-panel">
            <h3>Admin</h3>
            <p class="field-hint">
                Einladungscodes werden jetzt im <a href="pages/admin/invite_codes.php">Admin-Bereich</a> verwaltet.
            </p>
        </section>
    <?php endif; ?>
</div>

<script>
(() => {
    // (bewusst leer) – vormals Autocomplete-Toggle zwischen Login/Register auf derselben Seite
})();
</script>
