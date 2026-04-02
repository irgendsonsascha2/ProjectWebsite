<?php

if (!class_exists('MongoDB\Client')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (!function_exists('mongo_config')) {
    function mongo_config() {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $appUri = getenv('APP_DB_URI');
        if ($appUri === false || trim($appUri) === '') {
            $appUri = 'mongodb://viewer:0@localhost:27017/portfolio_db?authSource=portfolio_db';
        }

        $viewerUri = getenv('VIEWER_DB_URI');
        if ($viewerUri === false || trim($viewerUri) === '') {
            $viewerUri = 'mongodb://viewer:0@localhost:27017/portfolio_db?authSource=portfolio_db';
        }

        $communityUri = getenv('COMMUNITY_DB_URI');
        if ($communityUri === false || trim($communityUri) === '') {
            $communityUri = 'mongodb://community_member:0@localhost:27017/portfolio_db?authSource=portfolio_db';
        }

        $contentManagerUri = getenv('CONTENT_MANAGER_DB_URI');
        if ($contentManagerUri === false || trim($contentManagerUri) === '') {
            $contentManagerUri = 'mongodb://content_manager:0@localhost:27017/portfolio_db?authSource=portfolio_db';
        }

        $adminUri = getenv('ADMIN_DB_URI');
        if ($adminUri === false || trim($adminUri) === '') {
            $adminUri = 'mongodb://admin:0@localhost:27017/portfolio_db?authSource=portfolio_db';
        }

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
