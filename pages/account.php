<?php

use MongoDB\BSON\UTCDateTime;

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

$message = '';
$messageClass = 'alert';

$userId = (string) ($_SESSION['user_id'] ?? '');
$accountUser = $userId !== '' ? two_factor_find_user_by_id($userId) : null;
$twoFactorEnabled = two_factor_user_enabled($accountUser);
$twoFactorPendingBackup = is_array($_SESSION['two_factor_display_backup_codes'] ?? null)
    && count($_SESSION['two_factor_display_backup_codes']) > 0;

// Hinweise aus Session (z. B. nach Redirects)
if (! empty($_SESSION['register_validation_errors'])) {
    unset($_SESSION['register_validation_errors']);
}

// --- LOGIK: CODE GENERIEREN (Nur Admin) ---
if (isset($_POST['generate_code']) && can('generate_codes')) {
    $targetRole = $_POST['target_role'];
    [, $codesDb] = get_admin_mongo_connection();

    // Ensure uniqueness with both DB constraint and application retry.
    // (Index exists in dbScripts/00_db_init_accounts.php, but may be missing on legacy DBs.)
    try {
        $codesDb->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    } catch (Exception $e) {
        // ignore - generation below still handles duplicate keys
    }

    $maxAttempts = 10;
    $newCode = null;
    for ($i = 0; $i < $maxAttempts; $i++) {
        // 16 hex chars (2^64 possibilities) vs old 8 chars (2^32).
        $candidate = strtoupper(bin2hex(random_bytes(8)));
        try {
            $codesDb->registration_codes->insertOne([
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

<script>
(() => {
    // (bewusst leer) – vormals Autocomplete-Toggle zwischen Login/Register auf derselben Seite
})();
</script>
