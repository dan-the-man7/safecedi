<?php
/**
 * Small shared helpers  (owner: M1)
 */
declare(strict_types=1);

/** Longest pasted message accepted (M5's validation table is the source of truth). */
const MESSAGE_MAX_LENGTH = 5000;

/**
 * Redirect and stop. Only relative, same-site paths are accepted so this
 * can never be turned into an open redirect.
 */
function redirect(string $path): never
{
    header('Location: ' . safe_local_path($path, 'index.php'), true, 303);
    exit;
}

/**
 * Accept "account.php" or "/account.php?x=1"; reject "//evil.com",
 * "https://evil.com", "javascript:..." and backslash tricks.
 */
function safe_local_path(?string $path, string $fallback): string
{
    if ($path === null || $path === '') {
        return $fallback;
    }
    $unsafe = str_contains($path, '\\')
        || str_starts_with($path, '//')
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1   // any scheme
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1;         // control chars / header injection
    return $unsafe ? $fallback : $path;
}

/** One-shot messages that survive a redirect (Post/Redirect/Get). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Read a trimmed string from POST; arrays and missing keys become ''. */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Read a string from POST exactly as typed (passwords: never trimmed). */
function post_raw(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}
