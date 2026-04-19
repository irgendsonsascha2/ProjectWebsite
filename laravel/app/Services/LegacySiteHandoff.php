<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

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
        $exp = time() + 120;
        $payload = $uid.'|'.$exp;
        $sig = hash_hmac('sha256', $payload, $secret);

        return $base.'/laravel_handoff.php?'.http_build_query([
            'uid' => $uid,
            'exp' => $exp,
            'sig' => $sig,
        ]);
    }

    public function isConfigured(): bool
    {
        return (string) config('legacy.handoff_secret') !== '';
    }
}
