<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MongoDB-Integrationstests für idempotente DB-Baseline (Skripte 14/15/16).
 * Voraussetzung: MongoDB auf 127.0.0.1:27017 (lokal: make services-up).
 */
final class DbBaselineTest extends TestCase
{
    private static string $projectRoot;

    /** @var array<string, true> */
    private static array $baselineScripts = [
        '14_db_init_handoff_tokens.php' => true,
        '15_db_init_security_baseline.php' => true,
        '16_db_init_schema_migrations.php' => true,
    ];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(__DIR__.'/../..');
        if ($root === false) {
            throw new RuntimeException('Projektroot nicht gefunden.');
        }
        self::$projectRoot = $root;

        require_once $root.'/includes/db.php';
        require_once $root.'/includes/schema_migrations.php';
        require_once $root.'/includes/handoff_tokens.php';
    }

    public function test_admin_mongo_reachable(): void
    {
        [, $db] = get_admin_mongo_connection();
        $db->command(['ping' => 1]);
        $this->assertIsIterable($db->listCollections());
    }

    public function test_ensure_db_baseline_succeeds(): int
    {
        $exitCode = self::runBaselineScript();
        $this->assertSame(0, $exitCode, 'ensure-db-baseline.php muss mit Exit 0 enden.');

        return $exitCode;
    }

    /**
     * @depends test_ensure_db_baseline_succeeds
     */
    public function test_baseline_is_idempotent(int $firstRunExitCode): void
    {
        $this->assertSame(0, $firstRunExitCode);

        $output = [];
        $exitCode = self::runBaselineScript($output);
        $this->assertSame(0, $exitCode, 'Zweiter Baseline-Lauf muss Exit 0 haben.');
        $joined = implode("\n", $output);
        $this->assertStringContainsString(
            'Überspringe',
            $joined,
            'Idempotenter Lauf sollte protokollierte Skripte überspringen.'
        );
    }

    /**
     * @depends test_ensure_db_baseline_succeeds
     */
    public function test_schema_migrations_logged(int $firstRunExitCode): void
    {
        $this->assertSame(0, $firstRunExitCode);

        [, $db] = get_admin_mongo_connection();
        $map = schema_migration_applied_map($db);

        foreach (array_keys(self::$baselineScripts) as $script) {
            $this->assertArrayHasKey(
                $script,
                $map,
                "schema_migrations sollte Eintrag für {$script} haben."
            );
        }
    }

    /**
     * @depends test_ensure_db_baseline_succeeds
     */
    public function test_handoff_token_indexes_exist(int $firstRunExitCode): void
    {
        $this->assertSame(0, $firstRunExitCode);

        [, $db] = get_admin_mongo_connection();
        $names = self::indexNames($db, 'handoff_tokens');

        $this->assertContains('handoff_expires_ttl', $names);
        $this->assertContains('handoff_nonce_unique', $names);
    }

    /**
     * @param list<string> $outputLines
     */
    private static function runBaselineScript(array &$outputLines = []): int
    {
        $script = self::$projectRoot.'/scripts/ensure-db-baseline.php';
        $cmd = 'php '.escapeshellarg($script).' 2>&1';
        $lines = [];
        $exitCode = 0;
        exec($cmd, $lines, $exitCode);
        $outputLines = $lines;

        return $exitCode;
    }

    /**
     * @return list<string>
     */
    private static function indexNames(\MongoDB\Database $db, string $collection): array
    {
        $names = [];
        foreach ($db->{$collection}->listIndexes() as $index) {
            $names[] = (string) $index->getName();
        }

        return $names;
    }
}
