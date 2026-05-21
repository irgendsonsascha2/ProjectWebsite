<?php

require_once __DIR__.'/../includes/laravel_app_url.php';
require_once __DIR__.'/../includes/two_factor.php';

// 2FA: eigene Seite index.php?page=two_factor

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

$userId = (string) ($_SESSION['user_id'] ?? '');
$accountUser = $userId !== '' ? two_factor_find_user_by_id($userId) : null;
$twoFactorEnabled = two_factor_user_enabled($accountUser);
$twoFactorPendingBackup = is_array($_SESSION['two_factor_display_backup_codes'] ?? null)
    && count($_SESSION['two_factor_display_backup_codes']) > 0;

// Hinweise aus Session (z. B. nach Redirects)
if (! empty($_SESSION['register_validation_errors'])) {
    unset($_SESSION['register_validation_errors']);
}

?>

<div class="container">
    <h1>Account System</h1>

    <div class="account-header">
        <p>Eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['email']); ?></strong> (<?php echo htmlspecialchars($_SESSION['email']); ?>) (Rolle: <?php echo htmlspecialchars((string) ($_SESSION['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</p>
        <form method="POST" action="index.php?page=account" class="account-logout-form" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="link-button">Abmelden</button>
        </form>
    </div>

    <hr class="account-divider">

    <section class="account-2fa-summary">
        <h2>Sicherheit</h2>
        <p>
            Zwei-Faktor-Authentifizierung (2FA):
            <strong><?php echo $twoFactorEnabled ? 'aktiv' : 'nicht aktiv'; ?></strong>
        </p>
        <?php if ($twoFactorPendingBackup): ?>
            <p class="alert alert--success">
                Backup-Codes warten auf Bestätigung —
                <a href="index.php?page=two_factor">jetzt notieren</a>.
            </p>
        <?php endif; ?>
        <?php
            $twoFactorActionLabel = $twoFactorEnabled ? '2FA verwalten' : '2FA einrichten';
            $twoFactorHelpTitle = 'Was ist Zwei-Faktor-Authentifizierung?';
        ?>
        <div id="two-factor-help-content" hidden>
            <?php require __DIR__.'/../includes/partials/two_factor_help_content.php'; ?>
        </div>
        <div class="account-2fa-actions">
            <a href="index.php?page=two_factor" class="btn-primary"><?php echo htmlspecialchars($twoFactorActionLabel, ENT_QUOTES, 'UTF-8'); ?></a>
            <div
                data-react-help-button
                data-help-title="<?php echo htmlspecialchars($twoFactorHelpTitle, ENT_QUOTES, 'UTF-8'); ?>"
                data-help-source="two-factor-help-content"
            ></div>
        </div>
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
