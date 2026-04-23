<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

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
$isAjax = (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['xmlhttprequest', 'fetch'], true));

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
    <title>Mein Portfolio</title>
    <?php
        $asset = static function (string $path): string {
            $full = __DIR__ . '/' . ltrim($path, '/');
            $v = is_file($full) ? (string) filemtime($full) : (string) time();
            return htmlspecialchars($path . '?v=' . $v, ENT_QUOTES, 'UTF-8');
        };
    ?>
    <script>
        (function () {
            try {
                var KEY = 'portfolio-theme';
                var t = localStorage.getItem(KEY);
                if (t !== 'dark' && t !== 'light') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <!-- ESC-Back: bewusst früh laden (Firefox/Safari robust) -->
    <script src="<?php echo $asset('js/esc-back.js'); ?>"></script>

    <?php
        // React/Vite Frontend Assets (liefert das Styling).
        // Dev: export VITE_DEV_SERVER_URL=http://127.0.0.1:5173
        // Prod: npm run build (frontend) erzeugt /react-dist
        vite_react_assets('src/main.tsx');
    ?>
</head>

<body class="<?php echo $safe_page === 'home' ? 'page-is-home' : ''; ?>">

    <nav>
        <div class="nav-spacer" aria-hidden="true"></div>
        <div class="nav-main">
            <a href="index.php">Start</a>
            <a href="index.php?page=project_grid">Projekte</a>
            <a href="index.php?page=impressum">Impressum</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="pages/admin/index.php" style="color: red;">Admin</a>
            <?php endif; ?>
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

    <script src="<?php echo $asset('js/media-skeleton.js'); ?>" defer></script>
</body>

</html>
