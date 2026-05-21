<?php

use MongoDB\Database;

if (! function_exists('handoff_token_create')) {
    /**
     * Speichert einen einmaligen Handoff-Nonce (Admin-DB).
     *
     * @return string 32-stelliger Hex-Nonce
     */
    function handoff_token_create(Database $db, string $userId, int $expUnix): string
    {
        $nonce = bin2hex(random_bytes(16));
        $db->handoff_tokens->insertOne([
            'nonce' => $nonce,
            'user_id' => $userId,
            'exp' => $expUnix,
            'expires_at' => new MongoDB\BSON\UTCDateTime($expUnix * 1000),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);

        return $nonce;
    }
}

if (! function_exists('handoff_token_consume')) {
    /**
     * Löscht den Token atomar; gibt user_id zurück wenn gültig und nicht abgelaufen.
     */
    function handoff_token_consume(Database $db, string $nonce, int $expUnix): ?string
    {
        if ($nonce === '' || strlen($nonce) !== 32 || ! ctype_xdigit($nonce)) {
            return null;
        }

        $doc = $db->handoff_tokens->findOneAndDelete([
            'nonce' => $nonce,
            'exp' => $expUnix,
        ]);

        if ($doc === null) {
            return null;
        }

        $uid = (string) ($doc['user_id'] ?? '');
        if ($uid === '') {
            return null;
        }

        if ($expUnix < time()) {
            return null;
        }

        return $uid;
    }
}

if (! function_exists('handoff_token_ensure_indexes')) {
    function handoff_token_ensure_indexes(Database $db): void
    {
        try {
            $db->handoff_tokens->createIndex(
                ['expires_at' => 1],
                ['expireAfterSeconds' => 0, 'name' => 'handoff_expires_ttl']
            );
        } catch (Throwable $e) {
            // Index kann bereits existieren
        }
        try {
            $db->handoff_tokens->createIndex(['nonce' => 1], ['unique' => true, 'name' => 'handoff_nonce_unique']);
        } catch (Throwable $e) {
        }
    }
}
