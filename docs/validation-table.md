# Input validation table

**Owner: M5 (Security, QA & Evidence).** Phase 01 deliverable, first written 19 Nov 2026. In Phase 02, M5 signs off every POST handler against this table, field by field.

Browser checks (HTML attributes, jQuery) are only for convenience. **Every rule below is enforced in PHP on the server**, because anyone can send a request without using our form.

## Rules every form follows

- Every input is validated on the server.
- Required fields that are empty are rejected.
- Lengths are capped so nobody can send huge submissions.
- Values from a fixed list (votes, statuses, roles) must match the list exactly. Anything else is rejected.
- Reading `$_POST` goes through `post_string()` (trimmed) or `post_raw()` (passwords, never trimmed). If someone sends an array instead of a string, both return `''`, so it can't crash the page.
- Every state-changing form carries a CSRF token, checked with `csrf_verify()` before anything else.
- Every database query uses prepared statements.
- Every user-supplied value is escaped with `e()` when printed.
- URLs pass the SSRF guard before anything fetches them.

## The table

**Status** says where each rule stands:
- **Enforced:** it is in the code now, and the file is named.
- **Proposed:** the limit still needs agreeing with the slice owner, and the code doesn't exist yet.

| Field | Page | Expected data | Server-side rule | On failure | Status |
|---|---|---|---|---|---|
| Email (register) | `register.php` | Email address | Required; valid format (`FILTER_VALIDATE_EMAIL`); at most 254 characters; stored lowercase | "Enter a valid email address." | Enforced: `validate_registration()` |
| Email (login) | `login.php` | Email address | Required; lowercased before lookup | Generic "Email or password is incorrect." (never says which) | Enforced: `attempt_login()` |
| Display name | `register.php` | Text | 2 to 60 characters; no `<`, `>` or control characters | "Display name must be 2 to 60 characters." | Enforced: `validate_registration()` |
| Password | `register.php` | Text | At least 10 characters; at most 72 bytes (bcrypt ignores anything after byte 72, so longer passwords are refused, not silently cut); never trimmed | "Password must be at least 10 characters." / "Password is too long" | Enforced: `validate_registration()` |
| Confirm password | `register.php` | Text | Must equal the password (`hash_equals`) | "The two passwords do not match." | Enforced |
| Password (login) | `login.php` | Text | Required; at most 72 bytes; 5 failures lock the account for 15 minutes | Generic error; "Too many failed attempts" when locked | Enforced: `attempt_login()` |
| Submitted message | `check.php` | SMS or WhatsApp text | Required; valid UTF-8; at most 5,000 characters (`MESSAGE_MAX_LENGTH`); null bytes removed | "Paste a message to check." / "That message is too long" | Enforced: `public/check.php` |
| URL found in a message | engine | Web address | `http` or `https` only; no `user:pass@`; ports 80 and 443 only; no numeric-encoded hosts; every resolved IP must be public | Not fetched; refusal reason recorded | Enforced: `SsrfGuard::check()` (M3) |
| Reported phone number | report form | Ghanaian mobile number | Normalise to `233XXXXXXXXX` (strip spaces, `+`, leading `0`); must then be 12 digits starting `233` | "Enter a valid Ghanaian phone number." | Proposed (M2 and M5 to confirm) |
| Reported link | report form | Web address | Valid `http`/`https` URL; at most 2,048 characters | "Enter a valid link." | Proposed |
| Report description | report form | Text | Required; at most 1,000 characters (proposed) | Validation message | Proposed |
| Report vote | scam wall | Vote | Must be exactly one of the allowed values (e.g. `up`, `down`); one vote per user per report | Rejected | Proposed |
| Feedback ("this was wrong") | result page | Category and optional text | Category from a fixed list; text at most 500 characters (proposed) | Rejected | Proposed |
| Moderator decision | moderation queue | Status | Must be one of the allowed statuses (e.g. `approved`, `rejected`); moderator role required | Rejected; 403 for non-moderators | Proposed |
| Rule pattern | rules admin | Regex | Required; must compile (`@preg_match($pattern, '') !== false`); at most 500 characters (proposed) | "That pattern is not a valid regular expression." | Proposed |
| Rule weight | rules admin | Whole number | Integer; range to agree with M3 (proposed 0 to 50) | Rejected if out of range | Proposed |
| Logout | `logout.php` | — | POST only (`require_post()`), with a CSRF token | 405 for GET; 403 for a bad token | Enforced |

> **(M1 review)** The "Proposed" limits are suggestions so each field has a real number to test against. Agree them with M2 (column sizes) and M3 (rule weights), then change the status to Enforced when the code lands, naming the file.

## CSRF protection and output escaping: how every page uses them

These helpers are in `includes/security.php` and are loaded on every page by `includes/bootstrap.php`. Use them as they are. Don't write a second version.

**In every form that changes something:**

```php
<form method="post" action="check.php">
    <?= csrf_field() ?>
    <textarea name="message" required></textarea>
    <button type="submit">Check message</button>
</form>
```

**At the top of every handler that changes something, before any write:**

```php
require __DIR__ . '/../includes/bootstrap.php';
require_post();   // anything but POST gets 405
csrf_verify();    // missing or wrong token gets 403, nothing is written
```

**Whenever a value from a user is printed:**

```php
<p><?= e($submission['text']) ?></p>
```

`e()` turns `<script>alert("Hacked")</script>` into text the browser shows rather than runs.

## Conclusion

Validation keeps bad data out, and escaping on output makes any that slips through harmless. Browser checks help honest users. The server checks protect us from everyone else.
