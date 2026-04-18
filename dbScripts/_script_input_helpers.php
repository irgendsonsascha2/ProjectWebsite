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
