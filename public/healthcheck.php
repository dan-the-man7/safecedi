<?php
/**
 * Web health check for hosts without SSH:
 *     https://YOUR-SITE/healthcheck.php?token=<HEALTHCHECK_TOKEN from .env>
 *
 * Disabled (404) whenever HEALTHCHECK_TOKEN is empty. Blank the token
 * again once the gate has passed.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require APP_ROOT . '/includes/healthcheck.php';

$expected = config('HEALTHCHECK_TOKEN');
$given    = $_GET['token'] ?? '';

if ($expected === null || !is_string($given) || !hash_equals($expected, $given)) {
    render_error_page(404, 'Page not found', 'There is nothing here.');
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
echo format_health_report(run_health_checks(true));
