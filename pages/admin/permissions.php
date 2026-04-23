<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?page=login');
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
    <link rel="stylesheet" href="../../style/admin_permissions.css">
</head>

<body>
    <div class="container">
        <div class="page-header">
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

        <div class="toolbar">
            <button type="button" class="dialog-button" data-dialog-open="create-permission-dialog">Neue Berechtigung</button>
        </div>

        <dialog id="create-permission-dialog">
            <div class="dialog-card">
                <div class="dialog-header">
                    <h2>Neue Berechtigung erstellen</h2>
                    <button type="button" class="close-button" data-dialog-close>Schließen</button>
                </div>
                <form method="POST" class="permission-form">
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
                        <form method="POST" onsubmit="return confirm('Berechtigung wirklich löschen?');">
                            <input type="hidden" name="action" value="delete_permission">
                            <input type="hidden" name="perm_key" value="<?php echo htmlspecialchars($permKey); ?>">
                            <button type="submit" class="icon-button danger" aria-label="Berechtigung löschen" title="Berechtigung löschen">🗑</button>
                        </form>
                    </div>
                </div>

                <dialog id="info-permission-<?php echo htmlspecialchars($permKey); ?>">
                    <div class="dialog-card">
                        <div class="dialog-header">
                            <h2>Berechtigung</h2>
                            <button type="button" class="close-button" data-dialog-close>Schließen</button>
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
                            <button type="button" class="close-button" data-dialog-close>Schließen</button>
                        </div>
                        <form method="POST" class="permission-form">
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
                    </div>
                </dialog>
            <?php endforeach; ?>
        </div>
    </div>
</body>

<script>
(() => {
    const openButtons = Array.from(document.querySelectorAll('[data-dialog-open]'));
    const closeButtons = Array.from(document.querySelectorAll('[data-dialog-close]'));

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
})();
</script>

</html>
