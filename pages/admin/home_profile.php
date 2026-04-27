<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';

use MongoDB\BSON\UTCDateTime;

// Admin + Content Manager dürfen Startseiten-Content pflegen.
$allowedRoles = ['admin', 'content_manager'];

function home_profile_default_portrait_src(): string
{
    return 'img/profile-placeholder.svg';
}

function is_safe_image_path(string $path): bool
{
    $path = trim($path);
    if ($path === '') {
        return false;
    }
    // Nur projektinterne relative Pfade erlauben (kein http/https, kein ../)
    if (preg_match('/^https?:\\/\\//i', $path)) {
        return false;
    }
    if (strpos($path, '..') !== false) {
        return false;
    }
    return strpos($path, 'content/images/') === 0 || strpos($path, 'img/') === 0;
}

function delete_portrait_if_owned(string $portraitUrl): void
{
    $portraitUrl = trim($portraitUrl);
    if ($portraitUrl === '' || strpos($portraitUrl, 'content/images/') !== 0) {
        return;
    }
    $path = realpath(__DIR__ . '/../../' . $portraitUrl);
    $contentImages = realpath(__DIR__ . '/../../content/images');
    if (!$path || !$contentImages) {
        return;
    }
    // Nur innerhalb content/images löschen
    if (strpos($path, $contentImages) !== 0) {
        return;
    }
    if (is_file($path)) {
        @unlink($path);
    }
}

$notice = '';
$error = '';

// Datensatz laden (Single-Doc Ansatz)
$docId = 'home_profile';
$doc = null;
try {
    $doc = $db->site_pages->findOne(['_id' => $docId]);
} catch (Exception $e) {
    $doc = null;
}

// Defaults (falls noch nicht initialisiert)
$displayName = (string)($doc['display_name'] ?? 'Dein Name');
$kicker = (string)($doc['kicker'] ?? 'Deine Position / Spezialisierung');
$lead = (string)($doc['lead'] ?? "Kurze Beschreibung.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.");
$body = (string)($doc['body'] ?? "Langer Text.\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.\n\nSed nisi. Nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum. Praesent mauris.");
$portraitUrl = (string)($doc['portrait_url'] ?? '');
if ($portraitUrl === '' || !is_safe_image_path($portraitUrl)) {
    $portraitUrl = home_profile_default_portrait_src();
}

if (isset($_POST['action']) && $_POST['action'] === 'save_home_profile') {
    $nextDisplayName = trim((string)($_POST['display_name'] ?? ''));
    $nextKicker = trim((string)($_POST['kicker'] ?? ''));
    $nextLead = trim((string)($_POST['lead'] ?? ''));
    $nextBody = trim((string)($_POST['body'] ?? ''));

    if ($nextDisplayName === '' || $nextKicker === '' || $nextLead === '' || $nextBody === '') {
        $error = 'Bitte alle Felder ausfüllen.';
    } else {
        $set = [
            'display_name' => $nextDisplayName,
            'kicker' => $nextKicker,
            'lead' => $nextLead,
            'body' => $nextBody,
            'updated_at' => new UTCDateTime(),
            'updated_by' => (string)($_SESSION['user_id'] ?? ''),
        ];

        $newPortraitUrl = null;
        $portraitDeleted = false;

        // Portrait löschen (optional)
        if (!empty($_POST['delete_portrait']) && $_POST['delete_portrait'] === '1') {
            if (!empty($doc['portrait_url']) && is_string($doc['portrait_url'])) {
                delete_portrait_if_owned($doc['portrait_url']);
            }
            $newPortraitUrl = '';
            $portraitDeleted = true;
        }

        // Neues Portrait hochladen (optional, image only)
        if (!$portraitDeleted && isset($_FILES['portrait_file']) && isset($_FILES['portrait_file']['tmp_name']) && $_FILES['portrait_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['portrait_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload fehlgeschlagen: ' . upload_error_message($_FILES['portrait_file']['error']);
            } else {
                $tmp = $_FILES['portrait_file']['tmp_name'];
                $type = detect_media_type($tmp, $_FILES['portrait_file']['name'] ?? null);
                if ($type !== 'image') {
                    $error = 'Bitte nur ein Bild als Portrait hochladen.';
                } else {
                    $validationError = validate_media_upload($tmp, (int)($_FILES['portrait_file']['size'] ?? 0), 'image');
                    if ($validationError) {
                        $error = $validationError;
                    } else {
                        $ext = pathinfo((string)($_FILES['portrait_file']['name'] ?? ''), PATHINFO_EXTENSION);
                        $safeExt = sanitize_extension($ext);
                        if ($safeExt === '') {
                            $safeExt = '.jpg';
                        }
                        $contentImageDir = __DIR__ . '/../../content/images';
                        if (!is_dir($contentImageDir)) {
                            mkdir($contentImageDir, 0755, true);
                        }
                        $filename = uniqid('home_portrait_', true) . $safeExt;
                        $targetFile = $contentImageDir . '/' . $filename;
                        if (!move_uploaded_file($tmp, $targetFile)) {
                            $error = 'Konnte Portrait nicht speichern.';
                        } else {
                            // altes Portrait entfernen (nur wenn wir es besitzen)
                            if (!empty($doc['portrait_url']) && is_string($doc['portrait_url'])) {
                                delete_portrait_if_owned($doc['portrait_url']);
                            }
                            $newPortraitUrl = 'content/images/' . $filename;
                        }
                    }
                }
            }
        }

        if (!$error) {
            if ($newPortraitUrl !== null) {
                $set['portrait_url'] = $newPortraitUrl;
            }
            $setOnInsert = [
                'created_at' => new UTCDateTime(),
            ];

            try {
                $db->site_pages->updateOne(
                    ['_id' => $docId],
                    ['$set' => $set, '$setOnInsert' => $setOnInsert],
                    ['upsert' => true]
                );
                $notice = 'Startseiten-Profil gespeichert.';
                $doc = $db->site_pages->findOne(['_id' => $docId]);
                $displayName = (string)($doc['display_name'] ?? $nextDisplayName);
                $kicker = (string)($doc['kicker'] ?? $nextKicker);
                $lead = (string)($doc['lead'] ?? $nextLead);
                $body = (string)($doc['body'] ?? $nextBody);
                $portraitUrl = (string)($doc['portrait_url'] ?? '');
                if ($portraitUrl === '' || !is_safe_image_path($portraitUrl)) {
                    $portraitUrl = home_profile_default_portrait_src();
                }
            } catch (Exception $e) {
                $error = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }
}

admin_render_page('Startseite', 'home_profile', function () use ($notice, $error, $displayName, $kicker, $lead, $body, $portraitUrl) { ?>
    <div class="page-header">
        <h1>Startseiten‑Profil</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="admin-card admin-card--spaced">
        <h2>Bearbeiten</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_home_profile">

            <div class="field">
                <label for="display_name">Name</label>
                <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="field">
                <label for="kicker">Position / Was du bist</label>
                <input type="text" id="kicker" name="kicker" value="<?php echo htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="field">
                <label for="lead">Kurzbeschreibung</label>
                <textarea id="lead" name="lead" required><?php echo htmlspecialchars($lead, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field">
                <label for="body">Langer Text</label>
                <textarea id="body" name="body" required><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field">
                <label for="portrait_file">Portrait (optional)</label>
                <input type="file" id="portrait_file" name="portrait_file" accept="image/*">
                <div class="hint">Wenn du ein neues Bild hochlädst, ersetzt es das bisherige Portrait.</div>
            </div>

            <div class="field">
                <label class="checkbox-row">
                    <input type="checkbox" name="delete_portrait" value="1">
                    <span>Portrait zurücksetzen (DB‑Portrait löschen, Fallback auf <code>img/portrait.*</code>)</span>
                </label>
            </div>

            <div class="actions">
                <button type="submit">Speichern</button>
            </div>
        </form>
    </div>
<?php }, $allowedRoles);

