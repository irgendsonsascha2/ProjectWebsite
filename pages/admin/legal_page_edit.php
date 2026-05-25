<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/site_pages.php';
require_once __DIR__ . '/../../includes/admin_reauth.php';

use MongoDB\BSON\UTCDateTime;

$allowedRoles = ['admin', 'content_manager'];
$labels = [
    'impressum' => 'Impressum',
    'datenschutz' => 'Datenschutz',
    'nutzungsbedingungen' => 'Nutzungsbedingungen',
];

$pageKey = trim((string) ($_GET['key'] ?? ''));
if (!site_page_is_legal_key($pageKey)) {
    die('<h1>Unbekannte Seite</h1><p><a href="index.php">Zum Admin-Dashboard</a></p>');
}

$notice = '';
$error = '';
$adminReauthFresh = admin_reauth_is_fresh();
$adminReauthNeeds2fa = admin_reauth_user_has_2fa();
$reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;
$defaults = site_page_legal_defaults()[$pageKey];
$page = site_page_load_legal($pageKey, $db);

if (isset($_POST['action']) && $_POST['action'] === 'save_legal_page') {
    $reauth = admin_reauth_require_fresh_or_post();
    if (! $reauth['ok']) {
        $error = $reauth['error'];
    } else {
    $adminReauthFresh = admin_reauth_is_fresh();
    $reauthMinutesLeft = $adminReauthFresh ? (int) ceil(admin_reauth_seconds_remaining() / 60) : 0;

    $postedKey = trim((string) ($_POST['page_key'] ?? ''));
    if ($postedKey !== $pageKey) {
        $error = 'Ungültige Seiten-ID.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $error = 'Bitte einen Seitentitel angeben.';
        } else {
            $rawSections = $_POST['sections'] ?? [];
            $sections = [];
            if (is_array($rawSections)) {
                foreach ($rawSections as $section) {
                    if (!is_array($section)) {
                        continue;
                    }
                    $sections[] = [
                        'heading' => trim((string) ($section['heading'] ?? '')),
                        'body' => trim((string) ($section['body'] ?? '')),
                    ];
                }
            }
            $sections = site_page_normalize_sections($sections);

            $set = [
                'page_kind' => 'legal',
                'title' => $title,
                'intro' => trim((string) ($_POST['intro'] ?? '')),
                'provider_name' => trim((string) ($_POST['provider_name'] ?? '')),
                'provider_address' => trim((string) ($_POST['provider_address'] ?? '')),
                'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
                'sections' => $sections,
                'updated_at' => new UTCDateTime(),
                'updated_by' => (string) ($_SESSION['user_id'] ?? ''),
            ];

            try {
                $db->site_pages->updateOne(
                    ['_id' => $pageKey],
                    [
                        '$set' => $set,
                        '$setOnInsert' => ['created_at' => new UTCDateTime()],
                    ],
                    ['upsert' => true]
                );
                $notice = ($labels[$pageKey] ?? $pageKey) . ' gespeichert.';
                $page = site_page_load_legal($pageKey, $db);
            } catch (Exception $e) {
                $error = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }
    }
}

$navKey = 'legal_' . $pageKey;
$pageLabel = $labels[$pageKey] ?? $pageKey;

admin_render_page($pageLabel, $navKey, function () use ($notice, $error, $pageKey, $pageLabel, $page, $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft) {
    $sections = site_page_normalize_sections($page['sections'] ?? []);
    if ($sections === []) {
        $sections = [['heading' => '', 'body' => '']];
    }
    ?>
    <div class="page-header">
        <h1><?php echo htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php echo admin_reauth_banner_html($adminReauthFresh, $reauthMinutesLeft); ?>

    <div class="admin-card admin-card--spaced">
        <h2>Inhalt bearbeiten</h2>
        <form method="POST" id="legal-page-form" data-legal-page-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_legal_page">
            <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="title">Seitentitel (H1)</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars((string) $page['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="field">
                <label for="intro">Einleitung / Hinweis (optional)</label>
                <textarea id="intro" name="intro" rows="3"><?php echo htmlspecialchars((string) $page['intro'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <fieldset class="admin-fieldset">
                <legend>Diensteanbieter &amp; Kontakt</legend>
                <p class="hint">Für das Impressum ausfüllen. Auf Datenschutz/Nutzungsbedingungen werden leere Felder ausgeblendet.</p>

                <div class="field">
                    <label for="provider_name">Name</label>
                    <input type="text" id="provider_name" name="provider_name" value="<?php echo htmlspecialchars((string) $page['provider_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field">
                    <label for="provider_address">Anschrift</label>
                    <textarea id="provider_address" name="provider_address" rows="2"><?php echo htmlspecialchars((string) $page['provider_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="field">
                    <label for="contact_email">E-Mail</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars((string) $page['contact_email'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </fieldset>

            <div class="admin-card admin-card--spaced">
                <h2>Abschnitte</h2>
                <p class="hint">Zeilen mit <code>- </code> am Anfang werden als Aufzählung dargestellt. Leere Abschnitte werden ignoriert.</p>
                <div id="legal-sections">
                    <?php foreach ($sections as $index => $section): ?>
                        <div class="legal-section-block" data-section>
                            <div class="field">
                                <label>Überschrift (H2)</label>
                                <input type="text" name="sections[<?php echo (int) $index; ?>][heading]" value="<?php echo htmlspecialchars((string) ($section['heading'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="field">
                                <label>Text</label>
                                <textarea name="sections[<?php echo (int) $index; ?>][body]" rows="6"><?php echo htmlspecialchars((string) ($section['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="button" class="button-secondary" data-remove-section>Abschnitt entfernen</button>
                            <hr>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button-secondary" id="add-legal-section">Abschnitt hinzufügen</button>
            </div>

            <div class="actions">
                <?php admin_reauth_primary_button('legal-page-form', 'admin-reauth-legal-page', $adminReauthFresh, 'Speichern', 'action', 'save_legal_page'); ?>
            </div>
        </form>
    </div>

    <?php admin_reauth_confirm_dialog('admin-reauth-legal-page', 'legal-page-form', $adminReauthFresh, $adminReauthNeeds2fa, $reauthMinutesLeft, 'Speichern', 'action', 'save_legal_page'); ?>

    <template id="legal-section-template">
        <div class="legal-section-block" data-section>
            <div class="field">
                <label>Überschrift (H2)</label>
                <input type="text" data-section-heading>
            </div>
            <div class="field">
                <label>Text</label>
                <textarea rows="6" data-section-body></textarea>
            </div>
            <button type="button" class="button-secondary" data-remove-section>Abschnitt entfernen</button>
            <hr>
        </div>
    </template>

<?php }, $allowedRoles, ['admin-legal-sections.js']); ?>
