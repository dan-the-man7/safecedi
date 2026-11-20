<?php
/**
 * CLI health check:   php ops/healthcheck.php
 * Exit code 0 = no failures, 1 = at least one FAIL (usable in scripts).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/healthcheck.php';

$results = run_health_checks(false);
echo format_health_report($results);
exit(in_array(HC_FAIL, array_column($results, 0), true) ? 1 : 0);
