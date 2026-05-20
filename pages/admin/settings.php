<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/site_settings.php';

use MongoDB\BSON\UTCDateTime;

$allowedRoles = ['admin'];

$notice = '';
$error = '';
$settings = site_settings_load($db);

if (isset($_POST['action']) && $_POST['action'] === 'save_site_settings') {
    $input = [
        'max_image_mb' => (int) ($_POST['max_image_mb'] ?? 0),
        'max_video_mb' => (int) ($_POST['max_video_mb'] ?? 0),
        'max_image_width' => (int) ($_POST['max_image_width'] ?? 0),
        'max_image_height' => (int) ($_POST['max_image_height'] ?? 0),
        'max_files_per_upload' => (int) ($_POST['max_files_per_upload'] ?? 0),
        'project_detail_media_limit' => (int) ($_POST['project_detail_media_limit'] ?? 0),
        'comment_text_max_length' => (int) ($_POST['comment_text_max_length'] ?? 0),
    ];
    $settings = site_settings_normalize($input);

    $now = new UTCDateTime();
    $doc = site_settings_build_document($settings, $now);
    $doc['updated_by'] = (string) ($_SESSION['user_id'] ?? '');

    try {
        [$adminClient, $adminDb] = get_admin_mongo_connection();
        unset($adminClient);
        $existing = $adminDb->site_pages->findOne(['_id' => site_settings_document_id()]);
        if ($existing && !empty($existing['created_at'])) {
            $doc['created_at'] = $existing['created_at'];
        }

        $adminDb->site_pages->replaceOne(
            ['_id' => site_settings_document_id()],
            $doc,
            ['upsert' => true]
        );

        site_settings_clear_cache();
        site_settings_apply($adminDb);
        $settings = site_settings_load($adminDb, true);
        $notice = 'Einstellungen gespeichert. Upload-Limits gelten ab dem nächsten Request.';
    } catch (Exception $e) {
        $error = 'Datenbankfehler: ' . $e->getMessage();
    }
}

admin_render_page('Einstellungen', 'settings', function () use ($notice, $error, $settings) { ?>
    <div class="page-header">
        <h1>Allgemeine Einstellungen</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <p class="field-hint">
        Werte gelten für Medien-Uploads und einige Anzeige-Limits auf der Website.
        Der PHP-Server muss große Uploads weiterhin erlauben (<code>post_max_size</code> / <code>upload_max_filesize</code>).
    </p>

    <div class="admin-card admin-card--spaced">
        <h2>Medien-Uploads</h2>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_site_settings">

            <div class="field">
                <label for="max_image_mb">Max. Bildgröße (MB)</label>
                <input type="number" id="max_image_mb" name="max_image_mb" min="1" max="500" step="1"
                    value="<?php echo (int) $settings['max_image_mb']; ?>" required>
            </div>

            <div class="field">
                <label for="max_video_mb">Max. Videogröße (MB)</label>
                <input type="number" id="max_video_mb" name="max_video_mb" min="1" max="10240" step="1"
                    value="<?php echo (int) $settings['max_video_mb']; ?>" required>
                <div class="hint">Aktuell: <?php echo htmlspecialchars(media_upload_limit_label($settings['max_video_mb'] * 1024 * 1024), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="field">
                <label for="max_image_width">Max. Bildbreite (px, längere Seite)</label>
                <input type="number" id="max_image_width" name="max_image_width" min="320" max="8192" step="1"
                    value="<?php echo (int) $settings['max_image_width']; ?>" required>
            </div>

            <div class="field">
                <label for="max_image_height">Max. Bildhöhe (px, kürzere Seite)</label>
                <input type="number" id="max_image_height" name="max_image_height" min="240" max="8192" step="1"
                    value="<?php echo (int) $settings['max_image_height']; ?>" required>
                <div class="hint">Typisch 4K: 3840 × 2160 (Quer- oder Hochformat).</div>
            </div>

            <div class="field">
                <label for="max_files_per_upload">Max. Dateien pro Upload-Vorgang</label>
                <input type="number" id="max_files_per_upload" name="max_files_per_upload" min="1" max="100" step="1"
                    value="<?php echo (int) $settings['max_files_per_upload']; ?>" required>
            </div>

            <h2>Projekt &amp; Kommentare</h2>

            <div class="field">
                <label for="project_detail_media_limit">Medien initial auf Projektseite</label>
                <input type="number" id="project_detail_media_limit" name="project_detail_media_limit" min="1" max="200" step="1"
                    value="<?php echo (int) $settings['project_detail_media_limit']; ?>" required>
                <div class="hint">Anzahl sichtbarer Medien vor „Mehr laden“.</div>
            </div>

            <div class="field">
                <label for="comment_text_max_length">Max. Zeichen pro Kommentar</label>
                <input type="number" id="comment_text_max_length" name="comment_text_max_length" min="50" max="2000" step="1"
                    value="<?php echo (int) $settings['comment_text_max_length']; ?>" required>
            </div>

            <div class="actions">
                <button type="submit">Speichern</button>
            </div>
        </form>
    </div>
<?php }, $allowedRoles);
