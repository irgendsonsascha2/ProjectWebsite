<?php

require_once __DIR__.'/_layout.php';
require_once __DIR__.'/../../includes/deploy_status.php';

$checks = deploy_status_collect_checks();
$failCount = 0;
$warnCount = 0;
foreach ($checks as $c) {
    if ($c['status'] === 'fail') {
        $failCount++;
    } elseif ($c['status'] === 'warn') {
        $warnCount++;
    }
}

admin_render_page('Deploy-Status', 'deploy_status', function () use ($checks, $failCount, $warnCount) { ?>
    <div class="page-header">
        <h1>Deploy-Status</h1>
    </div>

    <p class="muted">Read-only-Checkliste für Laufzeit und Deployment. Es werden keine Updates ausgeführt.</p>

    <?php if ($failCount > 0): ?>
        <p class="alert alert--error"><?php echo (int) $failCount; ?> Prüfung(en) fehlgeschlagen.</p>
    <?php elseif ($warnCount > 0): ?>
        <p class="alert"><?php echo (int) $warnCount; ?> Hinweis/Hinweise — bitte prüfen.</p>
    <?php else: ?>
        <p class="alert">Alle Prüfungen OK.</p>
    <?php endif; ?>

    <div class="deploy-check-list">
        <?php foreach ($checks as $check): ?>
            <div class="admin-card deploy-check <?php echo htmlspecialchars(deploy_status_status_class($check['status']), ENT_QUOTES, 'UTF-8'); ?>">
                <h3>
                    <?php echo htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="deploy-check__status"><?php echo htmlspecialchars(deploy_status_status_label($check['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                </h3>
                <p class="deploy-check__detail"><?php echo htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($check['hint'] !== ''): ?>
                    <p class="muted"><?php echo htmlspecialchars($check['hint'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if ($check['id'] === 'migration_tracking' && $check['status'] !== 'ok'): ?>
                    <p><a href="db_scripts.php">→ DB-Skripte</a></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="admin-card admin-card--spaced">
        <h2>Code-Update (manuell)</h2>
        <p class="muted">Auf dem Server per SSH oder lokal im Projektroot — nicht über diese Web-Oberfläche.</p>
        <div class="code-block"><pre>git pull
composer install --no-dev
cd frontend &amp;&amp; npm ci &amp;&amp; npm run build
cd laravel &amp;&amp; composer install --no-dev &amp;&amp; php artisan config:cache</pre></div>
        <p class="muted">Danach: diese Seite und <a href="db_scripts.php">DB-Skripte</a> prüfen; ausstehende Migrationen dort ausführen. Checkliste: <code>docs/deployment.md</code>.</p>
    </div>
<?php });
