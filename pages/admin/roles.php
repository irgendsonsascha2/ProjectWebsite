<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?page=account');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren.</p>");
}

$notice = '';
$error = '';

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

function normalize_permission_keys($keys) {
    if ($keys instanceof Traversable) {
        $keys = iterator_to_array($keys);
    }
    if (!is_array($keys)) {
        return [];
    }
    $filtered = [];
    foreach ($keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $key = trim($key);
        if ($key !== '' && !in_array($key, $filtered, true)) {
            $filtered[] = $key;
        }
    }
    return $filtered;
}

function normalize_role_keys($keys) {
    if ($keys instanceof Traversable) {
        $keys = iterator_to_array($keys);
    }
    if (!is_array($keys)) {
        return [];
    }
    $filtered = [];
    foreach ($keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $key = trim($key);
        if ($key !== '' && !in_array($key, $filtered, true)) {
            $filtered[] = $key;
        }
    }
    return $filtered;
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
    if ($action === 'create_role') {
        $roleKey = strtolower(trim($_POST['role_key'] ?? ''));
        $roleLabel = trim($_POST['role_label'] ?? '');
        $permissions = normalize_permission_keys($_POST['permissions'] ?? []);
        $commentDeleteRoles = normalize_role_keys($_POST['comment_delete_roles'] ?? []);
        $commentLimit = isset($_POST['comment_limit']) ? (int)$_POST['comment_limit'] : 0;
        if ($commentLimit < 0) {
            $commentLimit = 0;
        }

        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $roleKey)) {
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
        $roleKey = strtolower(trim($_POST['role_key'] ?? ''));
        $roleLabel = trim($_POST['role_label'] ?? '');
        $permissions = normalize_permission_keys($_POST['permissions'] ?? []);
        $commentDeleteRoles = normalize_role_keys($_POST['comment_delete_roles'] ?? []);
        $commentLimit = isset($_POST['comment_limit']) ? (int)$_POST['comment_limit'] : 0;
        if ($commentLimit < 0) {
            $commentLimit = 0;
        }

        if (!$roleKey) {
            $error = 'Rolle fehlt.';
        } else {
            $db->roles_config->updateOne(
                ['role' => $roleKey],
                ['$set' => ['label' => $roleLabel ?: $roleKey, 'permissions' => $permissions, 'comment_delete_roles' => $commentDeleteRoles, 'comment_limit' => $commentLimit]]
            );
            $notice = 'Rolle wurde aktualisiert.';
        }
    } elseif ($action === 'delete_role') {
        $roleKey = strtolower(trim($_POST['role_key'] ?? ''));
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

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Rollen verwalten</title>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.6;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .admin-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 10px 0 20px;
        }

        .admin-nav a {
            text-decoration: none;
            color: #111;
            background: #f2f2f2;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .admin-nav a:hover {
            background: #e9e9e9;
        }

        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin: 12px 0;
        }

        .alert.error {
            background: #ffe5e5;
            color: #7a0000;
            border: 1px solid #f5bcbc;
        }

        .alert.success {
            background: #e7f8e9;
            color: #225a2d;
            border: 1px solid #bfe7c8;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 14px;
            background: #fafafa;
        }

        .card h3 {
            margin-top: 0;
        }

        .field {
            margin-bottom: 10px;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        input[type="text"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .permissions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 6px;
            margin-top: 6px;
        }

        .permissions label {
            font-weight: 400;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .permissions small {
            display: block;
            color: #666;
            font-size: 12px;
        }

        .permission-group {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px;
            background: #f8fafc;
        }

        .permission-group-title {
            font-weight: 600;
            color: #0f172a;
            margin: 2px 0 6px 2px;
            font-size: 0.95rem;
        }

        .permission-subfield {
            margin: 6px 0 10px 28px;
            padding-left: 8px;
            border-left: 2px solid #e2e8f0;
        }

        .comment-limit-field.is-disabled {
            opacity: 0.6;
        }

        .role-selector {
            position: relative;
        }

        .role-selector.is-disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        .role-selector-toggle {
            width: 100%;
            text-align: left;
            background: #f1f5f9;
            border: 1px solid #cbd5f5;
            color: #0f172a;
            padding: 8px 10px;
            border-radius: 6px;
        }

        .role-selector-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px;
            z-index: 5;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .role-selector-search input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #cbd5f5;
            border-radius: 6px;
        }

        .role-selector-list {
            max-height: 180px;
            overflow: auto;
            margin-top: 6px;
            display: grid;
            gap: 6px;
        }

        .role-selector-all {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 6px;
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        button {
            background: #333;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
        }

        button.secondary {
            background: #666;
        }

        button.danger {
            background: #a00;
        }

        button:hover {
            background: #555;
        }

        .hint {
            color: #666;
            font-size: 12px;
        }

        .role-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .card {
            position: relative;
            padding-bottom: 56px;
        }

        .card-actions {
            position: absolute;
            right: 12px;
            bottom: 12px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .card-actions form {
            margin: 0;
        }

        .permission-list {
            display: grid;
            gap: 6px;
        }

        .permission-item {
            background: #fff;
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            padding: 8px 10px;
        }

        .permission-item small {
            display: block;
            color: #666;
            font-size: 12px;
        }

        .dialog-button {
            background: #111;
        }

        .icon-button {
            background: #f2f2f2;
            color: #111;
            border: 1px solid #ccc;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 16px;
        }

        .icon-button:hover {
            background: #e7e7e7;
        }

        dialog {
            border: none;
            border-radius: 10px;
            padding: 0;
            width: min(720px, 92vw);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
        }

        dialog::backdrop {
            background: rgba(0, 0, 0, 0.4);
        }

        .dialog-card {
            padding: 18px;
        }

        .dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .dialog-header h2 {
            margin: 0;
        }

        .close-button {
            background: #666;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            margin: 14px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div style="display:flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <h1>Rollen verwalten</h1>
            <a href="index.php">Zurück</a>
        </div>

        <div class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="roles.php">Rollen</a>
            <a href="permissions.php">Berechtigungen</a>
            <a href="../../index.php">Zur Hauptseite</a>
        </div>

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
                    <button type="button" class="close-button" data-dialog-close>Schließen</button>
                </div>
                <form method="POST" class="role-form">
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
                    $rolePermissions = normalize_permission_keys($rolePermissions);
                    $roleDeleteRoles = $role['comment_delete_roles'] ?? [];
                    $roleDeleteRoles = normalize_role_keys($roleDeleteRoles);
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
                        <button type="button" class="icon-button" data-dialog-open="edit-role-<?php echo htmlspecialchars($roleKey); ?>" aria-label="Rolle bearbeiten" title="Rolle bearbeiten">✎</button>
                        <button type="button" class="icon-button" data-dialog-open="info-role-<?php echo htmlspecialchars($roleKey); ?>" aria-label="Berechtigungen anzeigen" title="Berechtigungen anzeigen">ℹ</button>
                        <form method="POST" onsubmit="return confirm('Rolle wirklich löschen?');">
                        <input type="hidden" name="action" value="delete_role">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey); ?>">
                            <button type="submit" class="icon-button danger" aria-label="Rolle löschen" title="Rolle löschen">🗑</button>
                        </form>
                    </div>
                </div>

                <dialog id="info-role-<?php echo htmlspecialchars($roleKey); ?>">
                    <div class="dialog-card">
                        <div class="dialog-header">
                            <h2>Berechtigungen</h2>
                            <button type="button" class="close-button" data-dialog-close>Schließen</button>
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
                        <div class="permission-list" style="margin-top: 1rem;">
                            <div class="permission-item" style="font-weight: 600;">Kommentar-Limit (pro Nutzer/Projekt)</div>
                            <div class="permission-item">
                                <?php echo $roleCommentLimit > 0 ? (int)$roleCommentLimit : 'Unbegrenzt'; ?>
                            </div>
                        </div>
                        <div class="permission-list" style="margin-top: 1rem;">
                            <div class="permission-item" style="font-weight: 600;">Kommentare löschen von Rollen</div>
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
                            <button type="button" class="close-button" data-dialog-close>Schließen</button>
                        </div>
                        <form method="POST" class="role-form" data-role="<?php echo htmlspecialchars($roleKey); ?>">
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
                            <div class="actions">
                                <button type="submit" class="save-button" disabled>Speichern</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </div>
</body>

<script>
(() => {
    const forms = Array.from(document.querySelectorAll('.role-form'));
    const openButtons = Array.from(document.querySelectorAll('[data-dialog-open]'));
    const closeButtons = Array.from(document.querySelectorAll('[data-dialog-close]'));

    function snapshotForm(form) {
        const data = new FormData(form);
        const entries = [];
        for (const [key, value] of data.entries()) {
            entries.push([key, value]);
        }
        entries.sort((a, b) => {
            if (a[0] === b[0]) return a[1].localeCompare(b[1]);
            return a[0].localeCompare(b[0]);
        });
        return JSON.stringify(entries);
    }

    function updateCommentLimitState(form) {
        const limitPermission = form.querySelector('input[name="permissions[]"][value="comment_limit"]');
        const limitField = form.querySelector('[data-limit-field]');
        if (!limitField) return;
        const limitInput = limitField.querySelector('input[name="comment_limit"]');
        if (!limitInput) return;
        const enabled = limitPermission && limitPermission.checked;
        limitInput.disabled = !enabled;
        limitField.classList.toggle('is-disabled', !enabled);
    }

    function updateDeleteRolesState(form) {
        const deletePermission = form.querySelector('input[name="permissions[]"][value="delete_comments"]');
        const selector = form.querySelector('[data-role-selector]');
        if (!selector) return;
        const enabled = deletePermission && deletePermission.checked;
        selector.classList.toggle('is-disabled', !enabled);
        selector.querySelectorAll('button, input').forEach((el) => {
            el.disabled = !enabled;
        });
    }

    forms.forEach((form) => {
        const saveButton = form.querySelector('.save-button');
        if (!saveButton) return;
        let initial = snapshotForm(form);

        function updateState() {
            const current = snapshotForm(form);
            const isDirty = current !== initial;
            saveButton.disabled = !isDirty;
        }

        form.addEventListener('input', updateState);
        form.addEventListener('change', updateState);
        form.addEventListener('input', () => updateCommentLimitState(form));
        form.addEventListener('change', () => updateCommentLimitState(form));
        form.addEventListener('input', () => updateDeleteRolesState(form));
        form.addEventListener('change', () => updateDeleteRolesState(form));
        form.addEventListener('reset', () => {
            initial = snapshotForm(form);
            updateState();
            updateCommentLimitState(form);
            updateDeleteRolesState(form);
        });

        form.addEventListener('submit', () => {
            saveButton.disabled = true;
        });

        updateCommentLimitState(form);
        updateDeleteRolesState(form);
    });

    openButtons.forEach((button) => {
        const dialogId = button.dataset.dialogOpen;
        const dialog = dialogId ? document.getElementById(dialogId) : null;
        if (!dialog || typeof dialog.showModal !== 'function') return;
        button.addEventListener('click', () => {
            dialog.showModal();
        });
    });

    closeButtons.forEach((button) => {
        const dialog = button.closest('dialog');
        if (!dialog) return;
        button.addEventListener('click', () => {
            dialog.close();
        });
    });

    const dialogs = Array.from(document.querySelectorAll('dialog'));
    dialogs.forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    const roleSelectors = Array.from(document.querySelectorAll('[data-role-selector]'));
    roleSelectors.forEach((selector) => {
        const toggle = selector.querySelector('.role-selector-toggle');
        const panel = selector.querySelector('.role-selector-panel');
        const searchInput = selector.querySelector('.role-selector-search input');
        const selectAll = selector.querySelector('[data-role-select-all]');
        const items = Array.from(selector.querySelectorAll('.role-selector-list label'));
        if (!toggle || !panel) return;

        function updateToggleLabel() {
            const checked = items.filter((item) => item.querySelector('input')?.checked).length;
            toggle.textContent = checked > 0 ? `${checked} Rolle(n) ausgewählt` : 'Rollen auswählen';
        }

        function updateSelectAllState() {
            const inputs = items.map((item) => item.querySelector('input')).filter(Boolean);
            const checkedCount = inputs.filter((input) => input.checked).length;
            if (selectAll) {
                selectAll.checked = checkedCount > 0 && checkedCount === inputs.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < inputs.length;
            }
        }

        function filterList() {
            const query = (searchInput?.value || '').trim().toLowerCase();
            items.forEach((item) => {
                const text = item.textContent ? item.textContent.toLowerCase() : '';
                item.style.display = text.includes(query) ? '' : 'none';
            });
        }

        toggle.addEventListener('click', () => {
            const isOpen = !panel.hasAttribute('hidden');
            if (isOpen) {
                panel.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            } else {
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        document.addEventListener('click', (event) => {
            if (!selector.contains(event.target)) {
                panel.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        items.forEach((item) => {
            const input = item.querySelector('input');
            if (!input) return;
            input.addEventListener('change', () => {
                updateSelectAllState();
                updateToggleLabel();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                const checked = selectAll.checked;
                items.forEach((item) => {
                    const input = item.querySelector('input');
                    if (input) {
                        input.checked = checked;
                    }
                });
                updateSelectAllState();
                updateToggleLabel();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterList);
        }

        updateSelectAllState();
        updateToggleLabel();
    });
})();
</script>

</html>
