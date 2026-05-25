<?php

/**
 * Optionales TOTP-2FA (RFC 6238) für die klassische PHP-App.
 */

if (!function_exists('two_factor_app_label')) {
    function two_factor_app_label(): string
    {
        if (!function_exists('site_settings_site_name')) {
            require_once __DIR__ . '/site_settings.php';
        }

        return site_settings_site_name();
    }
}

if (!function_exists('two_factor_base32_encode')) {
    function two_factor_base32_encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }
}

if (!function_exists('two_factor_base32_decode')) {
    function two_factor_base32_decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $len = strlen($secret);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}

if (!function_exists('two_factor_generate_secret')) {
    function two_factor_generate_secret(): string
    {
        return two_factor_base32_encode(random_bytes(20));
    }
}

if (!function_exists('two_factor_code_at_counter')) {
    function two_factor_code_at_counter(string $secretBase32, int $counter): string
    {
        $key = two_factor_base32_decode($secretBase32);
        $time = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('two_factor_verify_totp')) {
    function two_factor_verify_totp(string $secretBase32, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', trim($code)) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(two_factor_code_at_counter($secretBase32, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('two_factor_provisioning_uri')) {
    function two_factor_provisioning_uri(string $email, string $secretBase32): string
    {
        $label = rawurlencode(two_factor_app_label().':'.$email);
        $issuer = rawurlencode(two_factor_app_label());
        $secret = rawurlencode($secretBase32);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }
}

if (!function_exists('two_factor_generate_backup_codes')) {
    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    function two_factor_generate_backup_codes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }
}

if (!function_exists('two_factor_verify_backup_code')) {
    /**
     * @param list<string> $hashedCodes
     */
    function two_factor_verify_backup_code(array $hashedCodes, string $code): int
    {
        $code = strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
        if ($code === '') {
            return -1;
        }

        foreach ($hashedCodes as $idx => $hash) {
            if (! is_string($hash) || $hash === '') {
                continue;
            }
            if (password_verify($code, $hash)) {
                return (int) $idx;
            }
        }

        return -1;
    }
}

if (!function_exists('two_factor_user_enabled')) {
    /**
     * @param array<string, mixed>|object|null $user
     */
    function two_factor_user_enabled($user): bool
    {
        if ($user === null) {
            return false;
        }
        if (is_object($user)) {
            if (method_exists($user, 'getAttribute')) {
                $enabled = $user->getAttribute('two_factor_enabled');
                $secret = $user->getAttribute('two_factor_totp_secret');
            } else {
                $enabled = $user->two_factor_enabled ?? null;
                $secret = $user->two_factor_totp_secret ?? null;
            }

            return ! empty($enabled) && is_string($secret) && $secret !== '';
        }

        if (! is_array($user)) {
            return false;
        }

        return ! empty($user['two_factor_enabled']) && ! empty($user['two_factor_totp_secret']);
    }
}

if (!function_exists('two_factor_login_pending_valid')) {
    function two_factor_login_pending_valid(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $uid = (string) ($_SESSION['login_2fa_user_id'] ?? '');
        $exp = (int) ($_SESSION['login_2fa_expires'] ?? 0);

        return $uid !== '' && $exp >= time();
    }
}

if (!function_exists('two_factor_set_login_pending')) {
    function two_factor_set_login_pending(string $userId, int $ttlSeconds = 300): void
    {
        $_SESSION['login_2fa_user_id'] = $userId;
        $_SESSION['login_2fa_expires'] = time() + $ttlSeconds;
    }
}

if (!function_exists('two_factor_clear_login_pending')) {
    function two_factor_clear_login_pending(): void
    {
        unset($_SESSION['login_2fa_user_id'], $_SESSION['login_2fa_expires']);
    }
}

if (!function_exists('two_factor_find_user_by_id')) {
    /**
     * @return array<string, mixed>|object|null
     */
    function two_factor_find_user_by_id(string $userId)
    {
        require_once __DIR__.'/db.php';

        try {
            $oid = new MongoDB\BSON\ObjectId($userId);
        } catch (Throwable) {
            return null;
        }

        [, $adminDb] = get_admin_mongo_connection();

        return $adminDb->users->findOne(
            ['_id' => $oid],
            [
                'projection' => [
                    'email' => 1,
                    'username' => 1,
                    'password' => 1,
                    'role' => 1,
                    'account_moderation' => 1,
                    'two_factor_enabled' => 1,
                    'two_factor_totp_secret' => 1,
                    'two_factor_backup_codes' => 1,
                ],
            ]
        );
    }
}

if (!function_exists('two_factor_update_user')) {
    /**
     * @param array<string, mixed> $fields
     */
    function two_factor_update_user(string $userId, array $fields): bool
    {
        require_once __DIR__.'/db.php';

        try {
            $oid = new MongoDB\BSON\ObjectId($userId);
        } catch (Throwable) {
            return false;
        }

        [, $adminDb] = get_admin_mongo_connection();
        $result = $adminDb->users->updateOne(['_id' => $oid], ['$set' => $fields]);

        return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
    }
}

if (!function_exists('two_factor_consume_backup_code')) {
    /**
     * @param array<string, mixed>|object $user
     */
    function two_factor_consume_backup_code($user, string $code): bool
    {
        if (is_object($user)) {
            $user = (array) $user;
        }
        $hashed = $user['two_factor_backup_codes'] ?? [];
        if ($hashed instanceof Traversable) {
            $hashed = iterator_to_array($hashed);
        }
        if (! is_array($hashed)) {
            return false;
        }

        $idx = two_factor_verify_backup_code($hashed, $code);
        if ($idx < 0) {
            return false;
        }

        unset($hashed[$idx]);
        $hashed = array_values($hashed);

        return two_factor_update_user((string) ($user['_id'] ?? ''), [
            'two_factor_backup_codes' => $hashed,
        ]);
    }
}
