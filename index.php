<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require __DIR__ . '/includes/bootstrap.php';

// Welchen Inhalt sollen wir zeigen? Standard ist 'grid'
$page = $_GET['page'] ?? 'project_grid';
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Mein Portfolio</title>
    <link rel="stylesheet" href="style/style.css">
</head>

<body>

    <nav>
        <a href="index.php?page=project_grid">Home</a>
        <a href="index.php?page=account">Account</a>
        <a href="index.php?page=acc">Error</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="pages/admin/index.php" style="color: red;">Admin</a>
        <?php endif; ?>
    </nav>

    <main class="main-content page-<?php echo htmlspecialchars($page); ?>">
        <?php
        // 1. Sicherheits-Check: Nur Buchstaben und Zahlen erlauben
        // Verhindert, dass jemand Pfade wie ../../etc/passwd eingibt
        $safe_page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

        // 2. Pfad zur Datei zusammenbauen
        $file_path = "pages/" . $safe_page . ".php";

        // 3. Prüfen, ob die Datei existiert und den Inhalt laden
       if (file_exists($file_path)) {
            include $file_path;
        } else {
            // 4. Fallback zur 404 Seite
            include 'pages/404.php';
        }
        ?>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Portfolio</p>
    </footer>

</body>

</html>
