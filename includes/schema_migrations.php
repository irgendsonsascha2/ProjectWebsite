<?php

declare(strict_types=1);

/**
 * Tracking ausgeführter DB-Migrationsskripte (nicht-destruktive nummerierte Skripte).
 */

if (!function_exists('schema_migration_script_dir')) {
    function schema_migration_script_dir(): string
    {
        return dirname(__DIR__).'/dbScripts';
    }
}

if (!function_exists('schema_migration_list_numbered_scripts')) {
    /**
     * @return string[] absolute paths, sorted
     */
    function schema_migration_list_numbered_scripts(): array
    {
        $scripts = glob(schema_migration_script_dir().'/[0-9][0-9]*.php');
        if (!is_array($scripts)) {
            return [];
        }
        sort($scripts);

        return $scripts;
    }
}

if (!function_exists('schema_migration_trackable')) {
    function schema_migration_trackable(string $basename): bool
    {
        if ($basename === 'db_init_master.php') {
            return false;
        }
        if (preg_match('/^0[0-2]_/', $basename)) {
            return false;
        }

        return (bool) preg_match('/^\d{2}_/', $basename);
    }
}

if (!function_exists('schema_migration_is_destructive')) {
    function schema_migration_is_destructive(string $basename): bool
    {
        if ($basename === 'db_init_master.php') {
            return true;
        }

        return (bool) preg_match('/^0[0-2]_/', $basename)
            || $basename === '05_db_init_site_pages.php';
    }
}

if (!function_exists('schema_migration_destructive_allowed')) {
    function schema_migration_destructive_allowed(): bool
    {
        $v = getenv('ALLOW_DESTRUCTIVE_DB_SCRIPTS');
        if ($v === false) {
            return false;
        }
        $v = strtolower(trim((string) $v));

        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }
}

if (!function_exists('schema_migration_trackable_basenames')) {
    /**
     * @return string[]
     */
    function schema_migration_trackable_basenames(): array
    {
        $names = [];
        foreach (schema_migration_list_numbered_scripts() as $path) {
            $base = basename($path);
            if (schema_migration_trackable($base)) {
                $names[] = $base;
            }
        }

        return $names;
    }
}

if (!function_exists('schema_migration_collection_exists')) {
    function schema_migration_collection_exists($db): bool
    {
        try {
            foreach ($db->listCollections(['filter' => ['name' => 'schema_migrations']]) as $info) {
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('schema_migration_applied_map')) {
    /**
     * @return array<string, array{applied_at: \MongoDB\BSON\UTCDateTime, applied_by: string}>
     */
    function schema_migration_applied_map($db): array
    {
        if (!schema_migration_collection_exists($db)) {
            return [];
        }

        $map = [];
        $cursor = $db->schema_migrations->find([], ['sort' => ['script' => 1]]);
        foreach ($cursor as $doc) {
            $script = (string) ($doc['script'] ?? '');
            if ($script === '') {
                continue;
            }
            $appliedAt = $doc['applied_at'] ?? null;
            if (!$appliedAt instanceof MongoDB\BSON\UTCDateTime) {
                continue;
            }
            $map[$script] = [
                'applied_at' => $appliedAt,
                'applied_by' => (string) ($doc['applied_by'] ?? ''),
            ];
        }

        return $map;
    }
}

if (!function_exists('schema_migration_summary')) {
    /**
     * @return array{
     *   total: int,
     *   applied_count: int,
     *   pending: string[],
     *   collection_exists: bool,
     *   error: string|null
     * }
     */
    function schema_migration_summary($db): array
    {
        $trackable = schema_migration_trackable_basenames();
        $total = count($trackable);
        $collectionExists = schema_migration_collection_exists($db);
        $error = null;
        $map = [];

        try {
            $map = schema_migration_applied_map($db);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $pending = [];
        foreach ($trackable as $name) {
            if (!isset($map[$name])) {
                $pending[] = $name;
            }
        }

        return [
            'total' => $total,
            'applied_count' => $total - count($pending),
            'pending' => $pending,
            'collection_exists' => $collectionExists,
            'error' => $error,
        ];
    }
}

if (!function_exists('schema_migration_pending_scripts')) {
    /**
     * @return string[] basenames
     */
    function schema_migration_pending_scripts($db): array
    {
        return schema_migration_summary($db)['pending'];
    }
}

if (!function_exists('schema_migration_record')) {
    function schema_migration_record($db, string $script, string $appliedBy): void
    {
        if (!schema_migration_trackable($script)) {
            return;
        }

        $by = trim($appliedBy);
        if ($by === '') {
            $by = 'admin';
        }

        $db->schema_migrations->updateOne(
            ['script' => $script],
            [
                '$set' => [
                    'script' => $script,
                    'applied_at' => new MongoDB\BSON\UTCDateTime(),
                    'applied_by' => $by,
                ],
            ],
            ['upsert' => true]
        );
    }
}

if (!function_exists('schema_migration_format_applied_at')) {
    function schema_migration_format_applied_at(MongoDB\BSON\UTCDateTime $appliedAt): string
    {
        $dt = $appliedAt->toDateTime();
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get() ?: 'UTC'));

        return $dt->format('Y-m-d H:i');
    }
}

if (!function_exists('schema_migration_status_badge_html')) {
    function schema_migration_status_badge_html(string $basename, array $appliedMap): string
    {
        if (schema_migration_is_destructive($basename)) {
            return '<span class="migration-badge migration-badge--destructive" title="Kann Daten löschen oder neu aufsetzen">Destruktiv</span>';
        }
        if (!schema_migration_trackable($basename)) {
            return '';
        }
        if (isset($appliedMap[$basename])) {
            $info = $appliedMap[$basename];
            $when = schema_migration_format_applied_at($info['applied_at']);
            $who = htmlspecialchars($info['applied_by'], ENT_QUOTES, 'UTF-8');
            $title = 'Angewendet am '.$when.' von '.$who;

            return '<span class="migration-badge migration-badge--applied" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">Angewendet</span>';
        }

        return '<span class="migration-badge migration-badge--pending" title="Noch nicht als ausgeführt protokolliert">Ausstehend</span>';
    }
}

if (!function_exists('schema_migration_actor_from_session')) {
    function schema_migration_actor_from_session(): string
    {
        $username = trim((string) ($_SESSION['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return trim((string) ($_SESSION['email'] ?? 'admin'));
    }
}

if (!function_exists('schema_migration_backfill_log')) {
    /**
     * Trägt fehlende trackbare Skripte ins Protokoll ein (z. B. nach älterem Master-Lauf).
     *
     * @return array{added: string[], skipped: string[]}
     */
    function schema_migration_backfill_log($db, ?string $actor = null): array
    {
        $actor = $actor ?? schema_migration_actor_for_run();
        $map = schema_migration_applied_map($db);
        $added = [];
        $skipped = [];

        foreach (schema_migration_trackable_basenames() as $script) {
            if (isset($map[$script])) {
                $skipped[] = $script;
                continue;
            }
            schema_migration_record($db, $script, $actor);
            $map[$script] = true;
            $added[] = $script;
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}

if (!function_exists('schema_migration_wants_backfill_from_input')) {
    function schema_migration_wants_backfill_from_input(): bool
    {
        $input = $GLOBALS['dbScriptInput'] ?? null;
        if (!is_array($input)) {
            return false;
        }

        $v = $input['backfill_migration_log'] ?? false;

        return $v === true || $v === 1 || $v === '1';
    }
}

if (!function_exists('schema_migration_actor_for_run')) {
    function schema_migration_actor_for_run(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $email = trim((string) ($_SESSION['email'] ?? ''));
            $username = trim((string) ($_SESSION['username'] ?? ''));
            if ($username !== '' || $email !== '') {
                return schema_migration_actor_from_session();
            }
        }
        if (isset($GLOBALS['dbScriptActor']) && is_string($GLOBALS['dbScriptActor'])) {
            $actor = trim($GLOBALS['dbScriptActor']);
            if ($actor !== '') {
                return $actor;
            }
        }

        return 'db_init_master';
    }
}
