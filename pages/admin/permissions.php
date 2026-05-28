<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/svg_icons.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';

admin_require_manage_users();

$notice = '';
$error = '';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

$defaultPermissions = [
    ['key' => 'view_projects', 'label' => 'Projekte ansehen', 'description' => 'Projekte im Frontend ansehen'],
    ['key' => 'view_comments', 'label' => 'Kommentare ansehen', 'description' => 'Kommentare lesen'],
    ['key' => 'view_likes', 'label' => 'Likes/Dislikes ansehen', 'description' => 'Like/Dislike-Zahlen anzeigen'],
    ['key' => 'create_project', 'label' => 'Projekt erstellen', 'description' => 'Neue Projekte anlegen'],
    ['key' => 'edit_all', 'label' => 'Alle Projekte bearbeiten', 'description' => 'Beliebige Projekte bearbeiten'],
    ['key' => 'edit_own', 'label' => 'Eigene Projekte bearbeiten', 'description' => 'Nur eigene Projekte bearbeiten'],
    ['key' => 'delete_all', 'label' => 'Projekte löschen', 'description' => 'Projekte löschen (inkl. Kommentare/Likes)'],
    ['key' => 'delete_comments', 'label' => 'Kommentare löschen', 'description' => 'Kommentare anderer Nutzer löschen (Rollenzuordnung)'],
    ['key' => 'comment_limit', 'label' => 'Kommentar-Limit', 'description' => 'Kommentar-Anzahl pro Rolle begrenzen'],
    ['key' => 'manage_users', 'label' => 'Benutzer verwalten', 'description' => 'Admin-Funktionen für Benutzer/Einladungen'],
    ['key' => 'generate_codes', 'label' => 'Einladungscodes erzeugen', 'description' => 'Registrierungs-Codes erstellen'],
    ['key' => 'comment', 'label' => 'Kommentieren', 'description' => 'Kommentare erstellen/bearbeiten'],
    ['key' => 'like_dislike', 'label' => 'Likes/Dislikes', 'description' => 'Likes und Dislikes vergeben']
];

function normalize_perm_key($key) {
    $key = strtolower(trim((string)$key));
    return $key;
}

try {
    if ($db->permissions_config->countDocuments() === 0) {
        $db->permissions_config->insertMany($defaultPermissions);
        $db->permissions_config->createIndex(['key' => 1], ['unique' => true]);
    }
} catch (Exception $e) {
    $error = 'Berechtigungen konnten nicht geladen werden.';
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'create_permission') {
        $permKey = input_identifier_key(req_post_string('perm_key', ''), 2, 60) ?? '';
        $permLabel = input_admin_label(req_post_string('perm_label', '')) ?? '';
        $permDesc = input_admin_description(req_post_string('perm_desc', '')) ?? '';

        if ($permKey === '') {
            $error = 'Berechtigungs-Schlüssel ist ungültig (2-60 Zeichen, a-z, 0-9, _ -).';
        } elseif ($db->permissions_config->findOne(['key' => $permKey])) {
            $error = 'Diese Berechtigung existiert bereits.';
        } else {
            $db->permissions_config->insertOne([
                'key' => $permKey,
                'label' => $permLabel ?: $permKey,
                'description' => $permDesc
            ]);
            $notice = 'Berechtigung wurde erstellt.';
        }
    } elseif ($action === 'update_permission') {
        $reauth = admin_reauth_require_fresh_or_post();
        if (! $reauth['ok']) {
            $error = $reauth['error'];
        } else {
            $adminReauthFresh = admin_reauth_is_fresh();
            $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
            $permKey = input_identifier_key(req_post_string('perm_key', ''), 2, 60) ?? '';
            $permLabel = input_admin_label(req_post_string('perm_label', '')) ?? '';
            $permDesc = input_admin_description(req_post_string('perm_desc', '')) ?? '';

            if ($permKey === '') {
                $error = 'Berechtigung fehlt.';
            } else {
                $db->permissions_config->updateOne(
                    ['key' => $permKey],
                    ['$set' => ['label' => $permLabel ?: $permKey, 'description' => $permDesc]]
                );
                $notice = 'Berechtigung wurde aktualisiert.';
            }
        }
    } elseif ($action === 'delete_permission') {
        $reauth = admin_reauth_require_fresh_or_post();
        if (! $reauth['ok']) {
            $error = $reauth['error'];
        } else {
            $adminReauthFresh = admin_reauth_is_fresh();
            $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
            $permKey = input_identifier_key(req_post_string('perm_key', ''), 2, 60) ?? '';
            $roleUsage = $permKey !== '' ? $db->roles_config->countDocuments(['permissions' => $permKey]) : 0;
            if ($roleUsage > 0) {
                $error = 'Berechtigung ist noch Rollen zugeordnet.';
            } else {
                $db->permissions_config->deleteOne(['key' => $permKey]);
                $notice = 'Berechtigung wurde gelöscht.';
            }
        }
    }
}

$permissions = iterator_to_array($db->permissions_config->find([], ['sort' => ['key' => 1]]));
$usageCounts = [];
try {
    $usageCursor = $db->roles_config->aggregate([
        ['$unwind' => '$permissions'],
        ['$group' => ['_id' => '$permissions', 'count' => ['$sum' => 1]]]
    ]);
    foreach ($usageCursor as $entry) {
        if (isset($entry['_id'])) {
            $usageCounts[(string)$entry['_id']] = (int)$entry['count'];
        }
    }
} catch (Exception $e) {
    $usageCounts = [];
}
?>
<?php admin_render_page('Berechtigungen verwalten', 'permissions', function () use ($error, $notice, $permissions, $usageCounts, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) { ?>
    <div class="page-header">
        <h1>Berechtigungen verwalten</h1>
    </div>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <button type="button" class="dialog-button" data-dialog-open="create-permission-dialog">Neue Berechtigung</button>
    </div>

    <dialog id="create-permission-dialog">
        <div class="dialog-card">
            <div class="dialog-header">
                <h2>Neue Berechtigung erstellen</h2>
                <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
            </div>
            <form method="POST" class="permission-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_permission">
                <div class="field">
                    <label for="perm_key">Berechtigungs-Schlüssel</label>
                    <input type="text" id="perm_key" name="perm_key" placeholder="z.B. publish_posts" required>
                    <div class="hint">Nur Kleinbuchstaben, Zahlen, _ und -</div>
                </div>
                <div class="field">
                    <label for="perm_label">Anzeigename</label>
                    <input type="text" id="perm_label" name="perm_label" placeholder="z.B. Beiträge veröffentlichen">
                </div>
                <div class="field">
                    <label for="perm_desc">Beschreibung</label>
                    <textarea id="perm_desc" name="perm_desc" placeholder="Wofür ist diese Berechtigung?"></textarea>
                </div>
                <div class="actions">
                    <button type="submit">Berechtigung anlegen</button>
                </div>
            </form>
        </div>
    </dialog>

    <h2>Bestehende Berechtigungen</h2>
    <div class="grid">
        <?php foreach ($permissions as $permission): ?>
            <?php
                $permKey = $permission['key'] ?? '';
                if (!$permKey) {
                    continue;
                }
                $permLabel = $permission['label'] ?? $permKey;
                $permDesc = $permission['description'] ?? '';
                $usageCount = $usageCounts[$permKey] ?? 0;
            ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3><?php echo htmlspecialchars($permLabel); ?></h3>
                        <div class="hint">Schlüssel: <?php echo htmlspecialchars($permKey); ?> · Rollen: <?php echo (int)$usageCount; ?></div>
                    </div>
                </div>

                <div class="card-actions">
                    <button type="button" class="icon-button" data-dialog-open="edit-permission-<?php echo htmlspecialchars($permKey); ?>" aria-label="Berechtigung bearbeiten" title="Berechtigung bearbeiten"><?php echo svg_icon_pencil(18); ?></button>
                    <button type="button" class="icon-button" data-dialog-open="info-permission-<?php echo htmlspecialchars($permKey); ?>" aria-label="Berechtigung anzeigen" title="Berechtigung anzeigen">ℹ</button>
                    <button type="button" class="icon-button danger" data-dialog-open="delete-permission-<?php echo htmlspecialchars($permKey); ?>" aria-label="Berechtigung löschen" title="Berechtigung löschen"><?php echo svg_icon_trash(18); ?></button>
                </div>
            </div>

            <dialog id="delete-permission-<?php echo htmlspecialchars($permKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Berechtigung löschen</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <p class="muted">Berechtigung: <strong><?php echo htmlspecialchars($permLabel, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($permKey, ENT_QUOTES, 'UTF-8'); ?>)</p>
                    <form method="POST" data-dialog-close-on-submit data-confirm-submit="Berechtigung wirklich löschen?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_permission">
                        <input type="hidden" name="perm_key" value="<?php echo htmlspecialchars($permKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                        <button type="submit" class="danger">Endgültig löschen</button>
                    </form>
                </div>
            </dialog>

            <dialog id="info-permission-<?php echo htmlspecialchars($permKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Berechtigung</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <p><strong><?php echo htmlspecialchars($permLabel); ?></strong></p>
                    <p><?php echo htmlspecialchars($permDesc ?: 'Keine Beschreibung vorhanden.'); ?></p>
                    <p class="hint">Schlüssel: <?php echo htmlspecialchars($permKey); ?> · Rollen: <?php echo (int)$usageCount; ?></p>
                </div>
            </dialog>

            <dialog id="edit-permission-<?php echo htmlspecialchars($permKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Berechtigung bearbeiten</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <form method="POST" class="permission-form" data-dialog-close-on-submit>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_permission">
                        <input type="hidden" name="perm_key" value="<?php echo htmlspecialchars($permKey); ?>">
                        <div class="field">
                            <label>Anzeigename</label>
                            <input type="text" name="perm_label" value="<?php echo htmlspecialchars($permLabel); ?>">
                        </div>
                        <div class="field">
                            <label>Beschreibung</label>
                            <textarea name="perm_desc"><?php echo htmlspecialchars($permDesc); ?></textarea>
                        </div>
                        <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                        <div class="actions">
                            <button type="submit">Speichern</button>
                        </div>
                    </form>
                </div>
            </dialog>
        <?php endforeach; ?>
    </div>
<?php }); ?>
