<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/registration_codes.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';

// Admin layout enforces admin role. Additionally require permission for safety.
if (!can('generate_codes')) {
    die("<h1>Zugriff verweigert</h1><p>Sie haben nicht die nötigen Rechte, um Einladungscodes zu verwalten.</p>");
}

$message = '';
$messageClass = 'alert';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

if (isset($_GET['err']) && (string) $_GET['err'] === 'csrf') {
    $message = '❌ Formular abgelaufen — bitte erneut versuchen.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['err']) && (string) $_GET['err'] === 'reauth') {
    $message = '❌ Admin-Bestätigung fehlgeschlagen oder abgelaufen — Passwort'.($adminReauthNeeds2fa ? ' und 2FA-Code' : '').' erneut eingeben.';
    $messageClass = 'alert alert--error';
}

// --- LOGIK: CODE GENERIEREN ---
if (isset($_POST['generate_code'])) {
    $reauth = admin_reauth_require_fresh_or_post();
    if (! $reauth['ok']) {
        header('Location: invite_codes.php?err=reauth');
        exit;
    }
    $adminReauthFresh = admin_reauth_is_fresh();
    $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

    $targetRole = (string) ($_POST['target_role'] ?? '');
    $newCode = registration_code_create($db, $targetRole);

    if ($newCode === null) {
        $message = '❌ Konnte keinen eindeutigen Code generieren (bitte erneut versuchen).';
        $messageClass = 'alert error';
    } else {
        $message = '✅ Neuer Code generiert: <code>'.htmlspecialchars($newCode, ENT_QUOTES, 'UTF-8').'</code>';
        $messageClass = 'alert success';
    }
}

// Rollen (ohne viewer) für Dropdown
$roleOptions = [];
try {
    $roleOptions = iterator_to_array($db->roles_config->find([], ['sort' => ['role' => 1]]));
} catch (Exception $e) {
    $roleOptions = [];
}
if (count($roleOptions) > 0) {
    $roleOptions = array_values(array_filter($roleOptions, function ($roleOption) {
        return isset($roleOption['role']) && $roleOption['role'] !== 'viewer';
    }));
}

$inviteCodesData = [];
try {
    $activeCodes = $db->registration_codes->find(
        ['is_used' => false],
        ['sort' => ['created_at' => -1]]
    );
    foreach ($activeCodes as $c) {
        $link = registration_code_register_link((string) ($c['code'] ?? ''));
        $inviteCodesData[] = [
            'role' => (string)($c['role'] ?? ''),
            'code' => (string)($c['code'] ?? ''),
            'link' => (string)$link,
        ];
    }
} catch (Exception $e) {
    // ignore
}

admin_render_page('Einladungscodes', 'invite_codes', function () use ($message, $messageClass, $roleOptions, $inviteCodesData, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) { ?>
    <div class="page-header">
        <h1>Einladungscodes</h1>
    </div>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; // enthält nur escapte <code>-Tags bei Erfolg ?></div>
    <?php endif; ?>

    <div class="admin-card admin-card--spaced">
        <h2>Neuen Code generieren</h2>
        <?php if ($adminReauthFresh): ?>
            <form method="POST" class="code-form" action="invite_codes.php">
                <?php echo csrf_field(); ?>
                <select id="target_role" name="target_role" aria-label="Rolle">
                    <?php foreach ($roleOptions as $roleOption): ?>
                        <?php
                            $roleKey = $roleOption['role'] ?? '';
                            $roleLabel = $roleOption['label'] ?? $roleKey;
                            if (!$roleKey) {
                                continue;
                            }
                        ?>
                        <option value="<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="generate_code" value="1">Code generieren</button>
            </form>
        <?php else: ?>
            <p class="muted">Zum Erzeugen eines Codes ist eine Admin-Bestätigung nötig.</p>
            <button type="button" class="button-primary" data-dialog-open="generate-code-dialog">Code generieren…</button>
        <?php endif; ?>
    </div>

    <dialog id="generate-code-dialog">
        <div class="dialog-card">
            <div class="dialog-header">
                <h2>Code generieren</h2>
                <button type="button" class="close-button" data-dialog-close>Schließen</button>
            </div>
            <form method="POST" class="code-form" action="invite_codes.php" data-dialog-close-on-submit>
                <?php echo csrf_field(); ?>
                <p>
                    <label for="target_role_dialog">Rolle</label><br>
                    <select id="target_role_dialog" name="target_role" aria-label="Rolle">
                        <?php foreach ($roleOptions as $roleOption): ?>
                            <?php
                                $roleKey = $roleOption['role'] ?? '';
                                $roleLabel = $roleOption['label'] ?? $roleKey;
                                if (!$roleKey) {
                                    continue;
                                }
                            ?>
                            <option value="<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                <button type="submit" name="generate_code" value="1">Code generieren</button>
            </form>
        </div>
    </dialog>

    <div class="admin-card admin-card--spaced">
        <h2>Aktive Codes</h2>

        <script type="application/json" id="react-invite-codes-data"><?php echo json_encode($inviteCodesData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

        <div class="table-wrap" id="invite-codes-table-wrap">
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
    </div>
<?php });
