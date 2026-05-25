<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/site_settings.php';
require_once __DIR__ . '/../../includes/stress_mode.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';

use MongoDB\BSON\UTCDateTime;

$allowedRoles = ['admin'];

$notice = '';
$error = '';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
$settings = site_settings_load($db);
$stressAutoStatus = stress_mode_auto_status();

if (isset($_POST['action']) && $_POST['action'] === 'save_site_settings') {
    $reauth = admin_reauth_require_fresh_or_post();
    if (! $reauth['ok']) {
        $error = $reauth['error'];
    } else {
    $adminReauthFresh = admin_reauth_is_fresh();
    $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

    $input = [
        'site_name' => (string) ($_POST['site_name'] ?? ''),
        'max_image_mb' => (int) ($_POST['max_image_mb'] ?? 0),
        'max_video_mb' => (int) ($_POST['max_video_mb'] ?? 0),
        'max_image_width' => (int) ($_POST['max_image_width'] ?? 0),
        'max_image_height' => (int) ($_POST['max_image_height'] ?? 0),
        'max_files_per_upload' => (int) ($_POST['max_files_per_upload'] ?? 0),
        'project_detail_media_limit' => (int) ($_POST['project_detail_media_limit'] ?? 0),
        'comment_text_max_length' => (int) ($_POST['comment_text_max_length'] ?? 0),
        'stress_mode_enabled' => isset($_POST['stress_mode_enabled']),
        'stress_auto_enabled' => isset($_POST['stress_auto_enabled']),
        'stress_auto_activate_rpm' => (int) ($_POST['stress_auto_activate_rpm'] ?? 0),
        'stress_auto_release_rpm' => (int) ($_POST['stress_auto_release_rpm'] ?? 0),
        'stress_auto_hold_minutes' => (int) ($_POST['stress_auto_hold_minutes'] ?? 0),
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
        $stressAutoStatus = stress_mode_auto_status();
        $notice = 'Einstellungen gespeichert. Website-Name, Schutzmodus und Upload-Limits gelten ab dem nächsten Request.';
    } catch (Exception $e) {
        $error = 'Datenbankfehler: ' . $e->getMessage();
    }
    }
}

admin_render_page('Einstellungen', 'settings', function () use ($notice, $error, $settings, $stressAutoStatus, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) { ?>
    <div class="page-header">
        <h1>Allgemeine Einstellungen</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <p class="field-hint">
        Werte gelten für Medien-Uploads, Anzeige-Limits und den öffentlichen Website-Namen
        (Seitentitel, Laravel-Auth-Seiten wie Passwort vergessen, E-Mails, 2FA-Anzeige in Authenticator-Apps).
        Der PHP-Server muss große Uploads weiterhin erlauben (<code>post_max_size</code> / <code>upload_max_filesize</code>).
    </p>

    <div class="admin-card admin-card--spaced">
        <form method="POST" id="site-settings-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_site_settings">

            <h2>Website</h2>

            <h2>Schutzmodus</h2>

            <?php
                $autoActive = ! empty($stressAutoStatus['active']);
                $currentRpm = (int) ($stressAutoStatus['rpm'] ?? 0);
                $activateRpm = (int) ($settings['stress_auto_activate_rpm'] ?? 600);
                $releaseRpm = (int) ($settings['stress_auto_release_rpm'] ?? 250);
                $activeUntil = (int) ($stressAutoStatus['active_until'] ?? 0);
            ?>
            <p class="field-hint">
                Aktuelle Last (geschätzt): <strong><?php echo $currentRpm; ?></strong> Requests/min. (alle PHP-Seiten mit Bootstrap).
                <?php if ($autoActive && $activeUntil > time()): ?>
                    <br><strong>Automatischer Schutzmodus ist aktiv</strong> bis <?php echo htmlspecialchars(date('Y-m-d H:i:s', $activeUntil), ENT_QUOTES, 'UTF-8'); ?>.
                <?php elseif (! empty($settings['stress_auto_enabled'])): ?>
                    <br>Automatik eingeschaltet — bei ≥ <?php echo $activateRpm; ?> Requests/min wird der Schutzmodus für Gäste ausgelöst.
                <?php endif; ?>
            </p>

            <div class="field">
                <label class="checkbox-label">
                    <input type="checkbox" id="stress_auto_enabled" name="stress_auto_enabled" value="1"
                        <?php echo ! empty($settings['stress_auto_enabled']) ? 'checked' : ''; ?>>
                    Automatisch bei Überlastung aktivieren
                </label>
                <div class="hint">
                    Zählt eingehende Requests (Datei unter <code>logs/</code>). Überschreitung der Aktivierungsschwelle → Schutzmodus für Gäste;
                    Ende erst nach Mindestdauer und wenn die Last unter die Freigabe-Schwelle fällt (Hysterese gegen Flackern).
                </div>
            </div>

            <div class="field">
                <label for="stress_auto_activate_rpm">Aktivierung ab (Requests/min.)</label>
                <input type="number" id="stress_auto_activate_rpm" name="stress_auto_activate_rpm" min="10" max="10000" step="1"
                    value="<?php echo (int) $settings['stress_auto_activate_rpm']; ?>" required>
            </div>

            <div class="field">
                <label for="stress_auto_release_rpm">Freigabe unter (Requests/min.)</label>
                <input type="number" id="stress_auto_release_rpm" name="stress_auto_release_rpm" min="1" max="10000" step="1"
                    value="<?php echo (int) $settings['stress_auto_release_rpm']; ?>" required>
                <div class="hint">Muss unter der Aktivierungsschwelle liegen (wird beim Speichern ggf. angepasst). Aktuell: Freigabe &lt; <?php echo $releaseRpm; ?>, Aktivierung ≥ <?php echo $activateRpm; ?>.</div>
            </div>

            <div class="field">
                <label for="stress_auto_hold_minutes">Mindestdauer nach Auslösung (Minuten)</label>
                <input type="number" id="stress_auto_hold_minutes" name="stress_auto_hold_minutes" min="1" max="1440" step="1"
                    value="<?php echo (int) $settings['stress_auto_hold_minutes']; ?>" required>
            </div>

            <div class="field">
                <label class="checkbox-label">
                    <input type="checkbox" id="stress_mode_enabled" name="stress_mode_enabled" value="1"
                        <?php echo ! empty($settings['stress_mode_enabled']) ? 'checked' : ''; ?>>
                    Schutzmodus dauerhaft (manuell) — Gäste von Projekten und Medien ausschließen
                </label>
                <div class="hint">
                    Unabhängig von der Last; hat Vorrang vor der Automatik. Notfall ohne Admin-UI: <code>STRESS_MODE=1</code> in <code>.env.local</code>.
                    Startseite, Login, Registrierung und Rechtstexte bleiben erreichbar.
                </div>
            </div>

            <div class="field">
                <label for="site_name">Website-Name</label>
                <input type="text" id="site_name" name="site_name" maxlength="80" required
                    value="<?php echo htmlspecialchars((string) $settings['site_name'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="hint">Erscheint u. a. im Browser-Tab, auf Laravel-Login/Passwort-Seiten und als Absendername in System-E-Mails.</div>
            </div>

            <h2>Medien-Uploads</h2>

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
                <?php admin_reauth_primary_button('site-settings-form', 'admin-reauth-settings', $adminReauthFresh, 'Speichern', 'action', 'save_site_settings'); ?>
            </div>
        </form>
    </div>

    <?php admin_reauth_confirm_dialog('admin-reauth-settings', 'site-settings-form', $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft, 'Speichern', 'action', 'save_site_settings'); ?>
<?php }, $allowedRoles);
