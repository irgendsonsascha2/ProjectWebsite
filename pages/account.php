<?php

use MongoDB\BSON\UTCDateTime;

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header("Location: index.php?page=account"); // Weiterleitung zur account Seite durch den Router
    exit();
}

// --- LOGIK: LOGIN ---
if (isset($_POST['login'])) {
    $identifier = trim($_POST['login_id']);
    $user = $db->users->findOne([
        '$or' => [
            ['email' => $identifier],
            ['username' => strtolower($identifier)]
        ]
    ]);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = (string)$user['_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['username'] = $user['username'] ?? '';
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
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    $validCode = $db->registration_codes->findOne(['code' => $code, 'is_used' => false]);

    if (!$validCode) {
        $message = "❌ Fehler: Der Code ist ungültig oder bereits verbraucht.";
    } else {
        if (!preg_match('/^[a-z0-9._-]{3,20}$/', $username)) {
            $message = "❌ Fehler: Username ungültig (3-20 Zeichen: a-z, 0-9, . _ -).";
        } elseif ($db->users->findOne(['email' => $email])) {
            $message = "❌ Fehler: Diese E-Mail wird bereits verwendet.";
        } elseif ($db->users->findOne(['username' => $username])) {
            $message = "❌ Fehler: Dieser Username wird bereits verwendet.";
        } else {
            $db->users->insertOne([
                'email' => $email,
                'username' => $username,
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

$prefilledCode = isset($_GET['reg_token']) ? htmlspecialchars($_GET['reg_token']) : '';
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <link rel="stylesheet" href="style/account.css">
</head>

<div class="container">
    <h1>Account System</h1>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="auth-grid">
            <section>
                <h2>Anmelden</h2>
                <form method="POST" id="login-form">
                    <input type="text" id="login_id" name="login_id" placeholder="E-Mail oder Username" autocomplete="username" required>
                    <input type="password" id="login_password" name="password" placeholder="Passwort" autocomplete="current-password" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </section>

            <section>
                <h2>Registrieren</h2>
                <form method="POST" id="register-form">
                    <input type="text" id="reg_code" name="reg_code" placeholder="Einmal-Code" value="<?php echo $prefilledCode; ?>" autocomplete="one-time-code" required>
                    <input type="text" id="reg_username" name="username" placeholder="Username" autocomplete="new-username" required>
                    <small style="color:#64748b; display:block; margin-bottom:0.5rem;">3–20 Zeichen: a–z, 0–9, . _ -</small>
                    <input type="email" id="reg_email" name="email" placeholder="E-Mail Adresse" autocomplete="email" required>
                    <input type="password" id="reg_password" name="password" placeholder="Passwort wählen" autocomplete="new-password" required>
                    <button type="submit" name="register">Konto erstellen</button>
                </form>
            </section>
        </div>

    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <p>Eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['email']); ?></strong> (<?php echo htmlspecialchars($_SESSION['email']); ?>) (Rolle: <?php echo $_SESSION['role']; ?>)</p>
            <a href="index.php?page=account&logout=1">Abmelden</a>
        </div>

        <hr style="border: 0; border-top: 0.0625rem solid #eee; margin: 1.25rem 0;">

        <?php if (can('generate_codes')): ?>
            <section class="admin-panel">
                <h3>Einladungscodes & Links</h3>
                <form method="POST" style="display: flex; gap: 0.625rem; flex-wrap: wrap;">
                    <select name="target_role" style="flex: 2; min-width: 12.5rem;">
                        <option value="content_manager">Content Manager</option>
                        <option value="community_member">Community Member</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" name="generate_code" style="flex: 1; min-width: 9.375rem;">Code generieren</button>
                </form>

                <div style="overflow-x: auto;">
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
                                <td><input type="text" value="<?php echo $link; ?>" readonly onclick="this.select();" style="font-size: 0.8rem;"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    if (!loginForm || !registerForm) return;

    const loginInputs = loginForm.querySelectorAll('input');
    const registerInputs = registerForm.querySelectorAll('input');

    function clearInputs(inputs) {
        inputs.forEach((input) => {
            if (input.type === 'password' || input.type === 'text' || input.type === 'email') {
                input.value = '';
            }
        });
    }

    function setAutocomplete(inputs, value) {
        inputs.forEach((input) => {
            input.setAttribute('autocomplete', value);
        });
    }

    loginForm.addEventListener('focusin', () => {
        clearInputs(registerInputs);
        setAutocomplete(registerInputs, 'off');
        loginInputs.forEach((input) => {
            if (input.id === 'login_id') input.setAttribute('autocomplete', 'username');
            if (input.id === 'login_password') input.setAttribute('autocomplete', 'current-password');
        });
    });

    registerForm.addEventListener('focusin', () => {
        clearInputs(loginInputs);
        loginInputs.forEach((input) => {
            input.setAttribute('autocomplete', 'off');
        });
        registerInputs.forEach((input) => {
            if (input.id === 'reg_code') input.setAttribute('autocomplete', 'one-time-code');
            if (input.id === 'reg_username') input.setAttribute('autocomplete', 'new-username');
            if (input.id === 'reg_email') input.setAttribute('autocomplete', 'email');
            if (input.id === 'reg_password') input.setAttribute('autocomplete', 'new-password');
        });
    });
})();
</script>
