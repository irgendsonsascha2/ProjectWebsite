<?php

/**
 * Frische Admin-Bestätigung (Passwort + optional TOTP) für sensible Aktionen.
 */

require_once __DIR__.'/two_factor.php';

if (!function_exists('admin_reauth_ttl_seconds')) {
    function admin_reauth_ttl_seconds(): int
    {
        return 900;
    }
}

if (!function_exists('admin_reauth_is_fresh')) {
    function admin_reauth_is_fresh(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $at = (int) ($_SESSION['admin_reauth_confirmed_at'] ?? 0);
        if ($at <= 0) {
            return false;
        }

        return (time() - $at) < admin_reauth_ttl_seconds();
    }
}

if (!function_exists('admin_reauth_seconds_remaining')) {
    function admin_reauth_seconds_remaining(): int
    {
        if (! admin_reauth_is_fresh()) {
            return 0;
        }
        $at = (int) ($_SESSION['admin_reauth_confirmed_at'] ?? 0);
        $remaining = admin_reauth_ttl_seconds() - (time() - $at);

        return $remaining > 0 ? $remaining : 0;
    }
}

if (!function_exists('admin_reauth_mark_fresh')) {
    function admin_reauth_mark_fresh(): void
    {
        $_SESSION['admin_reauth_confirmed_at'] = time();
    }
}

if (!function_exists('admin_reauth_clear')) {
    function admin_reauth_clear(): void
    {
        unset($_SESSION['admin_reauth_confirmed_at']);
    }
}

if (!function_exists('admin_reauth_load_user')) {
    /**
     * @return array<string, mixed>|null
     */
    function admin_reauth_load_user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if (! in_array($role, ['admin', 'content_manager'], true)) {
            return null;
        }

        $userId = trim((string) ($_SESSION['user_id'] ?? ''));
        if ($userId === '') {
            return null;
        }

        $user = two_factor_find_user_by_id($userId);
        if ($user === null) {
            return null;
        }
        if (is_object($user)) {
            return (array) $user;
        }

        return is_array($user) ? $user : null;
    }
}

if (!function_exists('admin_reauth_user_has_2fa')) {
    function admin_reauth_user_has_2fa(): bool
    {
        $user = admin_reauth_load_user();

        return $user !== null && two_factor_user_enabled($user);
    }
}

if (!function_exists('admin_reauth_verify_password')) {
    function admin_reauth_verify_password(string $password, array $user): bool
    {
        $hash = (string) ($user['password'] ?? '');
        if ($password === '' || $hash === '') {
            return false;
        }

        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$argon2')) {
            return password_verify($password, $hash);
        }

        static $laravelReady = null;
        if ($laravelReady === null) {
            $laravelReady = is_file(__DIR__.'/../laravel/vendor/autoload.php');
        }
        if (! $laravelReady) {
            return password_verify($password, $hash);
        }

        require_once __DIR__.'/../laravel/vendor/autoload.php';
        $app = require __DIR__.'/../laravel/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        return Illuminate\Support\Facades\Hash::check($password, $hash);
    }
}

if (!function_exists('admin_reauth_confirm')) {
    /**
     * @return array{ok: bool, error: string}
     */
    function admin_reauth_confirm(string $password, string $totpCode = ''): array
    {
        $user = admin_reauth_load_user();
        if ($user === null) {
            return ['ok' => false, 'error' => 'Keine gültige Admin-Sitzung.'];
        }

        if ($password === '') {
            return ['ok' => false, 'error' => 'Passwort zur Bestätigung erforderlich.'];
        }

        if (! admin_reauth_verify_password($password, $user)) {
            return ['ok' => false, 'error' => 'Passwort falsch.'];
        }

        if (two_factor_user_enabled($user)) {
            $totpCode = trim($totpCode);
            if ($totpCode === '') {
                return ['ok' => false, 'error' => 'Authenticator-Code erforderlich (2FA aktiv).'];
            }
            $secret = (string) ($user['two_factor_totp_secret'] ?? '');
            if ($secret === '' || ! two_factor_verify_totp($secret, $totpCode)) {
                return ['ok' => false, 'error' => 'Authenticator-Code ungültig.'];
            }
        }

        admin_reauth_mark_fresh();

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('admin_reauth_confirm_from_post')) {
    /**
     * @return array{ok: bool, error: string}
     */
    function admin_reauth_confirm_from_post(): array
    {
        return admin_reauth_confirm(
            (string) ($_POST['admin_confirm_password'] ?? ''),
            (string) ($_POST['admin_totp_code'] ?? '')
        );
    }
}

if (!function_exists('admin_reauth_require_fresh_or_post')) {
    /**
     * Für POST-Aktionen: gültiges Re-Auth-Fenster oder erfolgreiche Bestätigung in diesem Request.
     *
     * @return array{ok: bool, error: string}
     */
    function admin_reauth_require_fresh_or_post(): array
    {
        if (admin_reauth_is_fresh()) {
            return ['ok' => true, 'error' => ''];
        }

        return admin_reauth_confirm_from_post();
    }
}

if (! function_exists('admin_reauth_form_fields')) {
    function admin_reauth_form_fields(bool $needs2fa): void
    {
        ?>
    <p class="muted">Zum Ausführen sensibler Admin-Aktionen: Passwort<?php echo $needs2fa ? ' und Authenticator-Code' : ''; ?> bestätigen (gültig <?php echo (int) (admin_reauth_ttl_seconds() / 60); ?> Min. nach Erfolg).</p>
    <p>
        <label>
            Dein Admin-Passwort<br>
            <input type="password" name="admin_confirm_password" autocomplete="current-password" required>
        </label>
    </p>
        <?php if ($needs2fa): ?>
    <p>
        <label>
            Authenticator-Code (6 Ziffern)<br>
            <input type="text" name="admin_totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
        </label>
    </p>
        <?php endif;
    }
}

if (! function_exists('admin_reauth_banner_html')) {
    function admin_reauth_banner_html(bool $fresh, int $minutesLeft): string
    {
        if (! $fresh) {
            return '';
        }

        return '<p class="alert">Admin-Bestätigung aktiv (noch ca. '
            .max(1, $minutesLeft)
            .' Min.) — sensible Aktionen ohne erneute Passwort-Eingabe.</p>';
    }
}

if (! function_exists('admin_reauth_form_fields_for_form')) {
    /**
     * Passwort/TOTP außerhalb des Hauptformulars — per form-Attribut beim Absenden mitgeschickt.
     */
    function admin_reauth_form_fields_for_form(string $formId, bool $needs2fa): void
    {
        $fid = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');
        ?>
    <p class="muted">Passwort<?php echo $needs2fa ? ' und Authenticator-Code' : ''; ?> zur Bestätigung (gültig <?php echo (int) (admin_reauth_ttl_seconds() / 60); ?> Min. nach Erfolg).</p>
    <p>
        <label>
            Dein Admin-Passwort<br>
            <input type="password" name="admin_confirm_password" form="<?php echo $fid; ?>" autocomplete="current-password" required>
        </label>
    </p>
        <?php if ($needs2fa): ?>
    <p>
        <label>
            Authenticator-Code (6 Ziffern)<br>
            <input type="text" name="admin_totp_code" form="<?php echo $fid; ?>" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
        </label>
    </p>
        <?php endif;
    }
}

if (! function_exists('admin_reauth_dialog_body')) {
    /**
     * Passwort/TOTP-Felder oder Hinweis auf aktives Re-Auth-Fenster (für Admin-Dialoge).
     */
    function admin_reauth_dialog_body(bool $fresh, bool $needs2fa, int $minutesLeft, ?string $attachFormId = null): void
    {
        if ($fresh) {
            echo '<p class="muted">Admin-Bestätigung aktiv (noch ca. '
                .(int) max(1, $minutesLeft)
                .' Min.) — Passwort nicht erneut nötig.</p>';

            return;
        }

        if ($attachFormId !== null && $attachFormId !== '') {
            admin_reauth_form_fields_for_form($attachFormId, $needs2fa);

            return;
        }

        admin_reauth_form_fields($needs2fa);
    }
}

if (! function_exists('admin_reauth_confirm_dialog')) {
    /**
     * Bestätigungs-Dialog: Passwort nur im Popup, Submit sendet das Hauptformular (form-Attribut).
     */
    function admin_reauth_confirm_dialog(
        string $dialogId,
        string $formId,
        bool $fresh,
        bool $needs2fa,
        int $minutesLeft,
        string $submitLabel = 'Bestätigen und fortfahren',
        ?string $submitName = null,
        ?string $submitValue = null
    ): void {
        $dialogIdEsc = htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8');
        $formIdEsc = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');
        ?>
    <dialog id="<?php echo $dialogIdEsc; ?>">
        <div class="dialog-card">
            <div class="dialog-header">
                <h2>Admin-Bestätigung</h2>
                <button type="button" class="dialog-close" data-dialog-close aria-label="Schließen">×</button>
            </div>
            <?php admin_reauth_dialog_body($fresh, $needs2fa, $minutesLeft, $formId); ?>
            <?php if (! $fresh): ?>
            <p>
                <button type="submit" form="<?php echo $formIdEsc; ?>" class="button-primary"
                    <?php if ($submitName !== null && $submitName !== ''): ?>
                        name="<?php echo htmlspecialchars($submitName, ENT_QUOTES, 'UTF-8'); ?>"
                        value="<?php echo htmlspecialchars($submitValue ?? '1', ENT_QUOTES, 'UTF-8'); ?>"
                    <?php endif; ?>
                ><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
            </p>
            <?php endif; ?>
        </div>
    </dialog>
        <?php
    }
}

if (! function_exists('admin_reauth_primary_button')) {
    /**
     * Speichern/Aktion: direkt submit wenn Re-Auth frisch, sonst Dialog öffnen.
     */
    function admin_reauth_primary_button(
        string $formId,
        string $dialogId,
        bool $fresh,
        string $label,
        ?string $submitName = null,
        ?string $submitValue = null
    ): void {
        if ($fresh) {
            ?>
            <button type="submit" class="button-primary"
                <?php if ($submitName !== null && $submitName !== ''): ?>
                    name="<?php echo htmlspecialchars($submitName, ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?php echo htmlspecialchars($submitValue ?? '1', ENT_QUOTES, 'UTF-8'); ?>"
                <?php endif; ?>
            ><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
            <?php

            return;
        }
        ?>
        <button type="button" class="button-primary" data-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <?php
    }
}
