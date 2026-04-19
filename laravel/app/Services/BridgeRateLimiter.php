<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Schutz für bridge_auth.php / bridge_register.php (Brute-Force).
 */
class BridgeRateLimiter
{
    public static function enforceOrRedirect(string $kind): void
    {
        $ip = self::clientIp();
        $key = 'bridge:'.$kind.':'.sha1($ip);
        $max = match ($kind) {
            'register' => 5,
            default => 10,
        };
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = RateLimiter::availableIn($key);
            header('Location: index.php?page=account&err=throttle&wait='.(int) $wait);
            exit;
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private static function clientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($xff) && $xff !== '') {
            return trim(explode(',', $xff)[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
