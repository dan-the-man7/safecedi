<?php
/**
 * Bootstrap  (owner: M1)
 *
 * Every page in public/ starts with exactly one line:
 *     require __DIR__ . '/../includes/bootstrap.php';
 *
 * It wires up, in this order:
 *   1. error handling  - errors are LOGGED, never shown to users in production
 *   2. configuration   - .env via includes/config.php
 *   3. HTTPS + headers - redirect to https when FORCE_HTTPS=true
 *   4. the session     - hardened cookie, idle timeout
 *   5. shared helpers  - db(), e(), csrf_*(), auth functions
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('UTC'); // Ghana is UTC+0; matches the DB session time zone

require APP_ROOT . '/includes/config.php';
require APP_ROOT . '/includes/errors.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/security.php';
require APP_ROOT . '/includes/session.php';
require APP_ROOT . '/includes/auth.php';

install_error_handlers();

if (PHP_SAPI !== 'cli') {
    enforce_https();
    send_security_headers();
    start_secure_session();
}
