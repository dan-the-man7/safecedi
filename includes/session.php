<?php
/**
 * Session handling  (owner: M1)
 *
 * A session is started for EVERY visitor, logged in or not, because
 * anonymous visitors still need a CSRF token to submit a message.
 */
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');   // refuse session IDs we didn't issue
    ini_set('session.use_only_cookies', '1');  // never accept ?PHPSESSID= in URLs
    ini_set('session.use_trans_sid', '0');

    session_name('safecedi_sid');
    session_set_cookie_params([
        'lifetime' => 0,           // dies when the browser closes
        'path'     => '/',
        'secure'   => is_https(),  // only sent over https on the live site
        'httponly' => true,        // JavaScript can't read it (limits XSS damage)
        'samesite' => 'Lax',       // not sent on cross-site POSTs (CSRF defence in depth)
    ]);
    session_start();

    enforce_idle_timeout();
}

/**
 * After SESSION_IDLE_MINUTES of no requests, wipe the session and issue a
 * new ID. The user is effectively logged out but the page still works.
 */
function enforce_idle_timeout(): void
{
    $idleSeconds = max(1, (int) config('SESSION_IDLE_MINUTES', '30')) * 60;
    $now         = time();
    $lastSeen    = $_SESSION['last_seen'] ?? null;

    if (is_int($lastSeen) && ($now - $lastSeen) > $idleSeconds) {
        $wasLoggedIn = isset($_SESSION['user_id']);
        $_SESSION = [];
        session_regenerate_id(true);
        if ($wasLoggedIn) {
            flash('info', 'You were signed out after a period of inactivity.');
        }
    }

    $_SESSION['last_seen'] = $now;
}

/** Completely end the session: data, server-side file and browser cookie. */
function destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
