<?php
/**
 * Authentication and authorisation  (owner: M1)
 *
 * Who can do what in SafeCedi:
 *   anonymous  - paste a message and get a verdict, browse the scam wall
 *   reporter   - all of the above + file reports, vote, give feedback
 *   moderator  - all of the above + review reports, manage rules/allowlist
 *
 * The spine rules implemented here:
 *   - password_hash()/password_verify() only. Never MD5, never home-made.
 *   - session_regenerate_id(true) on login  -> defeats session fixation.
 *   - session destroyed on logout (POST + CSRF, never a GET link).
 *   - role checked on EVERY protected page via require_role(), and the role
 *     is re-read from the database each request, so a demoted moderator
 *     loses access immediately, not when their session happens to expire.
 */
declare(strict_types=1);

const ROLE_REPORTER  = 'reporter';
const ROLE_MODERATOR = 'moderator';
const ROLES          = [ROLE_REPORTER, ROLE_MODERATOR];

const PASSWORD_MIN_LENGTH = 10;
const PASSWORD_MAX_BYTES  = 72;   // bcrypt silently ignores bytes after 72 — so we refuse them
const MAX_FAILED_LOGINS   = 5;
const LOCKOUT_MINUTES     = 15;

/**
 * A real bcrypt hash of a random string nobody knows. When the email isn't
 * found we still run password_verify() against it, so "no such user" takes
 * as long as "wrong password" and response timing can't reveal which
 * emails are registered.
 */
const TIMING_DUMMY_HASH = '$2y$12$Wnwv3AUnMXE3wvD4EC5dj.v8/cwokRNb0V0ILoZ7jb8aORk/jvgee';

function normalise_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Server-side validation for the registration form. The browser may check
 * the same things for convenience, but this is the gatekeeper.
 *
 * @return array<string,string> field => error message (empty = valid)
 */
function validate_registration(string $email, string $name, string $password, string $confirm): array
{
    $errors = [];

    if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    $nameLength = mb_strlen($name);
    if ($nameLength < 2 || $nameLength > 60) {
        $errors['display_name'] = 'Display name must be 2 to 60 characters.';
    } elseif (preg_match('/[\x00-\x1F\x7F<>]/u', $name) === 1) {
        $errors['display_name'] = 'Display name contains characters that are not allowed.';
    }

    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (strlen($password) > PASSWORD_MAX_BYTES) {
        $errors['password'] = 'Password is too long (maximum ' . PASSWORD_MAX_BYTES . ' bytes).';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    return $errors;
}

/**
 * Create an account and return its id. Throws DomainException if the
 * email is already registered (caught by the register page).
 */
function register_user(string $email, string $displayName, string $password, string $role = ROLE_REPORTER): int
{
    if (!in_array($role, ROLES, true)) {
        throw new InvalidArgumentException("Unknown role: {$role}");
    }

    try {
        db_run(
            'INSERT INTO users (email, display_name, password_hash, role) VALUES (?, ?, ?, ?)',
            [normalise_email($email), $displayName, password_hash($password, PASSWORD_DEFAULT), $role]
        );
    } catch (PDOException $e) {
        // 23000 = integrity constraint violation -> the UNIQUE(email) index fired.
        // Relying on the index (not a SELECT first) means two simultaneous
        // sign-ups with the same email can't both succeed.
        if ($e->getCode() === '23000') {
            throw new DomainException('email_taken');
        }
        throw $e;
    }

    $id = (int) db()->lastInsertId();
    app_log('info', 'User registered', ['user_id' => $id, 'role' => $role]);
    return $id;
}

/**
 * Check credentials. Returns the user row on success, or a user-facing
 * error string on failure. Deliberately vague: the same message for
 * "no such email" and "wrong password".
 *
 * @return array{ok:bool, user?:array, error?:string}
 */
function attempt_login(string $email, string $password): array
{
    $generic = 'Email or password is incorrect.';

    if ($email === '' || $password === '' || strlen($password) > PASSWORD_MAX_BYTES) {
        password_verify($password, TIMING_DUMMY_HASH);
        return ['ok' => false, 'error' => $generic];
    }

    $user = db_one(
        'SELECT id, email, display_name, password_hash, role, is_active,
                failed_login_count,
                (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked
           FROM users
          WHERE email = ?',
        [normalise_email($email)]
    );

    if ($user === null) {
        password_verify($password, TIMING_DUMMY_HASH);
        return ['ok' => false, 'error' => $generic];
    }

    if ((int) $user['is_locked'] === 1) {
        app_log('warning', 'Login attempt on locked account', ['user_id' => (int) $user['id']]);
        return ['ok' => false, 'error' =>
            'Too many failed attempts. Try again in ' . LOCKOUT_MINUTES . ' minutes.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        record_failed_login((int) $user['id']);
        return ['ok' => false, 'error' => $generic];
    }

    if ((int) $user['is_active'] !== 1) {
        return ['ok' => false, 'error' => 'This account has been disabled.'];
    }

    // If PHP's default algorithm or cost has improved since this hash was
    // made, upgrade it now — the only moment we hold the plain password.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    db_run('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?',
        [$user['id']]);

    unset($user['password_hash'], $user['is_locked'], $user['failed_login_count']);
    return ['ok' => true, 'user' => $user];
}

/**
 * Count a failure in ONE atomic UPDATE (no read-then-write race). On the
 * MAX_FAILED_LOGINS-th failure the account locks for LOCKOUT_MINUTES and
 * the counter resets.
 *
 * Order matters: MySQL evaluates SET assignments left to right, and later
 * ones see earlier results. locked_until is computed FIRST, so it reads the
 * counter's old value; then the counter itself is updated.
 */
function record_failed_login(int $userId): void
{
    db_run(
        'UPDATE users
            SET locked_until = IF(failed_login_count + 1 >= ?, NOW() + INTERVAL ? MINUTE, locked_until),
                failed_login_count = IF(failed_login_count + 1 >= ?, 0, failed_login_count + 1)
          WHERE id = ?',
        [MAX_FAILED_LOGINS, LOCKOUT_MINUTES, MAX_FAILED_LOGINS, $userId]
    );
    app_log('warning', 'Failed login', ['user_id' => $userId]);
}

/** Put a verified user into the session. */
function log_in_user(array $user): void
{
    // New session ID: any ID an attacker planted before login is now useless.
    session_regenerate_id(true);
    // New CSRF token too, so a token captured while logged out is worthless.
    unset($_SESSION['csrf_token']);

    $_SESSION['user_id']      = (int) $user['id'];
    $_SESSION['logged_in_at'] = time();
    current_user(true);

    app_log('info', 'User logged in', ['user_id' => (int) $user['id']]);
}

function log_out_user(): void
{
    $userId = $_SESSION['user_id'] ?? null;
    destroy_session();
    current_user(true);
    app_log('info', 'User logged out', ['user_id' => $userId]);
}

/**
 * The logged-in user, freshly read from the database (cached for the rest
 * of this request), or null for anonymous visitors.
 */
function current_user(bool $refresh = false): ?array
{
    static $cached = false; // false = not looked up yet this request

    if ($refresh) {
        $cached = false;
    }
    if ($cached !== false) {
        return $cached;
    }

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return $cached = null;
    }

    $user = db_one(
        'SELECT id, email, display_name, role, created_at, last_login_at
           FROM users WHERE id = ? AND is_active = 1',
        [$id]
    );

    if ($user === null) {
        // Account deleted or disabled since login: drop the stale session.
        unset($_SESSION['user_id'], $_SESSION['logged_in_at']);
        session_regenerate_id(true);
    }

    return $cached = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

/**
 * Guard for pages that need any account. Remembers where the visitor was
 * heading so login can send them back there.
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended_url'] = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))
                . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $user;
}

/**
 * Guard for role-restricted pages. Put it on the FIRST line of the page
 * itself — hiding the nav link is not access control.
 *
 *     $user = require_role(ROLE_MODERATOR);
 */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        app_log('warning', 'Forbidden: role check failed', [
            'user_id' => (int) $user['id'], 'role' => $user['role'],
            'needed'  => $roles, 'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        render_error_page(403, 'You do not have access to this page',
            'This area is for ' . implode(' or ', $roles) . ' accounts only.');
    }
    return $user;
}

/** Redirect target after login: the remembered page, or the account page. */
function take_intended_url(): string
{
    $url = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);
    return safe_local_path(is_string($url) ? $url : null, 'account.php');
}
