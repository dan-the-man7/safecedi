# Authentication and sessions: how it works

**Owner: M1.** All five members must be able to explain this in the viva (questions 1, 3 and 8). Read this before Thursday, then open the files and follow along.

## Who can do what

| Visitor | How they got here | Can |
|---|---|---|
| Anonymous | No account at all | Paste a message, get a verdict, browse the scam wall |
| Reporter | Registered on the site | All of the above, plus report scams, vote and flag wrong verdicts |
| Moderator | Created by M1 with `bin/create-user.php` or promoted in SQL | All of the above, plus the moderation queue, rules and allowlist |

Anonymous use is a **product decision**: the person who most needs SafeCedi has just received a frightening SMS and will not stop to make an account. So `index.php` and `check.php` have no login guard. They still use **CSRF tokens and server-side validation**, because a session (with a token in it) is started for every visitor.

## Registration: `public/register.php` and `register_user()`

1. `csrf_verify()` runs before anything else.
2. `validate_registration()` checks on the **server**: a valid email of 254 characters or fewer, a display name of 2–60 characters with no `<`, `>` or control characters, and a password of at least 10 characters and at most 72 bytes, with the confirmation matching.
   - *Why a 72-byte limit?* bcrypt silently ignores everything after byte 72. Without the limit, two different long passwords could both work. We refuse them instead of truncating them silently.
3. `password_hash($password, PASSWORD_DEFAULT)` makes a salted bcrypt hash. The salt is stored inside the hash string, so there is no separate salt column.
4. `INSERT` uses a prepared statement. A duplicate email is caught by the **UNIQUE index**, not by a `SELECT` beforehand. Two people signing up with the same email at the same instant can't both succeed, because the database refuses the second one (SQLSTATE 23000).
5. The new user is logged in straight away (see below).

## Login: `public/login.php` and `attempt_login()`

```
email + password
   │
   ├─ user not found ──► password_verify() against a dummy hash ──► "Email or password is incorrect."
   │                      (same time cost, so timing can't reveal which emails exist)
   ├─ locked_until in future ──► "Too many failed attempts…"
   ├─ password_verify() fails ──► record_failed_login(): one atomic UPDATE;
   │                               the 5th miss sets locked_until = NOW() + 15 min
   └─ success
        ├─ password_needs_rehash()? → store a fresh hash (the only time we hold the plain password)
        ├─ reset failed_login_count, set last_login_at
        └─ log_in_user():
              session_regenerate_id(true)   ← new ID; a planted (fixated) ID becomes useless
              unset csrf_token              ← a token seen while logged out is now worthless
              $_SESSION['user_id'] = id
```

The error message is the **same** for "no such email" and "wrong password".

## Every protected page: `require_login()` and `require_role()`

```php
$user = require_role(ROLE_MODERATOR);   // first line of moderate.php
```

- `current_user()` **re-reads the user from the database on every request**: role, active flag, everything. If a moderator is demoted or an account is disabled, access ends on their very next click, not when the session happens to expire.
- The check sits **on the page itself**. Hiding the "Moderate" link from reporters is just cosmetic. A reporter who types `/moderate.php` gets a 403, and the attempt is logged.
- A logged-out visitor is redirected to login, and returned afterwards to the page they asked for. That return path goes through `safe_local_path()`, so it can never be an open redirect to another site.

## The session cookie: `includes/session.php`

| Setting | Effect |
|---|---|
| `use_strict_mode=1` | PHP refuses session IDs it didn't create |
| `use_only_cookies=1` | No `?PHPSESSID=` in URLs, so IDs don't leak via links or logs |
| `HttpOnly` | JavaScript can't read the cookie, which limits the damage from any XSS |
| `Secure` (on https) | The cookie is never sent over plain http |
| `SameSite=Lax` | Not sent on cross-site POSTs, a second line of defence behind CSRF tokens |
| Idle timeout (30 min) | After inactivity the session is wiped and given a new ID |

## Logout: `public/logout.php`

POST only, with a CSRF token. *Why not a link?* Any other website could embed `<img src="https://our-site/logout.php">` and silently log our users out. `destroy_session()` empties `$_SESSION`, expires the cookie in the browser, and deletes the session file on the server.

## Errors: `includes/errors.php`

Users see a friendly page with a short **reference code**. The log (`storage/logs/app.log`) records the full detail under the same code. Raw PHP warnings never reach the screen in production, even if someone leaves `APP_DEBUG=true` in production by mistake. Passwords are never logged. The database connection error is re-thrown **without** the original exception, because PDO's stack trace would contain the DB password.

## Viva answers in one breath

- **Q8: Where are passwords handled, and what algorithm?** In `includes/auth.php`: `password_hash()` with `PASSWORD_DEFAULT` (bcrypt today, cost 12 on PHP 8.4+, older versions use 10). It's checked with `password_verify()`, and we upgrade old hashes on login with `password_needs_rehash()`. Plain passwords are never stored or logged.
- **Q3: Where is the CSRF token checked?** In `csrf_verify()` (`includes/security.php`), called at the top of every POST handler before any write. It uses `hash_equals()` for a constant-time compare. Without the token, the visitor gets a 403 and nothing is written.
- **What stops a reporter opening the moderator page?** `require_role(ROLE_MODERATOR)` on the first line of `moderate.php`, using the role freshly read from the database.
- **What happens if two people register the same email at once?** The UNIQUE index rejects the second insert, and we turn that into a friendly "already registered" message.
- **Known limitation:** the lockout is per account. An attacker could deliberately lock a moderator out for 15 minutes. Per-IP throttling would be the next step.
