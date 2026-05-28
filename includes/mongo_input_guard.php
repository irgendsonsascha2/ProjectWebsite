<?php

declare(strict_types=1);

/**
 * Structural guards for user-influenced arrays before MongoDB or role/permission updates.
 * Complements scalar whitelist helpers in request.php / input_validate.php.
 */

if (! function_exists('mongo_guard_is_safe_key')) {
    /**
     * Reject MongoDB operator keys and dotted field paths from user-controlled maps.
     */
    function mongo_guard_is_safe_key(string $key): bool
    {
        if ($key === '' || $key[0] === '$' || str_contains($key, '.')) {
            return false;
        }

        return mongo_guard_reject_pollution_keys($key);
    }
}

if (! function_exists('mongo_guard_reject_pollution_keys')) {
    function mongo_guard_reject_pollution_keys(string $key): bool
    {
        $lower = strtolower($key);

        return ! in_array($lower, ['__proto__', 'constructor', 'prototype'], true);
    }
}

if (! function_exists('mongo_guard_assert_plain_array')) {
    /**
     * @return array<string, mixed>|null null if structure is unsafe or too deep
     */
    function mongo_guard_assert_plain_array(
        mixed $value,
        int $depth = 0,
        int $maxDepth = 4,
        int $maxKeys = 128,
    ): ?array {
        if (! is_array($value)) {
            return null;
        }
        if ($depth > $maxDepth || count($value) > $maxKeys) {
            return null;
        }

        $out = [];
        foreach ($value as $k => $v) {
            if (! is_string($k) || ! mongo_guard_is_safe_key($k)) {
                return null;
            }
            if (is_array($v)) {
                $nested = mongo_guard_assert_plain_array($v, $depth + 1, $maxDepth, $maxKeys);
                if ($nested === null) {
                    return null;
                }
                $out[$k] = $nested;
            } elseif (is_string($v) || is_int($v) || is_float($v) || is_bool($v) || $v === null) {
                $out[$k] = $v;
            } else {
                return null;
            }
        }

        return $out;
    }
}

if (! function_exists('mongo_guard_scalar_list')) {
    /**
     * Extract list values from POST checkbox arrays (numeric keys) or reject unsafe maps.
     *
     * @template T
     * @param  callable(mixed): (?T)  $mapItem
     * @return list<T>
     */
    function mongo_guard_scalar_list(mixed $raw, int $maxItems, callable $mapItem): array
    {
        if ($raw instanceof Traversable) {
            $raw = iterator_to_array($raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && ! mongo_guard_is_safe_key($key)) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $mapped = $mapItem($value);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
            if (count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }
}

if (! function_exists('normalize_permission_keys')) {
    /**
     * @param  list<string>  $allowedKeys
     * @return list<string>
     */
    function normalize_permission_keys(mixed $raw, array $allowedKeys): array
    {
        $items = mongo_guard_scalar_list($raw, 64, static fn ($v) => is_string($v) ? $v : null);
        $out = [];
        foreach ($items as $perm) {
            $valid = input_enum($perm, $allowedKeys);
            if ($valid !== null && ! in_array($valid, $out, true)) {
                $out[] = $valid;
            }
        }

        return $out;
    }
}

if (! function_exists('normalize_role_keys_list')) {
    /**
     * @param  list<string>  $allowedRoleKeys
     * @return list<string>
     */
    function normalize_role_keys_list(mixed $raw, array $allowedRoleKeys): array
    {
        $items = mongo_guard_scalar_list($raw, 32, static fn ($v) => is_string($v) ? $v : null);
        $out = [];
        foreach ($items as $role) {
            $valid = input_enum($role, $allowedRoleKeys);
            if ($valid !== null && ! in_array($valid, $out, true)) {
                $out[] = $valid;
            }
        }

        return $out;
    }
}

if (! function_exists('permissions_config_allowed_keys')) {
    /**
     * @return list<string>
     */
    function permissions_config_allowed_keys($db): array
    {
        $keys = [];
        try {
            $cursor = $db->permissions_config->find([], ['projection' => ['key' => 1]]);
            foreach ($cursor as $doc) {
                $k = $doc['key'] ?? null;
                if (is_string($k) && $k !== '') {
                    $keys[] = $k;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $keys;
    }
}

if (! function_exists('roles_config_allowed_keys')) {
    /**
     * @return list<string>
     */
    function roles_config_allowed_keys($db): array
    {
        $keys = [];
        try {
            $cursor = $db->roles_config->find([], ['projection' => ['role' => 1]]);
            foreach ($cursor as $doc) {
                $k = $doc['role'] ?? null;
                if (is_string($k) && $k !== '') {
                    $keys[] = $k;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $keys;
    }
}

if (! function_exists('input_post_int_index_list')) {
    /**
     * Gallery reorder indices: non-negative ints, capped count.
     *
     * @return list<int>
     */
    function input_post_int_index_list(mixed $raw, int $maxItems = 200, int $maxIndex = 10000): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $plain = mongo_guard_assert_plain_array($raw, 0, 1, $maxItems);
        if ($plain === null) {
            return [];
        }

        $out = [];
        foreach ($plain as $v) {
            if (! is_string($v) && ! is_int($v) && ! is_float($v)) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '' || ! preg_match('/^\d+$/', $s)) {
                continue;
            }
            $idx = (int) $s;
            if ($idx < 0 || $idx > $maxIndex) {
                continue;
            }
            $out[] = $idx;
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return $out;
    }
}

if (! function_exists('req_get_token_hex')) {
    function req_get_token_hex(string $key, int $len = 64, bool $trim = false): ?string
    {
        $raw = req_read_scalar($_GET, $key);
        if ($raw === null) {
            return null;
        }
        if ($trim) {
            $raw = trim($raw);
        }
        if (strlen($raw) !== $len || ! ctype_xdigit($raw)) {
            return null;
        }

        return strtolower($raw);
    }
}

if (! function_exists('req_post_string_list')) {
    /**
     * @return list<string>
     */
    function req_post_string_list(string $key, int $maxItems, int $maxLenPerItem = 200): array
    {
        if (! isset($_POST[$key]) || ! is_array($_POST[$key])) {
            return [];
        }

        return mongo_guard_scalar_list(
            $_POST[$key],
            $maxItems,
            static function ($v) use ($maxLenPerItem): ?string {
                if (! is_string($v)) {
                    return null;
                }
                if ($maxLenPerItem > 0 && strlen($v) > $maxLenPerItem) {
                    $v = substr($v, 0, $maxLenPerItem);
                }

                return $v === '' ? null : $v;
            }
        );
    }
}

if (! function_exists('req_post_objectid_list')) {
    /**
     * @return list<\MongoDB\BSON\ObjectId>
     */
    function req_post_objectid_list(string $key, int $maxItems = 100): array
    {
        $strings = req_post_string_list($key, $maxItems, 24);
        $out = [];
        foreach ($strings as $id) {
            $oid = req_objectid_from_scalar($id);
            if ($oid !== null) {
                $out[] = $oid;
            }
        }

        return $out;
    }
}

if (! function_exists('req_post_action')) {
    /**
     * @param  list<string>  $allowed
     */
    function req_post_action(string $key, array $allowed): ?string
    {
        $raw = req_post_string($key, '', 80, true);
        if ($raw === '') {
            return null;
        }

        return input_enum($raw, $allowed);
    }
}
