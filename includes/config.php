<?php
/**
 * Configuration loader  (owner: M1)
 *
 * The pattern:
 *   - This file is COMMITTED. It contains code, never secrets.
 *   - Secrets live in APP_ROOT/.env, which is git-ignored.
 *   - .env.example is committed so anyone can see which keys are needed.
 *
 * Usage:  config('DB_HOST')            -> string|null
 *         config('DB_PORT', '3306')    -> string with a default
 *         config_bool('APP_DEBUG')     -> bool
 *         config_required('DB_NAME')   -> string, throws if missing/empty
 */
declare(strict_types=1);

/**
 * Parse a simple KEY=VALUE .env file. Lines starting with # are comments.
 * Values may optionally be wrapped in single or double quotes.
 *
 * @return array<string,string>
 */
function load_env_file(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException(
            'Configuration file .env is missing. Copy .env.example to .env and fill it in.'
        );
    }

    $values = [];
    $lines  = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue; // not a KEY=VALUE line; ignore rather than crash
        }

        $key   = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));

        $quoted = strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[-1] === $value[0];
        if ($quoted) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * Read one configuration value. A real environment variable with the same
 * name wins over .env (some hosts let you set them in a control panel).
 */
function config(string $key, ?string $default = null): ?string
{
    static $env = null;
    if ($env === null) {
        $env = load_env_file(APP_ROOT . '/.env');
    }

    $fromProcess = getenv($key);
    if ($fromProcess !== false && $fromProcess !== '') {
        return $fromProcess;
    }

    $value = $env[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
}

function config_bool(string $key, bool $default = false): bool
{
    $value = config($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function config_required(string $key): string
{
    $value = config($key);
    if ($value === null) {
        throw new RuntimeException("Required configuration key {$key} is not set in .env");
    }
    return $value;
}

function is_production(): bool
{
    return config('APP_ENV', 'production') === 'production';
}
