<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require __DIR__ . '/includes/bootstrap.php';

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
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="style/skeleton.css">
    <?php if ($safe_page === 'home'): ?>
    <link rel="stylesheet" href="style/home.css">
    <?php endif; ?>
    <?php if ($safe_page === 'account'): ?>
    <link rel="stylesheet" href="style/account.css">
    <?php endif; ?>
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
            <button type="button" class="nav-theme" id="theme-toggle" aria-label="Hell- oder Dunkelmodus umschalten" title="Darstellung">
                <svg class="nav-theme-icon nav-theme-icon--moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
                <svg class="nav-theme-icon nav-theme-icon--sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                </svg>
            </button>
            <a href="index.php?page=account" class="nav-account" aria-label="Account">
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

    <script src="js/media-skeleton.js" defer></script>
    <script src="js/theme-toggle.js"></script>
</body>

</html>
