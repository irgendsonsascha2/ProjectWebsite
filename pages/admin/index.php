<?php
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../includes/db.php';
// Dashboard keeps only overview + metrics (DB scripts moved to db_scripts.php).

function env_is_set($key) {
    $v = getenv($key);
    return $v !== false && trim((string)$v) !== '';
}

function admin_effective_db_config_html() {
    $cfg = mongo_config();
    $rows = [
        ['APP_DB_NAME', $cfg['db_name'], env_is_set('APP_DB_NAME')],
        ['APP_DB_URI', mask_mongo_uri($cfg['app_uri']), env_is_set('APP_DB_URI')],
        ['VIEWER_DB_URI', mask_mongo_uri($cfg['viewer_uri']), env_is_set('VIEWER_DB_URI')],
        ['COMMUNITY_DB_URI', mask_mongo_uri($cfg['community_member_uri']), env_is_set('COMMUNITY_DB_URI')],
        ['CONTENT_MANAGER_DB_URI', mask_mongo_uri($cfg['content_manager_uri']), env_is_set('CONTENT_MANAGER_DB_URI')],
        ['ADMIN_DB_URI', mask_mongo_uri($cfg['admin_uri']), env_is_set('ADMIN_DB_URI')],
    ];

    $html = '<div class="admin-card"><h2>Effektive DB-Konfiguration (laufender PHP-Prozess)</h2>';
    $html .= '<p class="muted">Passwörter sind maskiert. „Env gesetzt“ heißt: die Variable ist im PHP-Prozess wirklich vorhanden (nicht nur in deiner Shell).</p>';
    $html .= '<div class="code-block"><pre>';
    foreach ($rows as $r) {
        [$name, $value, $isSet] = $r;
        $flag = $isSet ? 'yes' : 'no';
        $html .= htmlspecialchars(str_pad($name, 24)) . ' = ' . htmlspecialchars((string)$value) . '   [env: ' . $flag . ']' . "\n";
    }
    $html .= '</pre></div></div>';
    return $html;
}
// Dashboard metrics
$metrics = [
    'users' => 0,
    'projects' => 0,
    'registration_codes_open' => 0,
    'registration_requests_pending' => 0,
];
try { $metrics['users'] = (int)$db->users->countDocuments(); } catch (Exception $e) {}
try { $metrics['projects'] = (int)$db->projects->countDocuments(); } catch (Exception $e) {}
try { $metrics['registration_codes_open'] = (int)$db->registration_codes->countDocuments(['is_used' => false]); } catch (Exception $e) {}
try {
    $metrics['registration_requests_pending'] = (int)$db->registration_code_requests->countDocuments([
        'verified_at' => ['$ne' => null],
        'approved_at' => null
    ]);
} catch (Exception $e) {}

admin_render_page('Admin Dashboard', 'dashboard', function () use ($metrics) { ?>
    <div class="page-header">
        <h1>Admin Dashboard</h1>
    </div>

    <p class="muted">Eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></p>

    <?php echo admin_effective_db_config_html(); ?>

    <h2>Übersicht</h2>
    <div class="grid">
        <div class="card">
            <h3>Nutzer</h3>
            <div class="hint"><?php echo (int)$metrics['users']; ?></div>
        </div>
        <div class="card">
            <h3>Projekte</h3>
            <div class="hint"><?php echo (int)$metrics['projects']; ?></div>
        </div>
        <div class="card">
            <h3>Offene Registrierungscodes</h3>
            <div class="hint"><?php echo (int)$metrics['registration_codes_open']; ?></div>
        </div>
        <div class="card">
            <h3>Requests (verifiziert, offen)</h3>
            <div class="hint"><?php echo (int)$metrics['registration_requests_pending']; ?></div>
        </div>
    </div>
<?php });
