<?php

require_once __DIR__.'/user_db.php';
require_once __DIR__.'/user_moderation.php';

if (!function_exists('authz_is_logged_in')) {
    function authz_is_logged_in(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE
            && isset($_SESSION['user_id'])
            && (string) $_SESSION['user_id'] !== '';
    }
}

if (!function_exists('authz_email_verified_from_document')) {
    function authz_email_verified_from_document($user): bool
    {
        if (is_object($user)) {
            $user = (array) $user;
        }
        if (! is_array($user)) {
            return false;
        }

        if (! isset($user['email_verified_at']) || $user['email_verified_at'] === null) {
            return false;
        }

        $v = $user['email_verified_at'];
        if ($v instanceof MongoDB\BSON\UTCDateTime) {
            return true;
        }
        if (is_string($v) && trim($v) !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('authz_session_email_is_verified')) {
    function authz_session_email_is_verified(): bool
    {
        if (! authz_is_logged_in()) {
            return true;
        }

        if (($_SESSION['role'] ?? '') === 'admin') {
            return true;
        }

        if (! empty($_SESSION['email_verified'])) {
            return true;
        }

        $raw = $_SESSION['email_verified_at'] ?? null;
        if ($raw === null || $raw === false || $raw === '') {
            return false;
        }

        return true;
    }
}

if (!function_exists('authz_apply_user_to_session')) {
    /**
     * @param array<string, mixed>|object $user
     */
    function authz_apply_user_to_session($user, $db): void
    {
        $sessionUser = user_session_from_document($user);
        $_SESSION['user_id'] = (string) ($user['_id'] ?? '');
        $_SESSION['email'] = $sessionUser['email'];
        $_SESSION['username'] = $sessionUser['username'];
        $_SESSION['role'] = $sessionUser['role'];
        $_SESSION['email_verified'] = authz_email_verified_from_document($user);
        $_SESSION['email_verified_at'] = $user['email_verified_at'] ?? null;

        $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
        if ($roleData && isset($roleData['permissions'])) {
            $perms = $roleData['permissions'];
            $_SESSION['permissions'] = is_array($perms) ? $perms : iterator_to_array($perms);
        } else {
            $_SESSION['permissions'] = [];
        }
    }
}

if (!function_exists('authz_sync_session_from_db')) {
    function authz_sync_session_from_db(): void
    {
        if (! authz_is_logged_in()) {
            return;
        }

        try {
            require_once __DIR__.'/db.php';
            [, $adminDb] = get_admin_mongo_connection();
            $user = user_find_public_by_id($adminDb, $_SESSION['user_id']);
            if ($user === null) {
                $_SESSION = [];

                return;
            }
            if (user_moderation_is_blocked($user)) {
                user_moderation_redirect_blocked($user);
            }
            authz_apply_user_to_session($user, $adminDb);
        } catch (Throwable) {
            // Session unverändert lassen
        }
    }
}

if (!function_exists('authz_require_active_account')) {
    function authz_require_active_account(): void
    {
        authz_require_login();
        if (($_SESSION['role'] ?? '') === 'admin') {
            return;
        }
        try {
            require_once __DIR__.'/db.php';
            [, $adminDb] = get_admin_mongo_connection();
            $user = user_find_public_by_id($adminDb, $_SESSION['user_id']);
            if ($user !== null && user_moderation_is_blocked($user)) {
                user_moderation_redirect_blocked($user);
            }
        } catch (Throwable) {
            // bei DB-Fehler nicht blockieren
        }
    }
}

if (!function_exists('authz_current_page_key')) {
    function authz_current_page_key(): string
    {
        $page = (string) ($_GET['page'] ?? 'home');
        $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

        return $page !== '' ? $page : 'home';
    }
}

if (!function_exists('authz_denied_redirect')) {
    function authz_denied_redirect(string $reason = 'forbidden'): void
    {
        if (function_exists('request_is_ajax') && request_is_ajax()) {
            if (! headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'ok' => false,
                'error' => $reason,
                'message' => 'Keine Berechtigung für diese Aktion.',
            ]);
            exit;
        }

        if (! function_exists('legacy_index_url')) {
            require_once __DIR__.'/app_env.php';
        }
        header('Location: '.legacy_index_url(['page' => 'login', 'err' => $reason]));
        exit;
    }
}

if (!function_exists('authz_require_login')) {
    function authz_require_login(): void
    {
        if (! authz_is_logged_in()) {
            authz_denied_redirect('forbidden');
        }
    }
}

if (!function_exists('authz_require_can')) {
    function authz_require_can(string $permission): void
    {
        authz_require_login();
        if (! function_exists('can') || ! can($permission)) {
            authz_denied_redirect('forbidden');
        }
    }
}

if (!function_exists('authz_require_verified_email')) {
    /**
     * E-Mail-Bestätigung läuft über den Registrierungsflow (Anfrage-Link / Einladungscode),
     * nicht über Laravel /verify-email.
     */
    function authz_require_verified_email(): void
    {
        authz_require_login();
    }
}

if (!function_exists('authz_apply_email_verification_gate')) {
    /** @deprecated Kein Laravel-/verify-email-Zwang mehr; Gate ist deaktiviert. */
    function authz_apply_email_verification_gate(): void
    {
    }
}

if (!function_exists('authz_can_edit_project')) {
    function authz_can_edit_project($project): bool
    {
        if (! authz_is_logged_in()) {
            return false;
        }
        if (! function_exists('can')) {
            return false;
        }
        if (can('edit_all')) {
            return true;
        }
        if (can('edit_own') && isset($project['author_id'])) {
            return (string) $project['author_id'] === (string) $_SESSION['user_id'];
        }

        return false;
    }
}

if (!function_exists('authz_require_project_edit')) {
    function authz_require_project_edit($project): void
    {
        authz_require_verified_email();
        if (! authz_can_edit_project($project)) {
            authz_denied_redirect('forbidden');
        }
    }
}

if (!function_exists('authz_can_view_project')) {
    function authz_can_view_project($project): bool
    {
        if (! function_exists('can') || ! can('view_projects')) {
            return false;
        }
        if (($project['is_draft'] ?? false) !== true) {
            return true;
        }
        if (! authz_is_logged_in()) {
            return false;
        }

        return isset($project['author_id'])
            && (string) $project['author_id'] === (string) $_SESSION['user_id'];
    }
}

if (!function_exists('authz_require_project_view')) {
    function authz_require_project_view($project): void
    {
        if (! authz_can_view_project($project)) {
            authz_denied_redirect('forbidden');
        }
    }
}

if (!function_exists('authz_can_delete_comment')) {
    /**
     * @param array<string, mixed> $comment
     * @param string|null $currentUserId
     * @param array<int, string>|iterable $deleteRolesAllowed
     */
    function authz_can_delete_comment(array $comment, ?string $currentUserId, $deleteRolesAllowed, bool $canDeleteOthers): bool
    {
        if ($currentUserId === null || $currentUserId === '') {
            return false;
        }
        if (! function_exists('can') || ! can('comment')) {
            return false;
        }

        $canDeleteOwn = isset($comment['user_id'])
            && (string) $comment['user_id'] === (string) $currentUserId;

        if ($canDeleteOwn) {
            return true;
        }

        if (! $canDeleteOthers) {
            return false;
        }

        $authorRole = (string) ($comment['author_role'] ?? '');
        if ($authorRole === '' && isset($comment['user_id'])) {
            try {
                require_once __DIR__.'/db.php';
                [, $adminDb] = get_admin_mongo_connection();
                $author = user_find_public_by_id($adminDb, $comment['user_id']);
                if ($author !== null) {
                    $authorRole = user_session_from_document($author)['role'];
                }
            } catch (Throwable) {
                $authorRole = '';
            }
        }

        $allowed = [];
        if (is_array($deleteRolesAllowed)) {
            $allowed = $deleteRolesAllowed;
        } elseif ($deleteRolesAllowed instanceof Traversable) {
            $allowed = iterator_to_array($deleteRolesAllowed);
        }

        if ($allowed === ['*']) {
            return true;
        }

        return $authorRole !== '' && in_array($authorRole, $allowed, true);
    }
}

if (!function_exists('authz_laravel_verify_email_url')) {
    function authz_laravel_verify_email_url(): string
    {
        if (! function_exists('laravel_app_url')) {
            require_once __DIR__.'/laravel_app_url.php';
        }

        return rtrim(laravel_app_url(), '/').'/verify-email';
    }
}
