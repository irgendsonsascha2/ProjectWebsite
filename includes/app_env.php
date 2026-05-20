<?php

if (!function_exists('app_environment')) {
    function app_environment(): string
    {
        $env = getenv('APP_ENV');
        if ($env === false || trim((string) $env) === '') {
            return 'local';
        }

        return strtolower(trim((string) $env));
    }
}

if (!function_exists('app_is_production')) {
    function app_is_production(): bool
    {
        return app_environment() === 'production';
    }
}

if (!function_exists('app_is_local_request')) {
    function app_is_local_request(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return in_array($ip, ['127.0.0.1', '::1'], true);
    }
}

if (!function_exists('app_debug_enabled')) {
    /**
     * ?debug=1 nur in APP_ENV=local und von Loopback — nie in production.
     */
    function app_debug_enabled(): bool
    {
        if (!isset($_GET['debug']) || (string) $_GET['debug'] !== '1') {
            return false;
        }
        if (app_environment() !== 'local') {
            return false;
        }

        return app_is_local_request();
    }
}

if (!function_exists('app_allow_dev_db_defaults')) {
    function app_allow_dev_db_defaults(): bool
    {
        $v = getenv('APP_ALLOW_DEV_DB_DEFAULTS');
        if ($v === false) {
            return !app_is_production();
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }
}

if (!function_exists('session_cookie_is_secure')) {
    function session_cookie_is_secure(): bool
    {
        $flag = getenv('SESSION_SECURE');
        if ($flag !== false && in_array(strtolower(trim((string) $flag)), ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443';
    }
}

if (!function_exists('configure_session_cookie_params')) {
    function configure_session_cookie_params(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $params = [
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => session_cookie_is_secure(),
        ];

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params(
                $params['lifetime'],
                $params['path'].'; samesite='.$params['samesite'],
                '',
                $params['secure'],
                $params['httponly']
            );
        }
    }
}

if (!function_exists('legacy_site_base_url')) {
    /**
     * Basis-URL der klassischen PHP-Site (ohne trailing slash), z. B. http://127.0.0.1:8080
     */
    function legacy_site_base_url(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $fromEnv = getenv('LEGACY_SITE_URL');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return $cached = rtrim(trim($fromEnv), '/');
        }

        $laravelEnv = dirname(__DIR__).'/laravel/.env';
        if (is_readable($laravelEnv)) {
            foreach (file($laravelEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (preg_match('/^LEGACY_SITE_URL=(.+)$/', $line, $m)) {
                    $url = trim($m[1], " \t\n\r\0\x0B\"'");

                    if ($url !== '') {
                        return $cached = rtrim($url, '/');
                    }
                }
            }
        }

        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');

        return $cached = $scheme.'://'.$host;
    }
}

if (!function_exists('legacy_index_url')) {
    /**
     * URL zu index.php der klassischen Site — funktioniert auch von pages/admin/ aus.
     *
     * @param array<string, string> $query
     */
    function legacy_index_url(array $query = []): string
    {
        $base = legacy_site_base_url();
        $qs = $query !== [] ? '?'.http_build_query($query) : '';

        return $base.'/index.php'.$qs;
    }
}

if (!function_exists('legacy_is_admin_script_request')) {
    function legacy_is_admin_script_request(): bool
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        return str_contains($script, '/pages/admin/');
    }
}

if (!function_exists('trusted_proxy_client_ip')) {
    /**
     * X-Forwarded-For nur wenn REMOTE_ADDR in TRUSTED_PROXY_IPS (kommagetrennt).
     */
    function trusted_proxy_client_ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = getenv('TRUSTED_PROXY_IPS');
        if ($trusted === false || trim((string) $trusted) === '') {
            return $remote;
        }

        $allowed = array_map('trim', explode(',', (string) $trusted));
        if (!in_array($remote, $allowed, true)) {
            return $remote;
        }

        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            return trim(explode(',', $xff)[0]);
        }

        return $remote;
    }
}
