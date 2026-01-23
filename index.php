<?php
session_start();
require 'vendor/autoload.php';

// --- GLOBALE DATENBANKVERBINDUNG ---
use MongoDB\Client;
$client = new Client("mongodb://localhost:27017");
$db = $client->portfolio_db;

// --- GLOBALE HILFSFUNKTION FÜR RECHTE (falls nicht schon geladen) ---
if (!function_exists('can')) {
    function can($permission) {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}

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