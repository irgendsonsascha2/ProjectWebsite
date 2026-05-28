<?php

if (!class_exists('MongoDB\Client')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (!function_exists('load_simple_env_file')) {
    function load_simple_env_file($path) {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ($key === '') {
                continue;
            }
            // Allow quoted values.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    // Minimal unescape for double-quoted values (so we can safely persist secrets with quotes/backslashes).
                    // - \" -> "
                    // - \\ -> \
                    if ($first === '"') {
                        $value = str_replace(['\\\\', '\\"'], ['\\', '"'], $value);
                    }
                }
            }
            // Don't override real environment.
            $existing = getenv($key);
            if ($existing !== false && trim((string)$existing) !== '') {
                continue;
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

// Optional local env file support so the running PHP server process
// uses the same DB settings as expected, without relying on shell-exported vars.
if (!function_exists('bootstrap_local_env')) {
    function bootstrap_local_env() {
        static $done = false;
        if ($done) return;
        $done = true;

        $root = realpath(__DIR__ . '/..');
        if (!$root) return;

        // Prefer .env.local, then .env (both are optional, and should not be committed).
        load_simple_env_file($root . '/.env.local');
        load_simple_env_file($root . '/.env');
    }
}

bootstrap_local_env();

if (!function_exists('mask_mongo_uri')) {
    function mask_mongo_uri($uri) {
        if (!is_string($uri) || $uri === '') return '';
        // Mask password in mongodb://user:pass@host...
        return preg_replace('/(mongodb(?:\\+srv)?:\\/\\/[^:\\/]+:)([^@]+)(@)/', '$1***$3', $uri);
    }
}

if (!function_exists('mongo_uri_with_password')) {
    function mongo_uri_with_password($uri, $password) {
        if (!is_string($uri) || $uri === '') {
            return $uri;
        }
        if (!is_string($password)) {
            $password = '';
        }
        $password = trim($password);
        if ($password === '') {
            return $uri;
        }

        // Important: don't use preg_replace replacement strings with untrusted passwords.
        // Characters like $ or \ are interpreted by preg_replace and can corrupt the URI.
        // Use callbacks instead.

        // Replace password in mongodb://user:OLD@...
        $updated = preg_replace_callback(
            '/^(mongodb(?:\\+srv)?:\\/\\/[^:\\/]+:)([^@]*)(@.*)$/',
            function ($m) use ($password) {
                return $m[1] . $password . $m[3];
            },
            $uri,
            1,
            $count
        );
        if (($count ?? 0) === 1 && is_string($updated)) {
            return $updated;
        }

        // If URI contains user but no password: mongodb://user@...
        $updated = preg_replace_callback(
            '/^(mongodb(?:\\+srv)?:\\/\\/[^@:\\/]+)(@.*)$/',
            function ($m) use ($password) {
                return $m[1] . ':' . $password . $m[2];
            },
            $uri,
            1,
            $count2
        );
        if (($count2 ?? 0) === 1 && is_string($updated)) {
            return $updated;
        }

        return $uri;
    }
}

if (!function_exists('mongo_default_uri')) {
    function mongo_default_uri(string $user): string
    {
        $dbName = getenv('APP_DB_NAME');
        if ($dbName === false || trim((string) $dbName) === '') {
            $dbName = 'portfolio_db';
        }

        return 'mongodb://'.$user.':0@localhost:27017/'.$dbName.'?authSource='.$dbName;
    }
}

if (!function_exists('mongo_resolve_uri')) {
    function mongo_resolve_uri(string $envKey, string $defaultUser): string
    {
        $uri = getenv($envKey);
        if ($uri !== false && trim((string) $uri) !== '') {
            return (string) $uri;
        }

        if (!function_exists('app_allow_dev_db_defaults') || !app_allow_dev_db_defaults()) {
            throw new RuntimeException(
                $envKey.' is not set. Create .env.local in the project root (see README). '
                .'For local dev only you may set APP_ALLOW_DEV_DB_DEFAULTS=1.'
            );
        }

        return mongo_default_uri($defaultUser);
    }
}

if (!function_exists('mongo_config')) {
    function mongo_config() {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        if (!function_exists('app_allow_dev_db_defaults')) {
            require_once __DIR__ . '/app_env.php';
        }

        $appUri = mongo_resolve_uri('APP_DB_URI', 'viewer');
        $viewerUri = mongo_resolve_uri('VIEWER_DB_URI', 'viewer');
        $communityUri = mongo_resolve_uri('COMMUNITY_DB_URI', 'community_member');
        $contentManagerUri = mongo_resolve_uri('CONTENT_MANAGER_DB_URI', 'content_manager');
        $adminUri = mongo_resolve_uri('ADMIN_DB_URI', 'admin');

        $dbName = getenv('APP_DB_NAME');
        if ($dbName === false || trim($dbName) === '') {
            $dbName = 'portfolio_db';
        }

        $config = [
            'app_uri' => $appUri,
            'viewer_uri' => $viewerUri,
            'community_member_uri' => $communityUri,
            'content_manager_uri' => $contentManagerUri,
            'admin_uri' => $adminUri,
            'db_name' => $dbName
        ];

        return $config;
    }
}

if (!function_exists('normalize_app_role')) {
    function normalize_app_role($role) {
        $role = is_string($role) ? trim(strtolower($role)) : '';
        $allowedRoles = ['viewer', 'community_member', 'content_manager', 'admin'];

        if (!in_array($role, $allowedRoles, true)) {
            return 'viewer';
        }

        return $role;
    }
}

if (!function_exists('create_mongo_connection')) {
    function create_mongo_connection($uri) {
        $config = mongo_config();
        $client = new \MongoDB\Client($uri);
        $dbName = $config['db_name'];

        return [$client, $client->$dbName];
    }
}

if (!function_exists('mongo_uri_for_role')) {
    function mongo_uri_for_role($role) {
        $config = mongo_config();
        $role = normalize_app_role($role);

        $map = [
            'viewer' => $config['viewer_uri'],
            'community_member' => $config['community_member_uri'],
            'content_manager' => $config['content_manager_uri'],
            'admin' => $config['admin_uri']
        ];

        return $map[$role] ?? $config['viewer_uri'];
    }
}

if (!function_exists('get_app_mongo_connection')) {
    function get_app_mongo_connection() {
        static $connection = null;

        if ($connection !== null) {
            return $connection;
        }

        $config = mongo_config();
        $connection = create_mongo_connection($config['app_uri']);

        return $connection;
    }
}

if (!function_exists('get_role_mongo_connection')) {
    function get_role_mongo_connection($role) {
        static $connections = [];

        $role = normalize_app_role($role);
        if (isset($connections[$role])) {
            return $connections[$role];
        }

        $connections[$role] = create_mongo_connection(mongo_uri_for_role($role));

        return $connections[$role];
    }
}

if (!function_exists('get_request_mongo_connection')) {
    function get_request_mongo_connection($role = null) {
        return get_role_mongo_connection($role ?: 'viewer');
    }
}

if (!function_exists('get_admin_mongo_connection')) {
    function get_admin_mongo_connection() {
        return get_role_mongo_connection('admin');
    }
}
