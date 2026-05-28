<?php

require_once __DIR__.'/user_db.php';

use MongoDB\BSON\UTCDateTime;

if (!function_exists('user_moderation_reason_options')) {
    /**
     * @return array<string, string> reason_key => label
     */
    function user_moderation_reason_options(): array
    {
        return [
            'terms' => 'Verstoß gegen Nutzungsbedingungen',
            'spam' => 'Spam oder unerwünschte Werbung',
            'harassment' => 'Belästigung oder Beleidigung',
            'copyright' => 'Urheberrechtsverletzung',
            'abuse' => 'Missbrauch oder Sicherheitsrisiko',
            'custom' => 'Sonstiges (eigener Text)',
        ];
    }
}

if (!function_exists('user_moderation_normalize_document')) {
    /**
     * @param array<string, mixed>|object|null $user
     * @return array<string, mixed>|null
     */
    function user_moderation_normalize_document($user): ?array
    {
        if ($user === null) {
            return null;
        }
        if (is_object($user)) {
            if ($user instanceof MongoDB\Model\BSONDocument) {
                $user = $user->getArrayCopy();
            } else {
                $user = (array) $user;
            }
        }
        if (! is_array($user)) {
            return null;
        }

        return $user;
    }
}

if (!function_exists('user_moderation_from_user')) {
    /**
     * @param array<string, mixed>|object|null $user
     * @return array<string, mixed>|null
     */
    function user_moderation_from_user($user): ?array
    {
        $user = user_moderation_normalize_document($user);
        if ($user === null) {
            return null;
        }
        $mod = $user['account_moderation'] ?? null;
        if ($mod instanceof MongoDB\Model\BSONDocument) {
            $mod = $mod->getArrayCopy();
        } elseif (is_object($mod)) {
            $mod = (array) $mod;
        }
        if (! is_array($mod) || $mod === []) {
            return null;
        }

        return $mod;
    }
}

if (!function_exists('user_moderation_is_expired')) {
    /**
     * @param array<string, mixed> $mod
     */
    function user_moderation_is_expired(array $mod): bool
    {
        $status = (string) ($mod['status'] ?? 'active');
        if ($status !== 'suspended') {
            return false;
        }
        $until = $mod['until'] ?? null;
        if (! $until instanceof UTCDateTime) {
            return true;
        }

        return $until->toDateTime()->getTimestamp() <= time();
    }
}

if (!function_exists('user_moderation_is_blocked')) {
    /**
     * @param array<string, mixed>|object|null $user
     */
    function user_moderation_is_blocked($user): bool
    {
        $user = user_moderation_normalize_document($user);
        if ($user === null) {
            return false;
        }
        if ((string) ($user['role'] ?? '') === 'admin') {
            return false;
        }
        $mod = user_moderation_from_user($user);
        if ($mod === null) {
            return false;
        }
        $status = (string) ($mod['status'] ?? 'active');
        if ($status === 'active' || $status === '') {
            return false;
        }
        if ($status === 'suspended' && user_moderation_is_expired($mod)) {
            return false;
        }

        return $status === 'suspended' || $status === 'banned';
    }
}

if (!function_exists('user_moderation_reason_label')) {
    /**
     * @param array<string, mixed> $mod
     */
    function user_moderation_reason_label(array $mod): string
    {
        $key = (string) ($mod['reason_key'] ?? '');
        $options = user_moderation_reason_options();
        if ($key === 'custom') {
            $custom = trim((string) ($mod['reason_custom'] ?? ''));

            return $custom !== '' ? $custom : ($options['custom'] ?? 'Sonstiges');
        }
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $key !== '' ? $key : 'Unbekannter Grund';
    }
}

if (!function_exists('user_moderation_quote_reason')) {
    /** Typografische Anführungszeichen (deutsch) für die öffentliche Anzeige. */
    function user_moderation_quote_reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return '';
        }

        return '„'.str_replace(['„', '"', '"', '"', '»', '«'], "'", $reason).'"';
    }
}

if (!function_exists('user_moderation_public_message_parts')) {
    /**
     * @param array<string, mixed>|object|null $user
     * @return array{lead: string, reason: string|null, until_suffix: string}
     */
    function user_moderation_public_message_parts($user): array
    {
        if (! user_moderation_is_blocked($user)) {
            return ['lead' => '', 'reason' => null, 'until_suffix' => ''];
        }
        $mod = user_moderation_from_user($user);
        if ($mod === null) {
            return ['lead' => 'Dein Konto ist derzeit gesperrt.', 'reason' => null, 'until_suffix' => ''];
        }
        $status = (string) ($mod['status'] ?? '');
        $lead = $status === 'suspended'
            ? 'Dein Konto ist vorübergehend gesperrt (Timeout).'
            : 'Dein Konto ist derzeit gesperrt.';
        if (empty($mod['show_reason'])) {
            return ['lead' => $lead, 'reason' => null, 'until_suffix' => ''];
        }
        $reason = user_moderation_reason_label($mod);
        $untilSuffix = '';
        $until = $mod['until'] ?? null;
        if ($status === 'suspended' && $until instanceof UTCDateTime) {
            $untilSuffix = ' Die Sperre endet voraussichtlich am '.$until->toDateTime()->format('d.m.Y H:i').' Uhr.';
        }

        return ['lead' => $lead, 'reason' => $reason, 'until_suffix' => $untilSuffix];
    }
}

if (!function_exists('user_moderation_public_message')) {
    /**
     * Klartext (z. B. Laravel-Validierung).
     *
     * @param array<string, mixed>|object|null $user
     */
    function user_moderation_public_message($user): string
    {
        $parts = user_moderation_public_message_parts($user);
        if ($parts['lead'] === '') {
            return '';
        }
        if ($parts['reason'] === null) {
            return $parts['lead'];
        }
        $quoted = user_moderation_quote_reason($parts['reason']);

        return $parts['lead'].' Grund: '.$quoted.$parts['until_suffix'];
    }
}

if (!function_exists('user_moderation_public_message_html')) {
    /**
     * HTML für Login-Hinweis (Grund in <q>, serverseitig escaped).
     *
     * @param array<string, mixed>|object|null $user
     */
    function user_moderation_public_message_html($user): string
    {
        $parts = user_moderation_public_message_parts($user);
        if ($parts['lead'] === '') {
            return '';
        }
        $html = htmlspecialchars($parts['lead'], ENT_QUOTES, 'UTF-8');
        if ($parts['reason'] !== null) {
            $reasonEsc = htmlspecialchars($parts['reason'], ENT_QUOTES, 'UTF-8');
            $html .= ' Grund: <q class="moderation-reason">'.$reasonEsc.'</q>';
        }
        if ($parts['until_suffix'] !== '') {
            $html .= htmlspecialchars($parts['until_suffix'], ENT_QUOTES, 'UTF-8');
        }

        return $html;
    }
}

if (!function_exists('user_moderation_status_label')) {
    /**
     * @param array<string, mixed>|object|null $user
     */
    function user_moderation_status_label($user): string
    {
        $user = user_moderation_normalize_document($user);
        if ($user === null) {
            return '—';
        }
        $mod = user_moderation_from_user($user);
        if ($mod === null) {
            return 'Aktiv';
        }
        $status = (string) ($mod['status'] ?? 'active');
        if ($status === 'suspended' && user_moderation_is_expired($mod)) {
            return 'Timeout abgelaufen';
        }
        if ($status === 'suspended') {
            $until = $mod['until'] ?? null;
            if ($until instanceof UTCDateTime) {
                return 'Timeout bis '.$until->toDateTime()->format('d.m.Y H:i');
            }

            return 'Timeout';
        }
        if ($status === 'banned') {
            return 'Ban';
        }

        return 'Aktiv';
    }
}

if (!function_exists('user_moderation_redirect_blocked')) {
    /**
     * @param array<string, mixed>|object|null $user
     */
    function user_moderation_redirect_blocked($user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            foreach ([
                'user_id', 'email', 'username', 'role', 'permissions',
                'email_verified', 'email_verified_at',
            ] as $key) {
                unset($_SESSION[$key]);
            }
            $_SESSION['moderation_message'] = user_moderation_public_message($user);
            $_SESSION['moderation_message_html'] = user_moderation_public_message_html($user);
        }
        if (! function_exists('legacy_index_url')) {
            require_once __DIR__.'/app_env.php';
        }
        header('Location: '.legacy_index_url(['page' => 'login', 'err' => 'moderation']));
        exit;
    }
}

if (!function_exists('user_moderation_role_is_protected')) {
    function user_moderation_role_is_protected($user): bool
    {
        $user = user_moderation_normalize_document($user);

        return $user !== null && (string) ($user['role'] ?? '') === 'admin';
    }
}

if (!function_exists('user_moderation_parse_until')) {
    /**
     * @return UTCDateTime|null
     */
    function user_moderation_parse_until(string $preset, string $customUntil): ?UTCDateTime
    {
        $now = time();
        switch ($preset) {
            case '1h':
                return new UTCDateTime(($now + 3600) * 1000);
            case '24h':
                return new UTCDateTime(($now + 86400) * 1000);
            case '7d':
                return new UTCDateTime(($now + 7 * 86400) * 1000);
            case '30d':
                return new UTCDateTime(($now + 30 * 86400) * 1000);
            case 'custom':
                $customUntil = trim($customUntil);
                if ($customUntil === '') {
                    return null;
                }
                try {
                    $dt = new DateTimeImmutable($customUntil);

                    return new UTCDateTime($dt);
                } catch (Throwable) {
                    return null;
                }
            default:
                return null;
        }
    }
}

if (!function_exists('user_moderation_apply')) {
    /**
     * @param array<string, mixed> $payload status, duration_preset, until_custom, reason_key, reason_custom, show_reason
     */
    function user_moderation_apply($db, string $userId, array $payload, string $adminUsername): void
    {
        $oid = new MongoDB\BSON\ObjectId($userId);
        $user = $db->users->findOne(['_id' => $oid]);
        if ($user === null) {
            throw new InvalidArgumentException('Nutzer nicht gefunden.');
        }
        if (user_moderation_role_is_protected($user)) {
            throw new InvalidArgumentException('Admin-Konten können nicht gesperrt werden.');
        }

        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, ['suspended', 'banned'], true)) {
            throw new InvalidArgumentException('Ungültiger Sperr-Status.');
        }

        $reasonKey = (string) ($payload['reason_key'] ?? '');
        $options = user_moderation_reason_options();
        if (! isset($options[$reasonKey])) {
            throw new InvalidArgumentException('Ungültiger Grund.');
        }
        $reasonCustom = trim((string) ($payload['reason_custom'] ?? ''));
        if ($reasonKey === 'custom' && $reasonCustom === '') {
            throw new InvalidArgumentException('Bitte einen eigenen Grund angeben.');
        }

        $until = null;
        if ($status === 'suspended') {
            $until = user_moderation_parse_until(
                (string) ($payload['duration_preset'] ?? ''),
                (string) ($payload['until_custom'] ?? '')
            );
            if ($until === null) {
                throw new InvalidArgumentException('Bitte eine gültige Timeout-Dauer wählen.');
            }
        }

        $mod = [
            'status' => $status,
            'until' => $until,
            'reason_key' => $reasonKey,
            'reason_custom' => $reasonKey === 'custom' ? $reasonCustom : '',
            'show_reason' => ! empty($payload['show_reason']),
            'updated_at' => new UTCDateTime(),
            'updated_by' => $adminUsername,
        ];

        $db->users->updateOne(
            ['_id' => $oid],
            ['$set' => ['account_moderation' => $mod]]
        );
    }
}

if (!function_exists('user_moderation_clear')) {
    function user_moderation_clear($db, string $userId, string $adminUsername): void
    {
        $oid = new MongoDB\BSON\ObjectId($userId);
        $user = $db->users->findOne(['_id' => $oid]);
        if ($user === null) {
            throw new InvalidArgumentException('Nutzer nicht gefunden.');
        }
        if (user_moderation_role_is_protected($user)) {
            throw new InvalidArgumentException('Admin-Konten können hier nicht geändert werden.');
        }

        $db->users->updateOne(
            ['_id' => $oid],
            ['$set' => [
                'account_moderation' => [
                    'status' => 'active',
                    'until' => null,
                    'reason_key' => '',
                    'reason_custom' => '',
                    'show_reason' => false,
                    'updated_at' => new UTCDateTime(),
                    'updated_by' => $adminUsername,
                ],
            ]]
        );
    }
}

if (!function_exists('user_moderation_search')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function user_moderation_search($db, string $query, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $filter = [];
        $query = trim($query);
        if ($query !== '') {
            $query = substr($query, 0, 80);
            $escaped = preg_quote($query, '/');
            $filter['$or'] = [
                ['email' => ['$regex' => $escaped, '$options' => 'i']],
                ['username' => ['$regex' => $escaped, '$options' => 'i']],
            ];
        }

        $cursor = $db->users->find($filter, [
            'sort' => ['username' => 1],
            'limit' => $limit,
            'projection' => [
                'email' => 1,
                'username' => 1,
                'role' => 1,
                'created_at' => 1,
                'account_moderation' => 1,
            ],
        ]);

        $out = [];
        foreach ($cursor as $doc) {
            $row = iterator_to_array($doc);
            $row['_id'] = (string) ($row['_id'] ?? '');

            $out[] = $row;
        }

        return $out;
    }
}

if (!function_exists('user_moderation_find_by_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function user_moderation_find_by_id($db, string $userId)
    {
        try {
            $oid = new MongoDB\BSON\ObjectId($userId);
        } catch (Throwable) {
            return null;
        }
        $doc = $db->users->findOne(['_id' => $oid], [
            'projection' => [
                'email' => 1,
                'username' => 1,
                'role' => 1,
                'created_at' => 1,
                'account_moderation' => 1,
            ],
        ]);
        if ($doc === null) {
            return null;
        }
        $row = iterator_to_array($doc);
        $row['_id'] = (string) ($row['_id'] ?? '');

        return $row;
    }
}
