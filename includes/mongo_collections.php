<?php

declare(strict_types=1);

if (!defined('MONGO_PROJECTS_PUBLISHED_VIEW')) {
    define('MONGO_PROJECTS_PUBLISHED_VIEW', 'projects_published');
}

if (!function_exists('mongo_projects_collection_name_for_read')) {
    function mongo_projects_collection_name_for_read(): string
    {
        if (!function_exists('normalize_app_role')) {
            require_once __DIR__.'/db.php';
        }

        $role = 'viewer';
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role'])) {
            $role = normalize_app_role($_SESSION['role']);
        }

        if (in_array($role, ['viewer', 'community_member'], true)) {
            return MONGO_PROJECTS_PUBLISHED_VIEW;
        }

        return 'projects';
    }
}

if (!function_exists('mongo_projects_for_read')) {
    function mongo_projects_for_read($db): MongoDB\Collection
    {
        $name = mongo_projects_collection_name_for_read();

        return $db->selectCollection($name);
    }
}
