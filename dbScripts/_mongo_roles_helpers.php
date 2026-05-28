<?php

declare(strict_types=1);

/**
 * Shared helpers for MongoDB custom roles/users (03, 17).
 */

if (!function_exists('mongo_role_exists')) {
    function mongo_role_exists($db, string $roleName): bool
    {
        $result = $db->command([
            'rolesInfo' => $roleName,
            'showPrivileges' => true,
            'showBuiltinRoles' => false,
        ])->toArray();

        if (!isset($result[0]->roles)) {
            return false;
        }

        return count((array) $result[0]->roles) > 0;
    }
}

if (!function_exists('mongo_user_exists')) {
    function mongo_user_exists($db, string $username): bool
    {
        $result = $db->command([
            'usersInfo' => $username,
            'showPrivileges' => false,
            'showCredentials' => false,
        ])->toArray();

        if (!isset($result[0]->users)) {
            return false;
        }

        return count((array) $result[0]->users) > 0;
    }
}

if (!function_exists('ensure_mongo_role')) {
    /**
     * @param array<int, array<string, mixed>> $privileges
     * @param array<int, array<string, string>> $inheritedRoles
     */
    function ensure_mongo_role($db, string $roleName, array $privileges, array $inheritedRoles = []): void
    {
        mongo_assert_app_role_privileges_allowlist($privileges);

        $command = [
            'createRole' => $roleName,
            'privileges' => $privileges,
            'roles' => $inheritedRoles,
        ];

        if (mongo_role_exists($db, $roleName)) {
            $command = [
                'updateRole' => $roleName,
                'privileges' => $privileges,
                'roles' => $inheritedRoles,
            ];
        }

        $db->command($command)->toArray();
    }
}

if (!function_exists('ensure_mongo_user')) {
    /**
     * @param array<int, array<string, string>> $roles
     */
    function ensure_mongo_user($db, string $username, string $password, array $roles): void
    {
        $command = [
            'createUser' => $username,
            'pwd' => $password,
            'roles' => $roles,
        ];

        if (mongo_user_exists($db, $username)) {
            $command = [
                'updateUser' => $username,
                'pwd' => $password,
                'roles' => $roles,
            ];
        }

        $db->command($command)->toArray();
    }
}

if (!function_exists('db_collection_actions')) {
    /**
     * @param list<string> $actions
     * @return array{resource: array{db: string, collection: string}, actions: list<string>}
     */
    function db_collection_actions(string $collection, array $actions): array
    {
        return [
            'resource' => [
                'db' => mongo_config()['db_name'],
                'collection' => $collection,
            ],
            'actions' => $actions,
        ];
    }
}
