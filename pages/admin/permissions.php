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
        $permKey = normalize_perm_key($_POST['perm_key'] ?? '');
        $permLabel = trim($_POST['perm_label'] ?? '');
        $permDesc = trim($_POST['perm_desc'] ?? '');

        if (!preg_match('/^[a-z0-9_-]{2,60}$/', $permKey)) {
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
        $permKey = normalize_perm_key($_POST['perm_key'] ?? '');
        $permLabel = trim($_POST['perm_label'] ?? '');
        $permDesc = trim($_POST['perm_desc'] ?? '');

        if (!$permKey) {
            $error = 'Berechtigung fehlt.';
        } else {
            $db->permissions_config->updateOne(
                ['key' => $permKey],
                ['$set' => ['label' => $permLabel ?: $permKey, 'description' => $permDesc]]
            );
            $notice = 'Berechtigung wurde aktualisiert.';
        }
    } elseif ($action === 'delete_permission') {
        $permKey = normalize_perm_key($_POST['perm_key'] ?? '');
        $roleUsage = $db->roles_config->countDocuments(['permissions' => $permKey]);
        if ($roleUsage > 0) {
            $error = 'Berechtigung ist noch Rollen zugeordnet.';
        } else {
            $db->permissions_config->deleteOne(['key' => $permKey]);
            $notice = 'Berechtigung wurde gelöscht.';
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

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Berechtigungen verwalten</title>
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

        textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            min-height: 80px;
            resize: vertical;
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
            <h1>Berechtigungen verwalten</h1>
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
            <h2>Neue Berechtigung erstellen</h2>
            <form method="POST">
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
        </section>

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
                    <h3><?php echo htmlspecialchars($permLabel); ?></h3>
                    <div class="hint">Schlüssel: <?php echo htmlspecialchars($permKey); ?> · Rollen: <?php echo (int)$usageCount; ?></div>
                    <form method="POST">
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
                        <div class="actions">
                            <button type="submit">Speichern</button>
                        </div>
                    </form>
                    <form method="POST" onsubmit="return confirm('Berechtigung wirklich löschen?');">
                        <input type="hidden" name="action" value="delete_permission">
                        <input type="hidden" name="perm_key" value="<?php echo htmlspecialchars($permKey); ?>">
                        <div class="actions">
                            <button type="submit" class="danger">Berechtigung löschen</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>
