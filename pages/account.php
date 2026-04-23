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

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Rolle</th>
                        <th>Code</th>
                        <th>Direkt-Link</th>
                    </tr>
                    <?php
                    $activeCodes = $db->registration_codes->find(['is_used' => false]);
                    foreach ($activeCodes as $c):
                        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $basePath = explode('?', $_SERVER['REQUEST_URI'])[0];
                        $link = $scheme . "://" . $_SERVER['HTTP_HOST'] . $basePath . "?page=register&reg_token=" . $c['code'] . "#register-section";
                    ?>
                        <tr>
                            <td><?php echo $c['role']; ?></td>
                            <td>
                                <div class="copy-row">
                                    <code id="code-<?php echo htmlspecialchars((string) $c['_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $c['code'], ENT_QUOTES, 'UTF-8'); ?></code>
                                    <button type="button" class="copy-btn" data-copy-text="<?php echo htmlspecialchars((string) $c['code'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Code kopieren" title="Code kopieren">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M9 9h10v10H9V9zm-4 6H4V4h11v1H5v10z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="copy-row">
                                    <input type="text" value="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" readonly onclick="this.select();" class="code-link-input">
                                    <button type="button" class="copy-btn" data-copy-text="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Link kopieren" title="Link kopieren">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M9 9h10v10H9V9zm-4 6H4V4h11v1H5v10z"></path>
                                        </svg>
                                    </button>
                                </div>
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

<script>
(() => {
    const buttons = document.querySelectorAll('.copy-btn[data-copy-text]');
    if (!buttons.length) return;

    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(ta);
        }
    }

    buttons.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const text = btn.getAttribute('data-copy-text') || '';
            if (!text) return;
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    fallbackCopy(text);
                }
                btn.classList.add('copy-btn--done');
                window.setTimeout(() => btn.classList.remove('copy-btn--done'), 800);
            } catch (e) {
                fallbackCopy(text);
            }
        });
    });
})();
</script>
