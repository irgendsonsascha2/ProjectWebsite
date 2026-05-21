<?php

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/svg_icons.php';
require_once __DIR__ . '/../../includes/user_moderation.php';

use MongoDB\BSON\UTCDateTime;

admin_require_access(['admin']);

$message = '';
$messageClass = 'alert';
$adminUsername = (string) ($_SESSION['username'] ?? $_SESSION['email'] ?? 'admin');
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$reasonOptions = user_moderation_reason_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        header('Location: users.php?err=csrf');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $userId = trim((string) ($_POST['user_id'] ?? ''));

    try {
        if ($action === 'clear_moderation') {
            user_moderation_clear($db, $userId, $adminUsername);
            $message = '✅ Sperre aufgehoben.';
            $messageClass = 'alert';
        } elseif ($action === 'apply_moderation') {
            $status = (string) ($_POST['moderation_status'] ?? '');
            if ($status === 'active') {
                user_moderation_clear($db, $userId, $adminUsername);
                $message = '✅ Nutzer ist wieder aktiv.';
            } else {
                user_moderation_apply($db, $userId, [
                    'status' => $status,
                    'duration_preset' => (string) ($_POST['duration_preset'] ?? ''),
                    'until_custom' => (string) ($_POST['until_custom'] ?? ''),
                    'reason_key' => (string) ($_POST['reason_key'] ?? ''),
                    'reason_custom' => (string) ($_POST['reason_custom'] ?? ''),
                    'show_reason' => ! empty($_POST['show_reason']),
                ], $adminUsername);
                $message = $status === 'banned'
                    ? '✅ Ban gesetzt.'
                    : '✅ Timeout gesetzt.';
            }
            $messageClass = 'alert';
        } else {
            $message = '❌ Unbekannte Aktion.';
            $messageClass = 'alert alert--error';
        }
    } catch (Throwable $e) {
        $message = '❌ '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $messageClass = 'alert alert--error';
    }

    $redirectQ = $searchQuery !== '' ? '&q='.rawurlencode($searchQuery) : '';
    if ($messageClass === 'alert') {
        header('Location: users.php?ok=1'.$redirectQ);
        exit;
    }
}

if (isset($_GET['err']) && (string) $_GET['err'] === 'csrf') {
    $message = '❌ Formular abgelaufen — bitte erneut versuchen.';
    $messageClass = 'alert alert--error';
}
if (isset($_GET['ok'])) {
    $message = '✅ Änderung gespeichert.';
    $messageClass = 'alert';
}

$users = [];
try {
    $users = user_moderation_search($db, $searchQuery, 80);
} catch (Throwable $e) {
    $message = '❌ Nutzerliste konnte nicht geladen werden: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $messageClass = 'alert alert--error';
}

?>
<?php admin_render_page('Nutzer', 'users', function () use (
    $message,
    $messageClass,
    $searchQuery,
    $users,
    $reasonOptions
) { ?>
    <div class="page-header">
        <h1>Nutzer</h1>
        <p class="muted">Registrierte Konten suchen, Timeout oder Ban setzen, freigeben. Admin-Konten sind geschützt.</p>
    </div>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="GET" action="users.php" class="admin-card" style="margin-bottom: 1rem;">
        <label for="q">Suche (E-Mail oder Username)</label>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <input type="search" id="q" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" style="flex: 1; min-width: 12rem;">
            <button type="submit">Suchen</button>
            <?php if ($searchQuery !== ''): ?>
                <a href="users.php">Alle anzeigen</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (count($users) === 0): ?>
        <p class="muted">Keine Nutzer gefunden.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Status</th>
                        <th>Letzte Änderung</th>
                        <th>Moderation</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uid = (string) ($u['_id'] ?? '');
                    $username = (string) ($u['username'] ?? '');
                    $email = (string) ($u['email'] ?? '');
                    $role = (string) ($u['role'] ?? '');
                    $protected = user_moderation_role_is_protected($u);
                    $mod = user_moderation_from_user($u);
                    $currentStatus = $mod !== null ? (string) ($mod['status'] ?? 'active') : 'active';
                    if ($currentStatus === 'suspended' && $mod !== null && user_moderation_is_expired($mod)) {
                        $currentStatus = 'active';
                    }
                    $reasonKey = $mod !== null ? (string) ($mod['reason_key'] ?? 'terms') : 'terms';
                    $reasonCustom = $mod !== null ? (string) ($mod['reason_custom'] ?? '') : '';
                    $showReason = $mod !== null && ! empty($mod['show_reason']);
                    $updatedAt = '';
                    if ($mod !== null && isset($mod['updated_at']) && $mod['updated_at'] instanceof UTCDateTime) {
                        $updatedAt = $mod['updated_at']->toDateTime()->format('d.m.Y H:i');
                    }
                    $untilValue = '';
                    if ($mod !== null && isset($mod['until']) && $mod['until'] instanceof UTCDateTime) {
                        $untilValue = $mod['until']->toDateTime()->format('Y-m-d\TH:i');
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(user_moderation_status_label($u), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($updatedAt !== '' ? $updatedAt : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php if ($protected): ?>
                                <span class="muted">Geschützt (Admin)</span>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="icon-button"
                                    data-dialog-open="moderation-user-<?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Status bearbeiten"
                                    title="Status bearbeiten"
                                ><?php echo svg_icon_pencil(18); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($users as $u): ?>
            <?php
            $uid = (string) ($u['_id'] ?? '');
            if ($uid === '' || user_moderation_role_is_protected($u)) {
                continue;
            }
            $username = (string) ($u['username'] ?? '');
            $email = (string) ($u['email'] ?? '');
            $mod = user_moderation_from_user($u);
            $currentStatus = $mod !== null ? (string) ($mod['status'] ?? 'active') : 'active';
            if ($currentStatus === 'suspended' && $mod !== null && user_moderation_is_expired($mod)) {
                $currentStatus = 'active';
            }
            $reasonKey = $mod !== null ? (string) ($mod['reason_key'] ?? 'terms') : 'terms';
            $reasonCustom = $mod !== null ? (string) ($mod['reason_custom'] ?? '') : '';
            $showReason = $mod !== null && ! empty($mod['show_reason']);
            $untilValue = '';
            if ($mod !== null && isset($mod['until']) && $mod['until'] instanceof UTCDateTime) {
                $untilValue = $mod['until']->toDateTime()->format('Y-m-d\TH:i');
            }
            $dialogId = 'moderation-user-'.$uid;
            ?>
            <dialog id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Status: <?php echo htmlspecialchars($username !== '' ? $username : $email, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <button type="button" class="close-button" data-dialog-close>Schließen</button>
                    </div>
                    <form method="POST" action="users.php" class="moderation-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="apply_moderation">

                        <fieldset class="admin-fieldset moderation-status-fieldset">
                            <legend>Status</legend>
                            <div class="moderation-status-options">
                                <label class="moderation-status-option">
                                    <input type="radio" name="moderation_status" value="active" class="js-moderation-status"<?php echo $currentStatus === 'active' ? ' checked' : ''; ?>>
                                    <span>Aktiv</span>
                                </label>
                                <label class="moderation-status-option">
                                    <input type="radio" name="moderation_status" value="suspended" class="js-moderation-status"<?php echo $currentStatus === 'suspended' ? ' checked' : ''; ?>>
                                    <span>Timeout</span>
                                </label>
                                <label class="moderation-status-option">
                                    <input type="radio" name="moderation_status" value="banned" class="js-moderation-status"<?php echo $currentStatus === 'banned' ? ' checked' : ''; ?>>
                                    <span>Ban</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="js-timeout-fields field">
                            <label>Dauer (Timeout)</label>
                            <select name="duration_preset">
                                <option value="1h">1 Stunde</option>
                                <option value="24h">24 Stunden</option>
                                <option value="7d">7 Tage</option>
                                <option value="30d">30 Tage</option>
                                <option value="custom">Bis Datum/Uhrzeit</option>
                            </select>
                            <label class="field" style="margin-top: 0.5rem;">Optional: bis (lokal)</label>
                            <input type="datetime-local" name="until_custom" value="<?php echo htmlspecialchars($untilValue, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="field">
                            <label>Grund</label>
                            <select name="reason_key" class="js-reason-key">
                                <?php foreach ($reasonOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $key === $reasonKey ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <label class="js-custom-reason field"<?php echo $reasonKey === 'custom' ? '' : ' style="display:none;"'; ?>>Eigener Text</label>
                        <textarea name="reason_custom" rows="2" class="js-custom-reason-input field"<?php echo $reasonKey === 'custom' ? '' : ' style="display:none;"'; ?>><?php echo htmlspecialchars($reasonCustom, ENT_QUOTES, 'UTF-8'); ?></textarea>

                        <label class="field" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="show_reason" value="1"<?php echo $showReason ? ' checked' : ''; ?>>
                            <span>Grund dem Nutzer anzeigen</span>
                        </label>

                        <div class="actions" style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                            <button type="submit">Speichern</button>
                            <?php if ($currentStatus !== 'active'): ?>
                            <button type="submit" class="button-secondary" form="clear-<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('Sperre wirklich aufheben?');">Freigeben</button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($currentStatus !== 'active'): ?>
                    <form method="POST" action="users.php" id="clear-<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" hidden>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="clear_moderation">
                    </form>
                    <?php endif; ?>
                </div>
            </dialog>
        <?php endforeach; ?>
    <?php endif; ?>

<?php }, ['admin'], ['admin-users.js']); ?>
