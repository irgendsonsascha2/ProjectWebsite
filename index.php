<?php

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/vite_assets.php';

// Welchen Inhalt sollen wir zeigen? Standard ist die Startseite (home)
$page = isset($_GET['page']) ? (string) $_GET['page'] : 'home';
if ($page === '') {
    $page = 'home';
}
$safe_page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);
if ($safe_page === '') {
    $safe_page = 'home';
}
$isAjax = request_is_ajax();

if ($isAjax) {
    $file_path = "pages/" . $safe_page . ".php";
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        include 'pages/404.php';
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mein Portfolio</title>
    <?php echo csrf_meta_script(); ?>
    <?php
        $asset = static function (string $path): string {
            $full = __DIR__ . '/' . ltrim($path, '/');
            $v = is_file($full) ? (string) filemtime($full) : (string) time();
            return htmlspecialchars($path . '?v=' . $v, ENT_QUOTES, 'UTF-8');
        };
    ?>
    <script src="<?php echo $asset('js/theme-bootstrap.js'); ?>"></script>

    <!-- ESC-Back: bewusst früh laden (Firefox/Safari robust) -->
    <script src="<?php echo $asset('js/esc-back.js'); ?>"></script>

    <?php
        // React/Vite: Standard = gebautes react-dist/; HMR: .env VITE_HMR=1 + VITE_DEV_SERVER_URL
        vite_react_assets('src/main.tsx');
    ?>
</head>

<body class="<?php echo $safe_page === 'home' ? 'page-is-home' : ''; ?>">

    <nav class="top-nav">
        <div class="nav-spacer" aria-hidden="true"></div>
        <div class="nav-main">
            <a href="index.php">Start</a>
            <a href="index.php?page=project_grid">Projekte</a>
            <?php
                $navRole = (string) ($_SESSION['role'] ?? '');
                $showAdminLink = in_array($navRole, ['admin', 'content_manager'], true);
            ?>
            <?php if ($showAdminLink): ?>
                <a href="pages/admin/index.php" style="color: red;">Admin</a>
            <?php endif; ?>

            <?php
                $navQ = '';
                if (isset($_GET['q']) && is_string($_GET['q'])) {
                    $navQ = trim($_GET['q']);
                }
            ?>
            <form class="nav-search" action="index.php" method="GET" role="search" aria-label="Projekte suchen">
                <input type="hidden" name="page" value="project_grid">
                <label class="nav-search-label" for="nav-search-input">Suche</label>
                <button type="button" class="nav-search-toggle" aria-label="Suche öffnen" aria-expanded="false" aria-controls="nav-search-input">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20l-3.5-3.5"></path>
                    </svg>
                </button>
                <input
                    id="nav-search-input"
                    class="nav-search-input"
                    type="search"
                    name="q"
                    value="<?php echo htmlspecialchars($navQ, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Projektname oder Tag…"
                    autocomplete="off"
                    inputmode="search"
                >
            </form>
        </div>
        <div class="nav-actions">
            <div id="react-theme-toggle"></div>
            <a href="index.php?page=<?php echo isset($_SESSION['user_id']) ? 'account' : 'login'; ?>" class="nav-account" aria-label="Account">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
            </a>
        </div>
    </nav>

    <main class="main-content page-<?php echo htmlspecialchars($safe_page, ENT_QUOTES, 'UTF-8'); ?>">
        <?php
        $file_path = "pages/" . $safe_page . ".php";

        if (file_exists($file_path)) {
            include $file_path;
        } else {
            include 'pages/404.php';
        }
        ?>
    </main>

    <div id="react-root" data-page="<?php echo htmlspecialchars($safe_page, ENT_QUOTES, 'UTF-8'); ?>"></div>

    <?php
        $isProjectArea = in_array($safe_page, ['project_grid', 'project_detail', 'create_project', 'edit_project'], true);
        $isHome = $safe_page === 'home';
        $navRoleBottom = (string) ($_SESSION['role'] ?? '');
        $isAdmin = in_array($navRoleBottom, ['admin', 'content_manager'], true);
    ?>
    <nav class="bottom-nav" aria-label="Bottom Navigation">
        <a
            class="bottom-nav__item <?php echo $isHome ? 'is-active' : ''; ?>"
            href="index.php"
            <?php echo $isHome ? 'aria-current="page"' : ''; ?>
        >
            <span class="bottom-nav__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11.5 12 4l9 7.5" />
                    <path d="M5 10.5V20h14v-9.5" />
                </svg>
            </span>
            <span class="bottom-nav__label">Start</span>
        </a>

        <a
            class="bottom-nav__item <?php echo $isProjectArea ? 'is-active' : ''; ?>"
            href="index.php?page=project_grid"
            <?php echo $isProjectArea ? 'aria-current="page"' : ''; ?>
        >
            <span class="bottom-nav__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h7v7H4z" />
                    <path d="M13 4h7v7h-7z" />
                    <path d="M4 13h7v7H4z" />
                    <path d="M13 13h7v7h-7z" />
                </svg>
            </span>
            <span class="bottom-nav__label">Projekte</span>
        </a>

        <button class="bottom-nav__item bottom-nav__item--button" type="button" data-nav-search-toggle aria-label="Suche">
            <span class="bottom-nav__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M20 20l-3.5-3.5"></path>
                </svg>
            </span>
            <span class="bottom-nav__label">Suche</span>
        </button>

        <?php if ($isAdmin): ?>
            <a class="bottom-nav__item" href="pages/admin/index.php">
                <span class="bottom-nav__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z" />
                        <path d="M12 7v6" />
                        <path d="M9.5 10H14.5" />
                    </svg>
                </span>
                <span class="bottom-nav__label">Admin</span>
            </a>
        <?php endif; ?>
    </nav>

    <footer class="site-footer" role="contentinfo">
        <div class="site-footer__inner">
            <a href="index.php?page=impressum">Impressum</a>
            <span aria-hidden="true">·</span>
            <a href="index.php?page=datenschutz">Datenschutz</a>
            <span aria-hidden="true">·</span>
            <a href="index.php?page=nutzungsbedingungen">Nutzungsbedingungen</a>
        </div>
    </footer>

    <script src="<?php echo $asset('js/csrf-forms.js'); ?>"></script>
    <?php
        $pageScripts = [
            'login' => ['login-2fa-alt.js'],
            'register' => ['dialog-focus.js', 'register-request-dialog.js'],
            'project_grid' => ['project-grid-delete.js', 'project-grid-tilt.js'],
            'project_detail' => ['project-detail.js'],
            'create_project' => ['project-media-manager.js'],
            'edit_project' => ['project-media-manager.js'],
        ];
        foreach ($pageScripts[$safe_page] ?? [] as $scriptFile) {
            echo '    <script src="'.$asset('js/'.$scriptFile).'" defer></script>'."\n";
        }
    ?>
    <script src="<?php echo $asset('js/media-skeleton.js'); ?>" defer></script>
    <script src="<?php echo $asset('js/video-hover-preview.js'); ?>" defer></script>
    <script src="<?php echo $asset('js/nav-search.js'); ?>" defer></script>
</body>

</html>
