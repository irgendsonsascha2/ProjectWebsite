<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use MongoDB\BSON\UTCDateTime;

class LegacySiteHandoff
{
    /**
     * Absolute URL zur alten Website inkl. signiertem Kurzlink (laravel_handoff.php).
     */
    public function redirectUrl(User $user): string
    {
        $secret = (string) config('legacy.handoff_secret', '');
        $base = (string) config('legacy.site_url', '');

        if ($secret === '' || $base === '') {
            return URL::to('/');
        }

        $uid = (string) $user->getAuthIdentifier();
        $exp = time() + 60;
        $nonce = bin2hex(random_bytes(16));

        $this->storeNonce($uid, $exp, $nonce);

        $payload = $uid.'|'.$exp.'|'.$nonce;
        $sig = hash_hmac('sha256', $payload, $secret);

        return $base.'/laravel_handoff.php?'.http_build_query([
            'uid' => $uid,
            'exp' => $exp,
            'nonce' => $nonce,
            'sig' => $sig,
        ]);
    }

    public function isConfigured(): bool
    {
        return (string) config('legacy.handoff_secret') !== '';
    }

    private function storeNonce(string $userId, int $exp, string $nonce): void
    {
        try {
            DB::connection('mongodb')->getCollection('handoff_tokens')->insertOne([
                'nonce' => $nonce,
                'user_id' => $userId,
                'exp' => $exp,
                'expires_at' => new UTCDateTime($exp * 1000),
                'created_at' => new UTCDateTime,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
