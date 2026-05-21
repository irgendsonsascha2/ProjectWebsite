<?php

use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

if (! function_exists('registration_code_create')) {
    /**
     * Erzeugt einen eindeutigen Einladungscode (16 Hex-Zeichen) für die angegebene Rolle.
     *
     * @return string|null Code bei Erfolg, null wenn nach mehreren Versuchen kein eindeutiger Code möglich war
     */
    function registration_code_create(Database $db, string $role): ?string
    {
        try {
            $db->registration_codes->createIndex(['code' => 1], ['unique' => true]);
        } catch (Exception $e) {
            // Index kann fehlen auf Legacy-DBs — Insert-Retry fängt Duplikate ab
        }

        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = strtoupper(bin2hex(random_bytes(8)));
            try {
                $db->registration_codes->insertOne([
                    'code' => $candidate,
                    'role' => $role,
                    'is_used' => false,
                    'created_at' => new UTCDateTime(),
                ]);

                return $candidate;
            } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
                $writeResult = $e->getWriteResult();
                $writeErrors = $writeResult ? $writeResult->getWriteErrors() : [];
                $isDuplicate = false;
                foreach ($writeErrors as $we) {
                    if (method_exists($we, 'getCode') && (int) $we->getCode() === 11000) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if ($isDuplicate) {
                    continue;
                }
                throw $e;
            }
        }

        return null;
    }
}

if (! function_exists('registration_code_register_link')) {
    function registration_code_register_link(string $code): string
    {
        if (! function_exists('legacy_index_url')) {
            require_once __DIR__.'/app_env.php';
        }

        return legacy_index_url([
            'page' => 'register',
            'reg_token' => $code,
        ]).'#register-section';
    }
}
