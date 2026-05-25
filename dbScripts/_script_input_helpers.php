<?php

if (!function_exists('db_script_input_value')) {
    function db_script_input_value($key, $default = '') {
        $inputs = $GLOBALS['dbScriptInput'] ?? [];
        if (!isset($inputs[$key]) || !is_string($inputs[$key])) {
            return $default;
        }

        $value = trim($inputs[$key]);
        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('db_script_checkbox')) {
    function db_script_checkbox(string $key): bool
    {
        $inputs = $GLOBALS['dbScriptInput'] ?? null;
        if (!is_array($inputs)) {
            return false;
        }

        $v = $inputs[$key] ?? false;

        return $v === true || $v === 1 || $v === '1';
    }
}
