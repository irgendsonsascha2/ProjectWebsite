<?php

declare(strict_types=1);

require_once __DIR__.'/request.php';

if (!function_exists('input_normalize_unicode')) {
    function input_normalize_unicode(string $s): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($s, Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $s;
    }
}

if (!function_exists('input_strip_control_chars')) {
    function input_strip_control_chars(string $s, bool $allowNewlines = false): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            $ord = ord($ch);
            if ($ord === 0) {
                continue;
            }
            if ($allowNewlines && ($ch === "\n" || $ch === "\r" || $ch === "\t")) {
                $out .= $ch;
                continue;
            }
            if ($ord < 32 || $ord === 127) {
                continue;
            }
            $out .= $ch;
        }

        return $out;
    }
}

if (!function_exists('input_bounded_text')) {
    function input_bounded_text(?string $raw, int $maxLen, bool $allowNewlines = false): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = input_normalize_unicode(trim($raw));
        $s = input_strip_control_chars($s, $allowNewlines);
        if ($s === '') {
            return null;
        }
        if ($maxLen > 0 && strlen($s) > $maxLen) {
            $s = substr($s, 0, $maxLen);
        }
        if ($s === '') {
            return null;
        }

        return $s;
    }
}

if (!function_exists('input_comment_text_max_length')) {
    function input_comment_text_max_length(): int
    {
        if (defined('COMMENT_TEXT_MAX_LENGTH')) {
            return max(50, (int) COMMENT_TEXT_MAX_LENGTH);
        }

        return 400;
    }
}

if (!function_exists('input_comment_text')) {
    function input_comment_text(?string $raw, ?int $maxLen = null): ?string
    {
        $max = $maxLen ?? input_comment_text_max_length();

        return input_bounded_text($raw, $max, true);
    }
}

if (!function_exists('input_project_title')) {
    function input_project_title(?string $raw): ?string
    {
        return input_bounded_text($raw, 200, false);
    }
}

if (!function_exists('input_project_description')) {
    function input_project_description(?string $raw): ?string
    {
        return input_bounded_text($raw, 5000, true);
    }
}

if (!function_exists('input_project_tags')) {
    /**
     * @return list<string>
     */
    function input_project_tags(mixed $raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                }
            }
        } elseif (is_string($raw)) {
            $parts = explode(',', $raw);
        } else {
            return [];
        }

        $tags = [];
        foreach ($parts as $part) {
            $tag = strtolower(trim((string) $part));
            $tag = input_strip_control_chars($tag, false);
            if ($tag === '') {
                continue;
            }
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $tag)) {
                continue;
            }
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
            if (count($tags) >= 20) {
                break;
            }
        }

        return $tags;
    }
}

if (!function_exists('input_email')) {
    function input_email(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        $validated = filter_var($s, FILTER_VALIDATE_EMAIL);

        return is_string($validated) ? $validated : null;
    }
}

if (!function_exists('input_slug_key')) {
    function input_slug_key(?string $raw, int $maxLen = 64): ?string
    {
        return input_identifier_key($raw, 2, $maxLen, false);
    }
}

if (!function_exists('input_identifier_key')) {
    /**
     * a-z Start, dann a-z0-9_ oder mit Bindestrich (Rollen/Berechtigungen).
     */
    function input_identifier_key(?string $raw, int $minLen, int $maxLen, bool $allowHyphen = true): ?string
    {
        if ($raw === null || $minLen < 1 || $maxLen < $minLen) {
            return null;
        }
        $s = strtolower(trim($raw));
        $s = input_strip_control_chars($s, false);
        if ($s === '') {
            return null;
        }
        $innerMax = $maxLen - 1;
        $innerMin = $minLen - 1;
        $inner = $allowHyphen ? '[a-z0-9_-]' : '[a-z0-9_]';
        if (! preg_match('/^[a-z]'.$inner.'{'.$innerMin.','.$innerMax.'}$/', $s)) {
            return null;
        }

        return $s;
    }
}

if (!function_exists('input_enum')) {
    /**
     * @param list<string> $allowed
     */
    function input_enum(?string $raw, array $allowed): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if (! in_array($s, $allowed, true)) {
            return null;
        }

        return $s;
    }
}

if (!function_exists('input_object_id_hex')) {
    function input_object_id_hex(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if (! preg_match('/^[a-fA-F0-9]{24}$/', $s)) {
            return null;
        }
        $oid = req_objectid_from_scalar($s);

        return $oid !== null ? (string) $oid : null;
    }
}

if (!function_exists('input_interaction_type')) {
    function input_interaction_type(?string $raw): ?string
    {
        return input_enum($raw, ['like', 'dislike']);
    }
}

if (!function_exists('input_db_script_dir')) {
    function input_db_script_dir(): string
    {
        return dirname(__DIR__).'/dbScripts';
    }
}

if (!function_exists('input_db_script_basename')) {
    function input_db_script_basename(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || str_contains($trimmed, '/') || str_contains($trimmed, '\\')) {
            return null;
        }
        $name = basename($trimmed);
        if (! preg_match('/^\d{2}_[a-z0-9_]+\.php$/', $name)) {
            if ($name !== 'db_init_master.php') {
                return null;
            }
        }
        $path = input_db_script_dir().'/'.$name;
        if (! is_file($path)) {
            return null;
        }

        return $name;
    }
}

if (! function_exists('input_user_password')) {
    /**
     * End-user account password: length bounds only, no trim, Unicode allowed.
     */
    function input_user_password(?string $raw, int $minLen = 12, int $maxLen = 512): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $len = mb_strlen($raw, 'UTF-8');
        if ($len < $minLen || $len > $maxLen) {
            return null;
        }

        return $raw;
    }
}

if (! function_exists('input_secret_password')) {
    /**
     * Operational passwords (MongoDB users, seed admin): length bounds, reject NUL only.
     */
    function input_secret_password(?string $raw, int $minLen = 8, int $maxLen = 512): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        if (str_contains($raw, "\0")) {
            return null;
        }
        $len = mb_strlen($raw, 'UTF-8');
        if ($len < $minLen || $len > $maxLen) {
            return null;
        }

        return $raw;
    }
}

if (!function_exists('input_password_secret')) {
    /**
     * @deprecated Use input_secret_password() — kept as alias for dbScripts/admin paths.
     */
    function input_password_secret(?string $raw, int $minLen = 8, int $maxLen = 512): ?string
    {
        return input_secret_password($raw, $minLen, $maxLen);
    }
}

if (!function_exists('input_admin_label')) {
    function input_admin_label(?string $raw, int $maxLen = 120): ?string
    {
        return input_bounded_text($raw, $maxLen, false);
    }
}

if (!function_exists('input_clamped_int')) {
    function input_clamped_int(mixed $raw, int $min, int $max, int $default): int
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return $default;
        }
        $s = trim((string) $raw);
        if ($s === '' || ! preg_match('/^-?\d+$/', $s)) {
            return $default;
        }
        $v = (int) $s;
        if ($v < $min) {
            return $min;
        }
        if ($v > $max) {
            return $max;
        }

        return $v;
    }
}

if (!function_exists('input_admin_description')) {
    function input_admin_description(?string $raw, int $maxLen = 500): ?string
    {
        return input_bounded_text($raw, $maxLen, true);
    }
}
