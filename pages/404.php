<div class="page-404">
    <div class="error-wrapper">
        <div class="error-content">
            <div class="error-number">404</div>
            <h1 class="error-headline">Seite nicht gefunden</h1>
            <p class="error-text">
                Die von dir gesuchte Unterseite
                <code class="requested-page">"<?php echo htmlspecialchars($page); ?>"</code>
                konnte leider nicht gefunden werden.
            </p>

            <div class="action-area">
                <a href="index.php" class="btn-back">
                    Zur Startseite
                </a>
            </div>

            <?php if (isset($safe_page)): ?>
                <div class="debug-info">
                    <span class="status-badge">✅ System-Check aktiv</span>
                    <p>Die Navigation für <strong><?php echo $safe_page; ?></strong> wurde korrekt abgefangen.</p>
                    <p class="server-timestamp">Serverzeit: <?php echo date("H:i:s"); ?> Uhr</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>