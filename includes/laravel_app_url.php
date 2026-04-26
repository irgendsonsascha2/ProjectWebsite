<?php

declare(strict_types=1);

/**
 * APP_URL aus laravel/.env für Links zur Laravel-App (Passwort vergessen, Verifizierung).
 */
function laravel_app_url(): string
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $path = dirname(__DIR__).'/laravel/.env';
    $default = 'http://127.0.0.1:8000';

    if (! is_readable($path)) {
        return $cached = $default;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (preg_match('/^APP_URL=(.+)$/', $line, $m)) {
            $url = trim($m[1], " \t\n\r\0\x0B\"'");

            return $cached = ($url !== '' ? rtrim($url, '/') : $default);
        }
    }

    return $cached = $default;
}

/**
 * True, wenn die Laravel-App (APP_URL) erreichbar ist.
 * Für lokale Dev-Setups verhindert das "kaputte" Links, wenn `php artisan serve`
 * nicht läuft.
 */
function laravel_app_reachable(int $timeoutMs = 250): bool
{
    $url = laravel_app_url();
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $host = (string)($parts['host'] ?? '');
    if ($host === '') {
        return false;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    if ($port < 1 || $port > 65535) {
        return false;
    }

    $timeoutSec = max(1, (int)ceil($timeoutMs / 1000));
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
    if (!is_resource($fp)) {
        return false;
    }
    fclose($fp);
    return true;
}
