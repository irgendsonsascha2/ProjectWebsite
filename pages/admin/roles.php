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
    ['key' => 'create_project', 'label' => 'Projekt erstellen', 'description' => 'Neue Projekte anlegen'],
    ['key' => 'edit_all', 'label' => 'Alle Projekte bearbeiten', 'description' => 'Beliebige Projekte bearbeiten'],
    ['key' => 'edit_own', 'label' => 'Eigene Projekte bearbeiten', 'description' => 'Nur eigene Projekte bearbeiten'],
    ['key' => 'delete_all', 'label' => 'Projekte löschen', 'description' => 'Projekte löschen (inkl. Kommentare/Likes)'],
    ['key' => 'manage_users', 'label' => 'Benutzer verwalten', 'description' => 'Admin-Funktionen für Benutzer/Einladungen'],
    ['key' => 'generate_codes', 'label' => 'Einladungscodes erzeugen', 'description' => 'Registrierungs-Codes erstellen'],
    ['key' => 'comment', 'label' => 'Kommentieren', 'description' => 'Kommentare erstellen/bearbeiten'],
    ['key' => 'like_dislike', 'label' => 'Likes/Dislikes', 'description' => 'Likes und Dislikes vergeben']
];

function normalize_permission_keys($keys) {
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

        if (!preg_match('/^[a-z0-9_-]{2,40}$/', $roleKey)) {
            $error = 'Rollen-Schlüssel ist ungültig (2-40 Zeichen, a-z, 0-9, _ -).';
        } elseif ($db->roles_config->findOne(['role' => $roleKey])) {
            $error = 'Diese Rolle existiert bereits.';
        } else {
            $db->roles_config->insertOne([
                'role' => $roleKey,
                'label' => $roleLabel ?: $roleKey,
                'permissions' => $permissions
            ]);
            $notice = 'Rolle wurde erstellt.';
        }
    } elseif ($action === 'update_role') {
        $roleKey = strtolower(trim($_POST['role_key'] ?? ''));
        $roleLabel = trim($_POST['role_label'] ?? '');
        $permissions = normalize_permission_keys($_POST['permissions'] ?? []);

        if (!$roleKey) {
            $error = 'Rolle fehlt.';
        } else {
            $db->roles_config->updateOne(
                ['role' => $roleKey],
                ['$set' => ['label' => $roleLabel ?: $roleKey, 'permissions' => $permissions]]
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

        <section class="card">
            <h2>Neue Rolle erstellen</h2>
            <form method="POST">
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
                        <?php foreach ($permissions as $permission): ?>
                            <?php
                                $permKey = $permission['key'] ?? '';
                                $permLabel = $permission['label'] ?? $permKey;
                                $permDesc = $permission['description'] ?? '';
                                if (!$permKey) {
                                    continue;
                                }
                            ?>
                            <label>
                                <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permKey); ?>">
                                <span>
                                    <?php echo htmlspecialchars($permLabel); ?>
                                    <?php if ($permDesc): ?>
                                        <small><?php echo htmlspecialchars($permDesc); ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Rolle anlegen</button>
                </div>
            </form>
        </section>

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
                    $userCount = $roleCounts[$roleKey] ?? 0;
                ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($roleLabel); ?></h3>
                    <div class="hint">Schlüssel: <?php echo htmlspecialchars($roleKey); ?> · Nutzer: <?php echo (int)$userCount; ?></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey); ?>">
                        <div class="field">
                            <label>Anzeigename</label>
                            <input type="text" name="role_label" value="<?php echo htmlspecialchars($roleLabel); ?>">
                        </div>
                        <div class="field">
                            <label>Berechtigungen</label>
                            <div class="permissions">
                                <?php foreach ($permissions as $permission): ?>
                                    <?php
                                        $permKey = $permission['key'] ?? '';
                                        $permLabel = $permission['label'] ?? $permKey;
                                        $permDesc = $permission['description'] ?? '';
                                        if (!$permKey) {
                                            continue;
                                        }
                                        $isChecked = in_array($permKey, $rolePermissions, true);
                                    ?>
                                    <label>
                                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permKey); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <span>
                                            <?php echo htmlspecialchars($permLabel); ?>
                                            <?php if ($permDesc): ?>
                                                <small><?php echo htmlspecialchars($permDesc); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="actions">
                            <button type="submit">Speichern</button>
                        </div>
                    </form>
                    <form method="POST" onsubmit="return confirm('Rolle wirklich löschen?');">
                        <input type="hidden" name="action" value="delete_role">
                        <input type="hidden" name="role_key" value="<?php echo htmlspecialchars($roleKey); ?>">
                        <div class="actions">
                            <button type="submit" class="danger">Rolle löschen</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>
