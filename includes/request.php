<?php

declare(strict_types=1);

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

if (!function_exists('req_read_scalar')) {
    function req_read_scalar(array $src, string $key): ?string
    {
        if (!array_key_exists($key, $src)) {
            return null;
        }
        $v = $src[$key];
        if (is_string($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v) || is_bool($v)) {
            return (string) $v;
        }

        // Reject arrays/objects to avoid accidental operator injection shapes.
        return null;
    }
}

if (!function_exists('req_get_string')) {
    function req_get_string(string $key, string $default = '', int $maxLen = 2000, bool $trim = true): string
    {
        $raw = req_read_scalar($_GET, $key);
        if ($raw === null) {
            return $default;
        }
        if ($trim) {
            $raw = trim($raw);
        }
        if ($maxLen > 0 && strlen($raw) > $maxLen) {
            $raw = substr($raw, 0, $maxLen);
        }

        return $raw;
    }
}

if (!function_exists('req_post_string')) {
    function req_post_string(string $key, string $default = '', int $maxLen = 2000, bool $trim = true): string
    {
        $raw = req_read_scalar($_POST, $key);
        if ($raw === null) {
            return $default;
        }
        if ($trim) {
            $raw = trim($raw);
        }
        if ($maxLen > 0 && strlen($raw) > $maxLen) {
            $raw = substr($raw, 0, $maxLen);
        }

        return $raw;
    }
}

if (!function_exists('req_get_int')) {
    function req_get_int(string $key, int $default, int $min, int $max): int
    {
        $raw = req_read_scalar($_GET, $key);
        if ($raw === null) {
            return $default;
        }
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return $default;
        }
        $v = (int) $raw;
        if ($v < $min) return $min;
        if ($v > $max) return $max;
        return $v;
    }
}

if (!function_exists('req_post_bool')) {
    function req_post_bool(string $key): bool
    {
        $raw = req_read_scalar($_POST, $key);
        if ($raw === null) {
            return false;
        }
        $raw = strtolower(trim($raw));
        return $raw === '1' || $raw === 'true' || $raw === 'on' || $raw === 'yes';
    }
}

if (!function_exists('req_get_objectid')) {
    function req_get_objectid(string $key): ?ObjectId
    {
        $raw = req_read_scalar($_GET, $key);
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return new ObjectId($raw);
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('req_objectid_from_scalar')) {
    function req_objectid_from_scalar($raw): ?ObjectId
    {
        if (!is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return new ObjectId($raw);
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('req_get_search_regex')) {
    function req_get_search_regex(string $key = 'q', int $maxLen = 80, int $minLen = 2): ?Regex
    {
        $q = req_get_string($key, '', $maxLen, true);
        if ($q === '' || strlen($q) < $minLen) {
            return null;
        }

        $escaped = preg_quote($q, '/');
        return new Regex($escaped, 'i');
    }
}

