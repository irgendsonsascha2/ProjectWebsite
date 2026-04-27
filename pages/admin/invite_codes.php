<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';

use MongoDB\BSON\UTCDateTime;

// Admin layout enforces admin role. Additionally require permission for safety.
if (!can('generate_codes')) {
    die("<h1>Zugriff verweigert</h1><p>Sie haben nicht die nötigen Rechte, um Einladungscodes zu verwalten.</p>");
}

$message = '';
$messageClass = 'alert';

// --- LOGIK: CODE GENERIEREN ---
if (isset($_POST['generate_code'])) {
    $targetRole = (string)($_POST['target_role'] ?? '');

    try {
        $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
    } catch (Exception $e) {
        // ignore - generation below still handles duplicate keys
    }

    $maxAttempts = 10;
    $newCode = null;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = strtoupper(bin2hex(random_bytes(8)));
        try {
            $db->registration_codes->insertOne([
                'code' => $candidate,
                'role' => $targetRole,
                'is_used' => false,
                'created_at' => new UTCDateTime()
            ]);
            $newCode = $candidate;
            break;
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $writeResult = $e->getWriteResult();
            $writeErrors = $writeResult ? $writeResult->getWriteErrors() : [];
            $isDuplicate = false;
            foreach ($writeErrors as $we) {
                if (method_exists($we, 'getCode') && (int)$we->getCode() === 11000) {
                    $isDuplicate = true;
                    break;
                }
            }
            if ($isDuplicate) {
                continue;
            }
            throw $e;
        }
    }

    if ($newCode === null) {
        $message = "❌ Konnte keinen eindeutigen Code generieren (bitte erneut versuchen).";
        $messageClass = 'alert error';
    } else {
        $message = "✅ Neuer Code generiert: <b>" . htmlspecialchars($newCode, ENT_QUOTES, 'UTF-8') . "</b>";
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

function site_base_url_from_request(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/pages/admin/index.php');
    $basePath = rtrim(str_replace(basename($script), '', $script), '/');
    // We are under /pages/admin/ -> need to go two levels up to the legacy index.php
    $basePath = preg_replace('#/pages/admin$#', '', $basePath);
    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

$inviteCodesData = [];
try {
    $activeCodes = $db->registration_codes->find(['is_used' => false]);
    foreach ($activeCodes as $c) {
        $base = site_base_url_from_request();
        $link = $base . '/index.php?page=register&reg_token=' . rawurlencode((string)($c['code'] ?? '')) . '#register-section';
        $inviteCodesData[] = [
            'role' => (string)($c['role'] ?? ''),
            'code' => (string)($c['code'] ?? ''),
            'link' => (string)$link,
        ];
    }
} catch (Exception $e) {
    // ignore
}

admin_render_page('Einladungscodes', 'invite_codes', function () use ($message, $messageClass, $roleOptions, $inviteCodesData) { ?>
    <div class="page-header">
        <h1>Einladungscodes</h1>
    </div>

    <?php if ($message): ?>
        <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="admin-card admin-card--spaced">
        <h2>Neuen Code generieren</h2>
        <form method="POST" class="code-form">
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
    </div>

    <div class="admin-card admin-card--spaced">
        <h2>Aktive Codes</h2>

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
    </div>
<?php });

