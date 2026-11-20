<?php
/**
 * Error handling  (owner: M1)
 *
 * Engineering spine rule: "Errors logged, not displayed."
 *   - Users see a friendly page with a short reference code.
 *   - The log (storage/logs/app.log) gets the full detail plus that code,
 *     so a user's screenshot can be matched to the exact log entry.
 *   - With APP_DEBUG=true (local only) the detail is also shown on screen.
 */
declare(strict_types=1);

function log_path(): string
{
    return APP_ROOT . '/storage/logs/app.log';
}

/** Write one line to the application log. Never pass secrets in here. */
function app_log(string $level, string $message, array $context = []): void
{
    $line = sprintf(
        "[%s] %s: %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );
    // error_log type 3 appends to a file; fall back to the PHP log if unwritable.
    if (!@error_log($line, 3, log_path())) {
        error_log(rtrim($line));
    }
}

/** True only when debug output is explicitly enabled AND config is readable. */
function app_debug(): bool
{
    try {
        return config_bool('APP_DEBUG') && !is_production();
    } catch (Throwable) {
        return false; // config itself is broken: fail closed, show nothing
    }
}

function install_error_handlers(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', '0');      // never print raw PHP warnings
    ini_set('log_errors', '1');
    ini_set('error_log', log_path());

    // Turn warnings/notices into exceptions so nothing fails silently.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // respects the @ operator
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $e): void {
        $ref = strtoupper(bin2hex(random_bytes(3)));
        app_log('error', get_class($e) . ': ' . $e->getMessage(), [
            'ref'  => $ref,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'uri'  => $_SERVER['REQUEST_URI'] ?? 'cli',
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Error [{$ref}]: {$e->getMessage()}\n");
            exit(1);
        }

        render_error_page(500, 'Something went wrong on our side',
            'The problem has been logged. Please try again in a moment.', $ref, $e);
    });
}

/**
 * Self-contained error page: it must work even when the layout, the
 * database or the configuration is the thing that is broken.
 */
function render_error_page(int $status, string $title, string $message, ?string $ref = null, ?Throwable $e = null): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    // Discard any half-rendered page underneath the error.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $detail = ($e !== null && app_debug())
        ? '<pre class="debug">' . $esc(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>'
        : '';
    $refHtml = $ref ? '<p class="ref">Reference: <code>' . $esc($ref) . '</code></p>' : '';

    echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>{$esc($title)} · SafeCedi</title>
      <style>
        body{font-family:system-ui,sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem;color:#1d2433;line-height:1.5}
        h1{font-size:1.4rem} .ref{color:#5b6475} a{color:#0b5fff}
        .debug{background:#fff4f4;border:1px solid #f2c4c4;padding:1rem;overflow:auto;font-size:.8rem;white-space:pre-wrap}
      </style>
    </head>
    <body>
      <h1>{$esc($title)}</h1>
      <p>{$esc($message)}</p>
      {$refHtml}
      <p><a href="index.php">Back to SafeCedi</a></p>
      {$detail}
    </body>
    </html>
    HTML;
    exit;
}
