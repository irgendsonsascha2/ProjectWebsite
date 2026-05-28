<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/svg_icons.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';
require_once __DIR__ . '/../../includes/permissions_defaults.php';

admin_require_manage_users();

$notice = '';
$error = '';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

try {
    permissions_ensure_seeded($db);
} catch (Exception $e) {
    $error = 'Berechtigungen konnten nicht geladen werden.';
}

$allowedPermKeys = permissions_config_allowed_keys($db);
$allowedRoleKeys = roles_config_allowed_keys($db);

$rolePostActions = ['create_role', 'update_role', 'delete_role'];
$action = req_post_action('action', $rolePostActions);

if ($action !== null) {
    if ($action === 'create_role') {
        $roleKey = input_identifier_key(req_post_string('role_key', ''), 2, 40) ?? '';
        $roleLabel = input_admin_label(req_post_string('role_label', '')) ?? '';
        $permissions = normalize_permission_keys($_POST['permissions'] ?? [], $allowedPermKeys);
        $commentDeleteRoles = normalize_role_keys_list($_POST['comment_delete_roles'] ?? [], $allowedRoleKeys);
        $commentLimit = input_clamped_int($_POST['comment_limit'] ?? 0, 0, 10000, 0);

        if ($roleKey === '') {
            $error = 'Rollen-Schlüssel ist ungültig (2-40 Zeichen, a-z, 0-9, _ -).';
        } elseif ($db->roles_config->findOne(['role' => $roleKey])) {
            $error = 'Diese Rolle existiert bereits.';
        } else {
            $db->roles_config->insertOne([
                'role' => $roleKey,
                'label' => $roleLabel ?: $roleKey,
                'permissions' => $permissions,
                'comment_delete_roles' => $commentDeleteRoles,
                'comment_limit' => $commentLimit
            ]);
            $notice = 'Rolle wurde erstellt.';
        }
    } elseif ($action === 'update_role') {
        $reauth = admin_reauth_require_fresh_or_post();
        if (! $reauth['ok']) {
            $error = $reauth['error'];
        } else {
            $adminReauthFresh = admin_reauth_is_fresh();
            $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
            $roleKey = input_identifier_key(req_post_string('role_key', ''), 2, 40) ?? '';
            $roleLabel = input_admin_label(req_post_string('role_label', '')) ?? '';
            $permissions = normalize_permission_keys($_POST['permissions'] ?? [], $allowedPermKeys);
            $commentDeleteRoles = normalize_role_keys_list($_POST['comment_delete_roles'] ?? [], $allowedRoleKeys);
            $commentLimit = input_clamped_int($_POST['comment_limit'] ?? 0, 0, 10000, 0);

            if ($roleKey === '') {
                $error = 'Rolle fehlt.';
            } else {
                $db->roles_config->updateOne(
                    ['role' => $roleKey],
                    ['$set' => ['label' => $roleLabel ?: $roleKey, 'permissions' => $permissions, 'comment_delete_roles' => $commentDeleteRoles, 'comment_limit' => $commentLimit]]
                );
                $notice = 'Rolle wurde aktualisiert.';
            }
        }
    } elseif ($action === 'delete_role') {
        $reauth = admin_reauth_require_fresh_or_post();
        if (! $reauth['ok']) {
            $error = $reauth['error'];
        } else {
            $adminReauthFresh = admin_reauth_is_fresh();
            $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
        $roleKey = input_identifier_key(req_post_string('role_key', ''), 2, 40) ?? '';
        if ($roleKey === 'admin') {
            $error = 'Die Admin-Rolle kann nicht gelöscht werden.';
        } else {
            $userCount = $db->users->countDocuments(['role' => $roleKey]);
            if ($userCount > 0) {
                $error = 'Rolle ist noch Benutzern zugeordnet.';
            } else {
                $db->roles_config->deleteOne(['role' => $roleKey]);
                $db->registration_codes->deleteMany(['role' => $roleKey]);
                $notice = 'Rolle wurde gelöscht.';
            }
        }
        }
    }
}

$permissions = iterator_to_array($db->permissions_config->find([], ['sort' => ['key' => 1]]));
$roles = iterator_to_array($db->roles_config->find([], ['sort' => ['role' => 1]]));
$permissionMap = [];
foreach ($permissions as $permission) {
    if (!isset($permission['key'])) {
        continue;
    }
    $key = (string)$permission['key'];
    $permissionMap[$key] = [
        'label' => $permission['label'] ?? $key,
        'description' => $permission['description'] ?? ''
    ];
}
$permissionGroups = [
    'Anzeigen' => ['view_projects', 'view_comments', 'view_likes'],
    'Projekte' => ['create_project', 'edit_own', 'edit_all', 'delete_all'],
    'Interaktionen' => ['like_dislike'],
    'Kommentare' => ['comment', 'delete_comments', 'comment_limit'],
    'Administration' => ['manage_users', 'generate_codes']
];
$groupedPermissions = [];
$usedPermissions = [];
foreach ($permissionGroups as $label => $keys) {
    $items = [];
    foreach ($keys as $key) {
        if (!isset($permissionMap[$key])) {
            continue;
        }
        $items[] = [
            'key' => $key,
            'label' => $permissionMap[$key]['label'] ?? $key,
            'description' => $permissionMap[$key]['description'] ?? ''
        ];
        $usedPermissions[$key] = true;
    }
    if (count($items) > 0) {
        $groupedPermissions[] = ['label' => $label, 'items' => $items];
    }
}
foreach ($permissionMap as $key => $meta) {
    if (isset($usedPermissions[$key])) {
        continue;
    }
    $groupedPermissions[] = [
        'label' => 'Weitere',
        'items' => [[
            'key' => $key,
            'label' => $meta['label'] ?? $key,
            'description' => $meta['description'] ?? ''
        ]]
    ];
    $usedPermissions[$key] = true;
}
$rolesMap = [];
foreach ($roles as $roleItem) {
    $roleKey = $roleItem['role'] ?? '';
    if (!$roleKey) {
        continue;
    }
    $rolesMap[$roleKey] = [
        'label' => $roleItem['label'] ?? $roleKey
    ];
}
$roleCounts = [];
try {
    $countsCursor = $db->users->aggregate([
        ['$group' => ['_id' => '$role', 'count' => ['$sum' => 1]]]
    ]);
    foreach ($countsCursor as $entry) {
        if (isset($entry['_id'])) {
            $roleCounts[(string)$entry['_id']] = (int)$entry['count'];
        }
    }
} catch (Exception $e) {
    $roleCounts = [];
}
?>

<?php admin_render_page('Rollen verwalten', 'roles', function () use ($error, $notice, $groupedPermissions, $roles, $permissionMap, $rolesMap, $roleCounts, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) { ?>
    <div class="page-header">
        <h1>Rollen verwalten</h1>
    </div>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <button type="button" class="dialog-button" data-dialog-open="create-role-dialog">Neue Rolle</button>
    </div>

    <dialog id="create-role-dialog">
        <div class="dialog-card">
            <div class="dialog-header">
                <h2>Neue Rolle erstellen</h2>
                <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
            </div>
            <form method="POST" class="role-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_role">
                <div class="field">
                    <label for="role_key">Rollen-Schlüssel</label>
                    <input type="text" id="role_key" name="role_key" placeholder="z.B. editor" required>
                    <div class="hint">Nur Kleinbuchstaben, Zahlen, _ und -</div>
                </div>
                <div class="field">
                    <label for="role_label">Anzeigename</label>
                    <input type="text" id="role_label" name="role_label" placeholder="z.B. Editor">
                </div>
                <div class="field">
                    <label>Berechtigungen</label>
                    <div class="permissions">
                        <?php foreach ($groupedPermissions as $group): ?>
                            <div class="permission-group">
                                <div class="permission-group-title"><?php echo htmlspecialchars($group['label']); ?></div>
                                <?php foreach ($group['items'] as $perm): ?>
                                    <label>
                                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm['key']); ?>">
                                        <span>
                                            <?php echo htmlspecialchars($perm['label']); ?>
                                            <?php if (!empty($perm['description'])): ?>
                                                <small><?php echo htmlspecialchars($perm['description']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <?php if ($perm['key'] === 'comment_limit'): ?>
                                        <div class="permission-subfield comment-limit-field" data-limit-field>
                                            <div class="hint">Nur wirksam mit Berechtigung "Kommentar-Limit". 0 = unbegrenzt.</div>
                                            <input type="number" name="comment_limit" min="0" step="1" value="0" disabled>
                                        </div>
                                    <?php elseif ($perm['key'] === 'delete_comments'): ?>
                                        <div class="permission-subfield">
                                            <div class="hint">Gilt nur mit Berechtigung "Kommentare löschen".</div>
                                            <div class="role-selector" data-role-selector>
                                                <button type="button" class="role-selector-toggle" aria-expanded="false">Rollen auswählen</button>
                                                <div class="role-selector-panel" hidden>
                                                    <div class="role-selector-search">
                                                        <input type="text" placeholder="Rollen suchen..." aria-label="Rollen suchen">
                                                    </div>
                                                    <label class="role-selector-all">
                                                        <input type="checkbox" data-role-select-all>
                                                        <span>Alle Rollen auswählen</span>
                                                    </label>
                                                    <div class="role-selector-list">
                                                        <?php foreach ($roles as $roleItem): ?>
                                                            <?php
                                                                $targetRoleKey = $roleItem['role'] ?? '';
                                                                $targetRoleLabel = $roleItem['label'] ?? $targetRoleKey;
                                                                if (!$targetRoleKey) {
                                                                    continue;
                                                                }
                                                            ?>
                                                            <label>
                                                                <input type="checkbox" name="comment_delete_roles[]" value="<?php echo htmlspecialchars($targetRoleKey); ?>">
                                                                <span><?php echo htmlspecialchars($targetRoleLabel); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Rolle anlegen</button>
                </div>
            </form>
        </div>
    </dialog>

    <h2>Bestehende Rollen</h2>
    <div class="grid">
        <?php foreach ($roles as $role): ?>
            <?php
                $roleKey = $role['role'] ?? '';
                if (!$roleKey) {
                    continue;
                }
                $roleLabel = $role['label'] ?? $roleKey;
                $rolePermissions = $role['permissions'] ?? [];
                $rolePermissions = normalize_permission_keys($rolePermissions, $allowedPermKeys);
                $roleDeleteRoles = $role['comment_delete_roles'] ?? [];
                $roleDeleteRoles = normalize_role_keys_list($roleDeleteRoles, $allowedRoleKeys);
                $roleCommentLimit = isset($role['comment_limit']) ? (int)$role['comment_limit'] : 0;
                $userCount = $roleCounts[$roleKey] ?? 0;
            ?>
            <div class="card">
                <div class="role-header">
                    <div>
                        <h3><?php echo htmlspecialchars($roleLabel); ?></h3>
                        <div class="hint">Schlüssel: <?php echo htmlspecialchars($roleKey); ?> · Nutzer: <?php echo (int)$userCount; ?></div>
                    </div>
                </div>

                <div class="card-actions">
                    <button type="button" class="icon-button" data-dialog-open="edit-role-<?php echo htmlspecialchars($roleKey); ?>" aria-label="Rolle bearbeiten" title="Rolle bearbeiten"><?php echo svg_icon_pencil(18); ?></button>
                    <button type="button" class="icon-button" data-dialog-open="info-role-<?php echo htmlspecialchars($roleKey); ?>" aria-label="Berechtigungen anzeigen" title="Berechtigungen anzeigen">ℹ</button>
                    <?php if ($roleKey !== 'admin'): ?>
                    <button type="button" class="icon-button danger" data-dialog-open="delete-role-<?php echo htmlspecialchars($roleKey); ?>" aria-label="Rolle löschen" title="Rolle löschen"><?php echo svg_icon_trash(18); ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($roleKey !== 'admin'): ?>
            <dialog id="delete-role-<?php echo htmlspecialchars($roleKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Rolle löschen</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <p class="muted">Rolle: <strong><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>)</p>
                    <form method="POST" data-dialog-close-on-submit data-confirm-submit="Rolle wirklich löschen?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_role">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                        <button type="submit" class="danger">Endgültig löschen</button>
                    </form>
                </div>
            </dialog>
            <?php endif; ?>

            <dialog id="info-role-<?php echo htmlspecialchars($roleKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Berechtigungen</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <div class="permission-list">
                        <?php if (count($rolePermissions) === 0): ?>
                            <div class="permission-item">Keine Berechtigungen zugeordnet.</div>
                        <?php else: ?>
                            <?php foreach ($rolePermissions as $permKey): ?>
                                <?php
                                    $permLabel = $permissionMap[$permKey]['label'] ?? $permKey;
                                    $permDesc = $permissionMap[$permKey]['description'] ?? '';
                                ?>
                                <div class="permission-item">
                                    <?php echo htmlspecialchars($permLabel); ?>
                                    <?php if ($permDesc): ?>
                                        <small><?php echo htmlspecialchars($permDesc); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="permission-list spaced">
                        <div class="permission-item heading">Kommentar-Limit (pro Nutzer/Projekt)</div>
                        <div class="permission-item">
                            <?php echo $roleCommentLimit > 0 ? (int)$roleCommentLimit : 'Unbegrenzt'; ?>
                        </div>
                    </div>
                    <div class="permission-list spaced">
                        <div class="permission-item heading">Kommentare löschen von Rollen</div>
                        <?php if (count($roleDeleteRoles) === 0): ?>
                            <div class="permission-item">Keine Rollen ausgewählt.</div>
                        <?php else: ?>
                            <?php foreach ($roleDeleteRoles as $deleteRoleKey): ?>
                                <?php
                                    $deleteRoleLabel = $rolesMap[$deleteRoleKey]['label'] ?? $deleteRoleKey;
                                ?>
                                <div class="permission-item"><?php echo htmlspecialchars($deleteRoleLabel); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </dialog>

            <dialog id="edit-role-<?php echo htmlspecialchars($roleKey); ?>">
                <div class="dialog-card">
                    <div class="dialog-header">
                        <h2>Rolle bearbeiten</h2>
                        <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
                    </div>
                    <form method="POST" class="role-form" data-dialog-close-on-submit data-role="<?php echo htmlspecialchars($roleKey); ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey); ?>">
                        <div class="field">
                            <label>Anzeigename</label>
                            <input type="text" name="role_label" value="<?php echo htmlspecialchars($roleLabel); ?>">
                        </div>
                        <div class="field">
                            <label>Berechtigungen</label>
                            <div class="permissions">
                                <?php foreach ($groupedPermissions as $group): ?>
                                    <div class="permission-group">
                                        <div class="permission-group-title"><?php echo htmlspecialchars($group['label']); ?></div>
                                        <?php foreach ($group['items'] as $perm): ?>
                                            <?php $isChecked = in_array($perm['key'], $rolePermissions, true); ?>
                                            <label>
                                                <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm['key']); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                <span>
                                                    <?php echo htmlspecialchars($perm['label']); ?>
                                                    <?php if (!empty($perm['description'])): ?>
                                                        <small><?php echo htmlspecialchars($perm['description']); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <?php if ($perm['key'] === 'comment_limit'): ?>
                                                <div class="permission-subfield comment-limit-field" data-limit-field>
                                                    <div class="hint">Nur wirksam mit Berechtigung "Kommentar-Limit". 0 = unbegrenzt.</div>
                                                    <input type="number" name="comment_limit" min="0" step="1" value="<?php echo (int)$roleCommentLimit; ?>" disabled>
                                                </div>
                                            <?php elseif ($perm['key'] === 'delete_comments'): ?>
                                                <div class="permission-subfield">
                                                    <div class="hint">Gilt nur mit Berechtigung "Kommentare löschen".</div>
                                                    <div class="role-selector" data-role-selector>
                                                        <button type="button" class="role-selector-toggle" aria-expanded="false">Rollen auswählen</button>
                                                        <div class="role-selector-panel" hidden>
                                                            <div class="role-selector-search">
                                                                <input type="text" placeholder="Rollen suchen..." aria-label="Rollen suchen">
                                                            </div>
                                                            <label class="role-selector-all">
                                                                <input type="checkbox" data-role-select-all>
                                                                <span>Alle Rollen auswählen</span>
                                                            </label>
                                                            <div class="role-selector-list">
                                                                <?php foreach ($roles as $roleItem): ?>
                                                                    <?php
                                                                        $targetRoleKey = $roleItem['role'] ?? '';
                                                                        $targetRoleLabel = $roleItem['label'] ?? $targetRoleKey;
                                                                        if (!$targetRoleKey) {
                                                                            continue;
                                                                        }
                                                                        $isChecked = in_array($targetRoleKey, $roleDeleteRoles, true);
                                                                    ?>
                                                                    <label>
                                                                        <input type="checkbox" name="comment_delete_roles[]" value="<?php echo htmlspecialchars($targetRoleKey); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                                        <span><?php echo htmlspecialchars($targetRoleLabel); ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php admin_reauth_dialog_body($adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft); ?>
                        <div class="actions">
                            <button type="submit" class="save-button" disabled>Speichern</button>
                        </div>
                    </form>
                </div>
            </dialog>
        <?php endforeach; ?>
    </div>
<?php }, ['admin'], ['admin-roles.js']); ?>
