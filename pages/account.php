<?php
// Session-Konfiguration für "Dauerhaft angemeldet bleiben" (30 Tage)
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);
session_start();

require_once 'vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;

$client = new MongoDB\Client("mongodb://localhost:27017");
$db = $client->portfolio_db;
$message = "";

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: account.php");
    exit();
}

// --- HILFSFUNKTION FÜR RECHTE ---
function can($permission) {
    return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}

// --- LOGIK: LOGIN ---
if (isset($_POST['login'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $user = $db->users->findOne(['email' => $email]);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = (string)$user['_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        $roleData = $db->roles_config->findOne(['role' => $user['role']]);
        $_SESSION['permissions'] = iterator_to_array($roleData['permissions']);
        
        $message = "✅ Erfolgreich angemeldet!";
    } else {
        $message = "❌ Fehler: E-Mail oder Passwort falsch.";
    }
}

// --- LOGIK: REGISTRIERUNG MIT EINMAL-CODE ---
if (isset($_POST['register'])) {
    $code = trim($_POST['reg_code']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $validCode = $db->registration_codes->findOne(['code' => $code, 'is_used' => false]);

    if (!$validCode) {
        $message = "❌ Fehler: Der Code ist ungültig oder bereits verbraucht.";
    } else {
        if ($db->users->findOne(['email' => $email])) {
            $message = "❌ Fehler: Diese E-Mail wird bereits verwendet.";
        } else {
            $db->users->insertOne([
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $validCode['role'],
                'created_at' => new UTCDateTime()
            ]);

            $db->registration_codes->updateOne(
                ['_id' => $validCode['_id']],
                ['$set' => ['is_used' => true]]
            );

            $message = "✅ Konto erstellt! Du kannst dich jetzt einloggen.";
        }
    }
}

// --- LOGIK: CODE GENERIEREN (Nur Admin) ---
if (isset($_POST['generate_code']) && can('generate_codes')) {
    $newCode = strtoupper(bin2hex(random_bytes(4))); 
    $targetRole = $_POST['target_role'];

    $db->registration_codes->insertOne([
        'code' => $newCode,
        'role' => $targetRole,
        'is_used' => false,
        'created_at' => new UTCDateTime()
    ]);
    $message = "✅ Neuer Code generiert: <b>$newCode</b>";
}

// Prüfung, ob ein Registrierungs-Link geklickt wurde
$prefilledCode = isset($_GET['reg_token']) ? htmlspecialchars($_GET['reg_token']) : '';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { padding: 10px; background: #e3f2fd; border-left: 5px solid #2196f3; margin-bottom: 20px; }
        .auth-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        input, select, button { width: 100%; padding: 10px; margin: 5px 0; box-sizing: border-box; }
        button { background: #333; color: white; border: none; cursor: pointer; }
        button:hover { background: #555; }
        .admin-panel { background: #fff3e0; padding: 15px; border-radius: 5px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-size: 0.9em; }
        code { background: #eee; padding: 2px 4px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Account System</h1>
    
    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="auth-grid">
            <section>
                <h2>Anmelden</h2>
                <form method="POST">
                    <input type="email" name="email" placeholder="E-Mail" required>
                    <input type="password" name="password" placeholder="Passwort" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </section>

            <section>
                <h2>Registrieren</h2>
                <form method="POST">
                    <input type="text" name="reg_code" placeholder="Einmal-Code" value="<?php echo $prefilledCode; ?>" required>
                    <input type="email" name="email" placeholder="E-Mail Adresse" required>
                    <input type="password" name="password" placeholder="Passwort wählen" required>
                    <button type="submit" name="register">Konto erstellen</button>
                </form>
            </section>
        </div>

    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p>Eingeloggt als: <strong><?php echo $_SESSION['email']; ?></strong> (Rolle: <?php echo $_SESSION['role']; ?>)</p>
            <a href="?logout=1">Abmelden</a>
        </div>

        <hr>

        <?php if (can('generate_codes')): ?>
            <section class="admin-panel">
                <h3>Einladungscodes & Links</h3>
                <form method="POST" style="display: flex; gap: 10px;">
                    <select name="target_role" style="flex: 2;">
                        <option value="content_manager">Content Manager</option>
                        <option value="viewer">Viewer</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" name="generate_code" style="flex: 1;">Code generieren</button>
                </form>

                <table>
                    <tr>
                        <th>Rolle</th>
                        <th>Code</th>
                        <th>Direkt-Link</th>
                    </tr>
                    <?php
                    $activeCodes = $db->registration_codes->find(['is_used' => false]);
                    foreach ($activeCodes as $c): 
                        $link = "http://" . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'])[0] . "?reg_token=" . $c['code'];
                    ?>
                        <tr>
                            <td><?php echo $c['role']; ?></td>
                            <td><code><?php echo $c['code']; ?></code></td>
                            <td><input type="text" value="<?php echo $link; ?>" readonly onclick="this.select();" style="font-size: 0.8em;"></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>
        <?php endif; ?>

        <?php if (can('create_project')): ?>
            <section style="margin-top: 20px;">
                <h3>Projekt-Management</h3>
                <p>Du hast die Berechtigung, Projekte zu verwalten.</p>
                <button onclick="alert('Hier käme das Formular für Projekte hin!')">Neues Projekt hinzufügen</button>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>