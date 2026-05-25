<?php

declare(strict_types=1);

/**
 * CLI: gleiche Prüfungen wie Admin → Deploy-Status (ohne Webserver).
 *
 * Usage:
 *   php scripts/deploy-check.php
 *   APP_ENV=production APP_ALLOW_DEV_DB_DEFAULTS=0 php scripts/deploy-check.php
 */

$root = realpath(__DIR__.'/..');
if ($root === false) {
    fwrite(STDERR, "Projektroot nicht gefunden.\n");
    exit(2);
}

chdir($root);

require_once $root.'/includes/deploy_status.php';

$checks = deploy_status_collect_checks();
$fail = 0;
$warn = 0;

echo "Deploy-Check (APP_ENV=".app_environment().", dev_defaults=".(app_allow_dev_db_defaults() ? '1' : '0').")\n";
echo str_repeat('-', 72)."\n";

foreach ($checks as $check) {
    $status = $check['status'];
    if ($status === 'fail') {
        $fail++;
    } elseif ($status === 'warn') {
        $warn++;
    }
    $tag = strtoupper($status);
    echo sprintf("[%s] %s\n", $tag, $check['label']);
    echo "       ".$check['detail']."\n";
    if ($check['hint'] !== '') {
        echo "       → ".$check['hint']."\n";
    }
}

echo str_repeat('-', 72)."\n";
if ($fail > 0) {
    echo "Ergebnis: {$fail} Fehler, {$warn} Hinweise — Exit 1\n";
    exit(1);
}
if ($warn > 0) {
    echo "Ergebnis: OK mit {$warn} Hinweis(en) — Exit 0 (Warnungen)\n";
    exit(0);
}

echo "Ergebnis: alle Prüfungen OK — Exit 0\n";
exit(0);
