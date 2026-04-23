<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__.'/../includes/laravel_app_url.php';

// Registrierung läuft über bridge_register.php + Laravel (RegisterInvitedUser).

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header("Location: index.php?page=login");
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

// Hinweise aus Session (z. B. nach Redirects)
if (! empty($_SESSION['register_validation_errors'])) {
    unset($_SESSION['register_validation_errors']);
}

// --- LOGIK: CODE GENERIEREN (Nur Admin) ---
if (isset($_POST['generate_code']) && can('generate_codes')) {
    $newCode = strtoupper(bin2hex(random_bytes(4)));
    $targetRole = $_POST['target_role'];

    $db->registration_codes->insertOne([
        'code' => $newCode,
        'role' => $targetRole,
        'is_used' => false,
        'created_at' => new UTCDateTime()
    ]);
    $message = "✅ Neuer Code generiert: <b>$newCode</b>";
    $messageClass = 'alert alert--success';
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
        <a href="index.php?page=account&logout=1">Abmelden</a>
    </div>

    <hr class="account-divider">

    <?php if (can('generate_codes')): ?>
        <section class="admin-panel">
            <h3>Einladungscodes & Links</h3>
            <form method="POST" class="code-form">
                <select name="target_role" class="code-select">
                    <?php foreach ($roleOptions as $roleOption): ?>
                        <?php
                            $roleKey = $roleOption['role'] ?? '';
                            $roleLabel = $roleOption['label'] ?? $roleKey;
                            if (!$roleKey) {
                                continue;
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($roleKey); ?>"><?php echo htmlspecialchars($roleLabel); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="generate_code" class="code-button">Code generieren</button>
            </form>

            <?php
            $inviteCodesData = [];
            $activeCodes = $db->registration_codes->find(['is_used' => false]);
            foreach ($activeCodes as $c) {
                $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $basePath = explode('?', $_SERVER['REQUEST_URI'])[0];
                $link = $scheme . "://" . $_SERVER['HTTP_HOST'] . $basePath . "?page=register&reg_token=" . $c['code'] . "#register-section";
                $inviteCodesData[] = [
                    'role' => (string) ($c['role'] ?? ''),
                    'code' => (string) ($c['code'] ?? ''),
                    'link' => (string) $link,
                ];
            }
            ?>

            <script type="application/json" id="react-invite-codes-data"><?php echo json_encode($inviteCodesData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Rolle</th>
                        <th>Code</th>
                        <th>Direkt-Link</th>
                    </tr>
                    <?php foreach ($inviteCodesData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div
                                    data-react-copy-field
                                    data-copy-kind="code"
                                    data-copy-value="<?php echo htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-copy-label="Code kopieren"
                                ></div>
                            </td>
                            <td>
                                <div
                                    data-react-copy-field
                                    data-copy-kind="link"
                                    data-copy-value="<?php echo htmlspecialchars($row['link'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-copy-label="Link kopieren"
                                ></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
(() => {
    // (bewusst leer) – vormals Autocomplete-Toggle zwischen Login/Register auf derselben Seite
})();
</script>
