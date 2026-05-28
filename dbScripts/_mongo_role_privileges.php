<?php

declare(strict_types=1);

/**
 * Privilege matrices for app MongoDB custom roles (viewer, community, content_manager).
 */

require_once __DIR__.'/_mongo_roles_helpers.php';

if (!defined('MONGO_PROJECTS_PUBLISHED_VIEW')) {
    define('MONGO_PROJECTS_PUBLISHED_VIEW', 'projects_published');
}

if (!function_exists('mongo_app_role_locked_collections')) {
    /**
     * Collections that app technical users must never receive privileges on.
     *
     * @return list<string>
     */
    function mongo_app_role_locked_collections(): array
    {
        return [
            'users',
            'registration_codes',
            'handoff_tokens',
            'schema_migrations',
        ];
    }
}

if (!function_exists('mongo_assert_app_role_privileges_allowlist')) {
    /**
     * @param array<int, array<string, mixed>> $privileges
     */
    function mongo_assert_app_role_privileges_allowlist(array $privileges): void
    {
        $locked = mongo_app_role_locked_collections();
        foreach ($privileges as $entry) {
            $collection = $entry['resource']['collection'] ?? '';
            if (!is_string($collection) || $collection === '') {
                continue;
            }
            if (in_array($collection, $locked, true)) {
                throw new RuntimeException(
                    'Privilege allowlist violation: collection '.$collection.' must not be granted to app roles.'
                );
            }
        }
    }
}

if (!function_exists('mongo_registration_request_guest_actions')) {
    /**
     * @return list<string>
     */
    function mongo_registration_request_guest_actions(): array
    {
        return ['find', 'insert', 'update'];
    }
}

if (!function_exists('mongo_viewer_role_privileges')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function mongo_viewer_role_privileges(): array
    {
        $guest = mongo_registration_request_guest_actions();

        return [
            db_collection_actions(MONGO_PROJECTS_PUBLISHED_VIEW, ['find']),
            db_collection_actions('comments', ['find']),
            db_collection_actions('likes', ['find']),
            db_collection_actions('site_pages', ['find']),
            db_collection_actions('roles_config', ['find']),
            db_collection_actions('permissions_config', ['find']),
            db_collection_actions('registration_code_requests', $guest),
        ];
    }
}

if (!function_exists('mongo_community_member_role_privileges')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function mongo_community_member_role_privileges(): array
    {
        $guest = mongo_registration_request_guest_actions();

        return [
            db_collection_actions(MONGO_PROJECTS_PUBLISHED_VIEW, ['find']),
            db_collection_actions('comments', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('likes', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('site_pages', ['find']),
            db_collection_actions('roles_config', ['find']),
            db_collection_actions('permissions_config', ['find']),
            db_collection_actions('registration_code_requests', $guest),
        ];
    }
}

if (!function_exists('mongo_content_manager_role_privileges')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function mongo_content_manager_role_privileges(): array
    {
        $guest = mongo_registration_request_guest_actions();

        return [
            db_collection_actions('projects', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('comments', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('likes', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('site_pages', ['find', 'insert', 'update', 'remove']),
            db_collection_actions('roles_config', ['find']),
            db_collection_actions('permissions_config', ['find']),
            db_collection_actions('registration_code_requests', $guest),
        ];
    }
}

if (!function_exists('mongo_apply_app_custom_roles')) {
    function mongo_apply_app_custom_roles($db): void
    {
        ensure_mongo_role($db, 'viewerRole', mongo_viewer_role_privileges());
        ensure_mongo_role($db, 'communityMemberRole', mongo_community_member_role_privileges());
        ensure_mongo_role($db, 'contentManagerRole', mongo_content_manager_role_privileges());
    }
}
