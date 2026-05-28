<?php

declare(strict_types=1);

require_once __DIR__.'/../includes/input_validate.php';

if (!function_exists('db_script_scalar_string')) {
    function db_script_scalar_string(string $key, string $default = ''): string
    {
        $inputs = $GLOBALS['dbScriptInput'] ?? [];
        if (! isset($inputs[$key])) {
            return $default;
        }
        $v = $inputs[$key];
        if (! is_string($v) && ! is_int($v) && ! is_float($v) && ! is_bool($v)) {
            return $default;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : $default;
    }
}

if (!function_exists('db_script_input_value')) {
    function db_script_input_value(string $key, string $default = ''): string
    {
        return db_script_scalar_string($key, $default);
    }
}

if (!function_exists('db_script_input_password')) {
    function db_script_input_password(string $key, int $minLen = 8): string
    {
        $inputs = $GLOBALS['dbScriptInput'] ?? [];
        $raw = $inputs[$key] ?? '';
        if (! is_string($raw)) {
            throw new RuntimeException('Passwort-Feld '.$key.' fehlt oder ist ungültig.');
        }
        $validated = input_password_secret($raw, $minLen);
        if ($validated === null) {
            throw new RuntimeException(
                'Passwort für '.$key.' ungültig (min. '.$minLen.' Zeichen, keine Steuerzeichen/Zeilenumbrüche).'
            );
        }

        return $validated;
    }
}

if (!function_exists('db_script_input_script_name')) {
    function db_script_input_script_name(string $key = 'script_name'): string
    {
        $inputs = $GLOBALS['dbScriptInput'] ?? [];
        $raw = isset($inputs[$key]) && is_string($inputs[$key]) ? $inputs[$key] : '';
        $name = input_db_script_basename($raw);
        if ($name === null) {
            throw new RuntimeException('Ungültiger oder unbekannter Skriptname.');
        }

        return $name;
    }
}

if (!function_exists('db_script_resolve_password')) {
    /**
     * Passwort aus $GLOBALS['dbScriptInput'] oder Umgebungsvariable (validiert).
     */
    function db_script_resolve_password(string $key, string $envVar): string
    {
        $raw = db_script_scalar_string($key, '');
        if ($raw === '') {
            $fromEnv = getenv($envVar);
            $raw = is_string($fromEnv) ? trim($fromEnv) : '';
        }
        $validated = input_password_secret($raw, 8);
        if ($validated === null) {
            throw new RuntimeException(
                'Passwort für '.$key.' fehlt oder ist ungültig ('.$envVar.' oder Formularfeld, min. 8 Zeichen).'
            );
        }

        return $validated;
    }
}

if (!function_exists('db_script_checkbox')) {
    function db_script_checkbox(string $key): bool
    {
        $inputs = $GLOBALS['dbScriptInput'] ?? null;
        if (! is_array($inputs)) {
            return false;
        }

        $v = $inputs[$key] ?? false;

        return $v === true || $v === 1 || $v === '1';
    }
}
