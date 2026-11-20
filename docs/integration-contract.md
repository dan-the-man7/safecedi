# Integration contract

**Owner: M1.** This file lists the function names and file locations each slice codes against, so five people can work in parallel and the nightly merge doesn't turn into a rewrite. If you need to change a line here, raise it at the 9:00 PM merge first.

## Every page

```php
<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';   // config, errors, session, helpers, auth
```

## Provided by M1: auth and plumbing

| Function | Use |
|---|---|
| `config('KEY', $default)`, `config_bool()`, `config_required()` | Read `.env` values. Never hard-code a secret |
| `current_user(): ?array` | `['id','email','display_name','role',…]` or `null` for anonymous |
| `require_login(): array` | First line of any page that needs an account |
| `require_role(ROLE_MODERATOR): array` | First line of any moderator page |
| `is_logged_in()`, `has_role(...)` | For **showing or hiding** UI only, never as the access check |
| `redirect('page.php')`, `flash('success'\|'error'\|'info', $msg)` | Post/Redirect/Get |
| `post_string('field')`, `post_raw('field')` | Safe reads from `$_POST` (arrays become `''`) |
| `app_log('info'\|'warning'\|'error', $msg, $context)` | Logging. Never log passwords, keys or full messages from users |
| `render_error_page($status, $title, $message)` | Friendly error and stop |

## Provided by M2: data (`includes/db.php`, `database/`)

| | |
|---|---|
| `db(): PDO` | Exceptions on, real prepared statements, utf8mb4, UTC |
| `db_run($sql, $params)`, `db_one($sql, $params)` | Prepared-statement helpers |
| `database/schema.sql`, `database/seed.sql` | Must rebuild an empty database from scratch |

## Provided by M3: engine (`engine/`)

M1's `public/check.php` already calls this as soon as the file exists:

```php
// engine/evaluate.php
function evaluate(string $text): array
// returns:
[
  'score'   => 42,                       // int
  'band'    => 'Suspicious',             // Looks Safe | Unclear | Suspicious | Dangerous (may be null in Phase 01)
  'signals' => [
     ['rule' => 'PIN_SOLICITATION', 'weight' => 25, 'match' => 'enter your PIN'],
     ...
  ],
]
```

`evaluate()` stays a **pure function**: no database, no network, no `$_POST`. That's what makes M5's corpus harness possible. The SSRF guard lives in its own function in `engine/`, and every outbound fetch of a user-supplied URL must go through it.

## Provided by M4: interface (`templates/`, `public/assets/`)

- Replace `public/assets/css/app.css` with the token file, keeping the class names or updating the templates in the same PR.
- `templates/header.php` decides **what the nav shows to whom**. Keep the `$viewer` logic and the logout `<form method="post">` intact.

## Provided by M5: security and QA (`includes/security.php`, `tests/`)

| | |
|---|---|
| `e($value)` | Escape at the point of printing |
| `csrf_field()`, `csrf_verify()` | Every POST form, every POST handler |
| `require_post()` | Reject non-POST on action endpoints |

M1 wrote the first version of these so auth could ship. M5 owns, documents and may harden them. The **names** stay the same.
