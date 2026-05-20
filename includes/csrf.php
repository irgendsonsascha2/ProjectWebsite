<?php

if (!function_exists('csrf_ensure_session')) {
    function csrf_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('configure_session_cookie_params')) {
                configure_session_cookie_params();
            }
            session_start();
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        csrf_ensure_session();

        if (!empty($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])) {
            return $_SESSION['csrf_token'];
        }

        if (!empty($_SESSION['csrf_bridge']) && is_string($_SESSION['csrf_bridge'])) {
            $_SESSION['csrf_token'] = $_SESSION['csrf_bridge'];

            return $_SESSION['csrf_token'];
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_bridge'] = $_SESSION['csrf_token'];

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = csrf_token();

        return '<input type="hidden" name="_token" value="'
            .htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $sessionToken = csrf_token();
        $postToken = (string) ($_POST['_token'] ?? '');

        return $sessionToken !== '' && $postToken !== '' && hash_equals($sessionToken, $postToken);
    }
}

if (!function_exists('csrf_failure_redirect_url')) {
    function csrf_failure_redirect_url(): string
    {
        if (! function_exists('legacy_index_url')) {
            require_once __DIR__.'/app_env.php';
        }

        $page = (string) ($_GET['page'] ?? 'home');
        $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?: 'home';

        if (legacy_is_admin_script_request()) {
            return legacy_index_url(['page' => 'home', 'err' => 'csrf']);
        }

        return legacy_index_url(['page' => $page, 'err' => 'csrf']);
    }
}

if (!function_exists('csrf_verify_or_exit')) {
    function csrf_verify_or_exit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        if (csrf_verify()) {
            return;
        }

        if (function_exists('request_is_ajax') && request_is_ajax()) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(['ok' => false, 'error' => 'csrf', 'message' => 'Formular ungültig oder Sitzung abgelaufen.']);
            exit;
        }

        header('Location: '.csrf_failure_redirect_url());
        exit;
    }
}

if (!function_exists('csrf_meta_script')) {
    function csrf_meta_script(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $token = csrf_token();
        if ($token === '') {
            return '';
        }

        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        return '<meta name="csrf-token" content="'.$escaped.'">'."\n"
            .'<script>window.PORTFOLIO_CSRF="'.$escaped.'";</script>'."\n";
    }
}
