<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__.'/../includes/laravel_app_url.php';

// Registrierung läuft über bridge_register.php + Laravel (RegisterInvitedUser).

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

// Login läuft über bridge_auth.php + Laravel (Passwort-Hash / Mongo wie die Laravel-App).

$message = '';

if (! empty($_SESSION['register_validation_errors'])) {
    $errs = $_SESSION['register_validation_errors'];
    unset($_SESSION['register_validation_errors']);
    $parts = [];
    foreach ($errs as $msgs) {
        foreach ((array) $msgs as $m) {
            $parts[] = $m;
        }
    }
    if ($parts !== []) {
        $message = '❌ '.implode(' ', $parts);
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

// CSRF für bridge_auth.php (Login über Laravel-Backend)
if (! isset($_SESSION['user_id'])) {
    if (empty($_SESSION['csrf_bridge'])) {
        $_SESSION['csrf_bridge'] = bin2hex(random_bytes(32));
    }
}

// Rückmeldungen vom Login-Bridge
if (isset($_GET['login_err'])) {
    $message = "❌ Fehler: E-Mail oder Passwort falsch.";
}
if (isset($_GET['err']) && $_GET['err'] === 'csrf') {
    $message = "❌ Formular ungültig oder Sitzung abgelaufen — bitte erneut versuchen.";
}
if (isset($_GET['err']) && $_GET['err'] === 'handoff') {
    $message = "❌ Anmeldung nicht möglich: In laravel/.env fehlen HANDOFF_SECRET oder LEGACY_SITE_URL passt nicht zur Website-URL.";
}
if (isset($_GET['err']) && $_GET['err'] === 'throttle') {
    $w = isset($_GET['wait']) ? (int) $_GET['wait'] : 0;
    $message = $w > 0
        ? "❌ Zu viele Versuche — bitte {$w} Sekunden warten und erneut versuchen."
        : '❌ Zu viele Versuche — bitte kurz warten und erneut versuchen.';
}
if (isset($_GET['handoff_err'])) {
    $h = (string) $_GET['handoff_err'];
    if ($h === 'config') {
        $message = '❌ Handoff: HANDOFF_SECRET fehlt in laravel/.env (oder .env nicht lesbar).';
    } elseif ($h === 'expired') {
        $message = '❌ Anmelde-Link abgelaufen — bitte erneut anmelden.';
    } elseif ($h === 'sig') {
        $message = '❌ Anmelde-Link ungültig (Signatur) — bitte erneut anmelden.';
    } elseif ($h === 'user') {
        $message = '❌ Benutzer in der Datenbank nicht gefunden.';
    } else {
        $message = '❌ Anmeldung (Handoff) fehlgeschlagen.';
    }
}

$roleOptions = [];
try {
    $roleOptions = iterator_to_array($db->roles_config->find([], ['sort' => ['role' => 1]]));
} catch (Exception $e) {
    $roleOptions = [];
}
if (count($roleOptions) === 0) {
    $roleOptions = [
        ['role' => 'content_manager', 'label' => 'Content Manager'],
        ['role' => 'community_member', 'label' => 'Community Member'],
        ['role' => 'admin', 'label' => 'Admin']
    ];
}
if (!empty($roleOptions)) {
    $roleOptions = array_values(array_filter($roleOptions, function ($roleOption) {
        return isset($roleOption['role']) && $roleOption['role'] !== 'viewer';
    }));
}
?>

<div class="container">
    <h1>Account System</h1>

    <?php if ($message): ?>
        <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="auth-grid">
            <section>
                <h2>Anmelden</h2>
                <p class="field-hint" style="margin-bottom:1rem;">Hier meldest du dich mit Nutzerdaten und Passwort aus der Datenbank an (technisch dieselbe Prüfung wie bei der geschützten Login-Routine).</p>
                <p class="field-hint" style="margin-bottom:1rem;"><a href="<?php echo htmlspecialchars(laravel_app_url().'/forgot-password', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Passwort vergessen</a> (Laravel unter <?php echo htmlspecialchars(laravel_app_url(), ENT_QUOTES, 'UTF-8'); ?>)</p>
                <form method="POST" id="login-form" action="bridge_auth.php">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_bridge'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" id="login_id" name="login_id" placeholder="E-Mail oder Username" autocomplete="username" required>
                    <input type="password" id="login_password" name="password" placeholder="Passwort" autocomplete="current-password" required>
                    <button type="submit">Login</button>
                </form>
            </section>

            <section id="register-section">
                <h2>Registrieren</h2>
                <form method="POST" id="register-form" action="bridge_register.php">
                    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['csrf_bridge'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" id="reg_code" name="reg_code" placeholder="Einmal-Code" value="<?php echo $prefilledCode; ?>" autocomplete="one-time-code" required>
                    <input type="text" id="reg_username" name="username" placeholder="Username" autocomplete="new-username" required>
                    <small class="field-hint">3–20 Zeichen: a–z, 0–9, . _ -</small>
                    <input type="email" id="reg_email" name="email" placeholder="E-Mail Adresse" autocomplete="email" required>
                    <input type="password" id="reg_password" name="password" placeholder="Passwort wählen" autocomplete="new-password" required>
                    <button type="submit" name="register">Konto erstellen</button>
                </form>
            </section>
        </div>

    <?php else: ?>
        <div class="account-header">
            <p>Eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['email']); ?></strong> (<?php echo htmlspecialchars($_SESSION['email']); ?>) (Rolle: <?php echo $_SESSION['role']; ?>)</p>
            <a href="index.php?page=account&logout=1">Abmelden</a>
        </div>

        <hr class="account-divider">

        <?php if (can('generate_codes')): ?>
            <section class="admin-panel">
                <h3>Einladungscodes & Links</h3>
                <form method="POST" class="code-form">
                    <select name="target_role" class="code-select">
                        <?php foreach ($roleOptions as $roleOption): ?>
                            <?php
                                $roleKey = $roleOption['role'] ?? '';
                                $roleLabel = $roleOption['label'] ?? $roleKey;
                                if (!$roleKey) {
                                    continue;
                                }
                            ?>
                            <option value="<?php echo htmlspecialchars($roleKey); ?>"><?php echo htmlspecialchars($roleLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="generate_code" class="code-button">Code generieren</button>
                </form>

                <div class="table-wrap">
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
                                <td><input type="text" value="<?php echo $link; ?>" readonly onclick="this.select();" class="code-link-input"></td>
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
