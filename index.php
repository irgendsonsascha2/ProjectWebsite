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
    <link rel="stylesheet" href="style/style.css">
</head>

<body class="<?php echo $safe_page === 'home' ? 'page-is-home' : ''; ?>">

    <nav>
        <a href="index.php">Start</a>
        <a href="index.php?page=project_grid">Projekte</a>
        <a href="index.php?page=account">Account</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="pages/admin/index.php" style="color: red;">Admin</a>
        <?php endif; ?>
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

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Portfolio</p>
    </footer>

</body>

</html>
