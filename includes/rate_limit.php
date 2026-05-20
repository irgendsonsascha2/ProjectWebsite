<?php

/**
 * Einfaches IP-basiertes Rate-Limit (Datei-Store unter logs/, nur für lokale/kleine Deployments).
 */

if (!function_exists('rate_limit_storage_path')) {
    function rate_limit_storage_path(string $bucket): string
    {
        $dir = dirname(__DIR__).'/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $bucket);

        return $dir.'/rate_limit_'.$safe.'.json';
    }
}

if (!function_exists('rate_limit_client_ip')) {
    function rate_limit_client_ip(): string
    {
        if (function_exists('trusted_proxy_client_ip')) {
            return trusted_proxy_client_ip();
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('rate_limit_allow')) {
    /**
     * @return bool true = erlaubt, false = Limit erreicht
     */
    function rate_limit_allow(string $bucket, int $maxAttempts, int $windowSeconds, ?string $key = null): bool
    {
        if ($maxAttempts < 1 || $windowSeconds < 1) {
            return true;
        }

        $key = $key ?? rate_limit_client_ip();
        $path = rate_limit_storage_path($bucket);
        $now = time();
        $cutoff = $now - $windowSeconds;

        $data = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $hits = [];
        if (isset($data[$key]) && is_array($data[$key])) {
            foreach ($data[$key] as $ts) {
                if (is_int($ts) && $ts >= $cutoff) {
                    $hits[] = $ts;
                }
            }
        }

        if (count($hits) >= $maxAttempts) {
            return false;
        }

        $hits[] = $now;
        $data[$key] = $hits;

        @file_put_contents($path, json_encode($data), LOCK_EX);

        return true;
    }
}

if (!function_exists('rate_limit_registration_code_request_allow')) {
    function rate_limit_registration_code_request_allow(): bool
    {
        return rate_limit_allow('registration_code_request', 5, 3600);
    }
}
