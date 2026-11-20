<?php
/**
 * Escaping, CSRF and HTTP hardening.
 *
 * e() and the csrf_* helpers are OWNED BY M5 (Security, QA & Evidence).
 * M1 wrote this first version so the auth forms could ship on Thu 19 Nov;
 * M5 reviews, documents and may replace the internals — the function
 * names are the contract everyone codes against, so keep them stable.
 */
declare(strict_types=1);

/**
 * Escape a value for HTML output. Use at the point of printing, on every
 * value that came from a user:  <?= e($user['display_name']) ?>
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** The per-session CSRF token (created on first use). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every state-changing <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Call at the top of every POST handler, BEFORE anything is written.
 * hash_equals() compares in constant time so the token can't be guessed
 * byte-by-byte from response timings.
 */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        app_log('warning', 'CSRF check failed', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        render_error_page(403, 'This form has expired',
            'Please go back, refresh the page and try again.');
    }
}

/** Reject anything that isn't POST (e.g. logout via a GET link or <img>). */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        render_error_page(405, 'Method not allowed', 'This action must be submitted from a form.');
    }
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Many shared hosts terminate SSL at a proxy and pass this header on.
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** With FORCE_HTTPS=true, bounce plain-http requests to https. */
function enforce_https(): void
{
    if (!config_bool('FORCE_HTTPS') || is_https()) {
        return;
    }
    $host = (string) parse_url((string) config('APP_URL', ''), PHP_URL_HOST);
    if ($host === '') {
        return; // APP_URL not configured; don't trust the Host header blindly
    }
    $uri = safe_local_path($_SERVER['REQUEST_URI'] ?? '/', '/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');          // no MIME sniffing
    header('X-Frame-Options: DENY');                    // no clickjacking via iframes
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000'); // stay on https
    }
}
