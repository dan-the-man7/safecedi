# Threat model

**Owner: M5 (Security, QA & Evidence).** Phase 01 deliverable, first written 19 Nov 2026. Notes marked **(M1 review)** were added during integration. M5 should confirm, edit or remove them.

## 1. What SafeCedi is, from an attacker's point of view

SafeCedi helps people in Ghana spot fraudulent mobile-money messages, suspicious links and phishing. Anyone can paste a suspicious SMS or WhatsApp message, with no account needed, and get a risk band (Looks Safe, Unclear, Suspicious, Dangerous) with the evidence behind it. It looks at language patterns, sender details, URLs and reputation data.

Two things make it a target:

- It **accepts text from anyone**, including people who are not logged in. The submission form is the main attack surface.
- It will **fetch URLs that strangers choose**, to follow redirect chains. That makes server-side request forgery (SSRF) the headline risk.

## 2. Assets to protect

| Asset | What it is | Why it matters |
|---|---|---|
| User accounts | Login credentials and account details | Prevent unauthorised access |
| Submitted messages | Messages pasted in for analysis | User privacy |
| Extracted URLs | Links pulled out of submitted messages | Prevent malicious outbound requests |
| Verdict records | Scores, risk bands and evidence | Accuracy and integrity |
| Database | Users, submissions, reports, rules, feedback | Prevent unauthorised reading or changes |
| API credentials | Google Safe Browsing API key | Prevent abuse of our quota |
| Moderation system | Tools moderators use to manage reports | Prevent unauthorised admin actions |

## 3. Threats

### Threat 1: Server-side request forgery (SSRF)

**What it is.** An attacker tricks the server into making requests to internal or restricted addresses. SafeCedi follows links from submitted messages. Without checks, someone could submit a link pointing at `127.0.0.1`, a private network address, or the cloud metadata address `169.254.169.254`, and our server would fetch it for them.

**Impact**
- Access to internal services
- Exposure of server information or cloud credentials
- Access to restricted network resources
- Possible compromise of the hosting environment

**Mitigations**
- Resolve the hostname before making any HTTP request
- Refuse private, loopback and link-local addresses
- Allow only `http` and `https`
- At most five redirect hops
- Re-check the destination address on **every** hop, not just the first
- Timeouts and response-size limits
- Log refused requests

**Where it lives in the code.** `engine/SsrfGuard.php` (M3): `SsrfGuard::check()` decides whether a URL may be fetched. It has 5 hops, 256 KB and 5 seconds as constants, and `pinnedResolveEntry()` makes cURL connect to the address that was checked, so DNS can't change between the check and the fetch.

> **(M1 review)** The guard returns a `reason` but does not log anything itself. Whoever writes the link resolver (M3, Phase 03) must call `app_log('warning', 'ssrf_refused', [...])` on every refusal, or test TC15 can't pass.

SSRF is the headline risk in the project plan.

### Threat 2: SQL injection

**What it is.** An attacker puts SQL syntax into an input to change what a database query does. Possible entry points: submitted messages, URLs, login forms, report submissions.

**Impact.** Records read, changed or deleted, and user data exposed.

**Mitigations**
- PDO prepared statements for every query: `db_run()` and `db_one()` in `includes/db.php` (real prepared statements, emulation off)
- Never join user input into SQL strings
- Validate input on the server
- The production database user gets only `SELECT, INSERT, UPDATE, DELETE` (see `docs/deploy-guide.md`, Step 2)

### Threat 3: Cross-site scripting (XSS)

**What it is.** Malicious script injected through user content runs in another user's browser. SafeCedi stores and redisplays messages, URLs, reports and feedback, and scam messages are by nature hostile text.

**Impact.** Session theft, actions taken in someone else's name, tampered pages, compromised moderator accounts.

**Mitigations**
- Escape every user-supplied value at the point of printing with `e()` (`includes/security.php`, which uses `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8)
- Never insert untrusted content into HTML, attributes or JavaScript unescaped
- Validate input before storing it
- The session cookie is `HttpOnly`, so a script can't read it
- A Content Security Policy

> **(M1 review)** The site does not send a Content-Security-Policy header yet. Adding one to `send_security_headers()` is an M5 task. It must allow Google Fonts (M4's CSS imports them) and wherever jQuery is loaded from.

### Threat 4: Cross-site request forgery (CSRF)

**What it is.** Another website tricks a logged-in user's browser into submitting a form to SafeCedi, for example making a moderator approve or delete a report without knowing.

**Impact.** Reports changed, account changes, unauthorised moderation.

**Mitigations**
- A random token per session: `bin2hex(random_bytes(32))`
- The token in every state-changing form (`csrf_field()`)
- Checked on the server before any write (`csrf_verify()`, a constant-time `hash_equals` compare; a missing or wrong token gets a 403)
- Logout is a POST form with a token, not a link
- Session cookie is `SameSite=Lax`, a second line of defence

### Threat 5: Unauthorised access

**What it is.** Someone reaches features their role doesn't allow, for example a reporter typing `/moderate.php`.

**Impact.** Exposed user data, changed reports, abused moderator powers.

**Mitigations**
- Role-based access: `require_login()` and `require_role(ROLE_MODERATOR)` on the first line of every protected page
- The role is re-read from the database on every request, so a demoted moderator loses access on their next click
- Passwords hashed with `password_hash()` and checked with `password_verify()`
- Login locked for 15 minutes after 5 failures
- Session ID regenerated on login (stops session fixation); 30-minute idle timeout

### Threat 6: Denial of service and resource abuse

**What it is.** Flooding the site with large messages or many URLs. Link resolution and reputation lookups are the expensive part.

**Impact.** Slow pages, Safe Browsing quota used up, server exhaustion, legitimate users locked out.

**Mitigations**
- Message length limit (5,000 characters)
- Per-IP rate limiting on submission (M1, Phase 03)
- A cap on URLs processed per submission
- HTTP timeouts and response-size caps
- Safe Browsing results cached with a time limit (`reputation_cache`)

> **(M1 review)** Known limitation: the login lockout is per account, so an attacker could lock a real moderator out for 15 minutes on purpose. Per-IP throttling is the fix.

### Threats to add (M1 review)

Suggested additions for M5 to write up:

| Threat | Why it applies here | Current defence |
|---|---|---|
| Leaked secrets | The Safe Browsing key or DB password committed to git | `.env` is git-ignored; Phase 04 check that the key is absent from the **whole** history |
| Session hijacking | A stolen session cookie gives someone's account | `HttpOnly`, `Secure` on HTTPS, `SameSite=Lax`, strict mode, idle timeout |
| Privacy of submissions | People paste real messages that may contain names and numbers | Store a **hashed** IP, never the raw IP; never log full message text |
| Information leaks in errors | Stack traces can reveal paths or the DB password | Friendly error page with a reference code; details go to the log only |
| Open redirect | `?next=` style parameters sending users to another site | `safe_local_path()` only allows local paths |

## 4. Summary

| Threat | Likelihood | Impact | Risk | Main mitigation |
|---|---|---|---|---|
| SSRF | High | Critical | **Critical** | URL and IP validation on every hop |
| SQL injection | Medium | Critical | High | Prepared statements |
| XSS | Medium | High | High | Output escaping |
| CSRF | Medium | High | High | CSRF tokens |
| Unauthorised access | Medium | High | High | Role-based access control |
| Denial of service | Medium | High | High | Rate limiting and resource limits |

## 5. Conclusion

SSRF is the most significant concern because SafeCedi fetches URLs that users supply. SQL injection, XSS, CSRF and unauthorised access follow. Controls must be enforced on the server. Browser-side checks are only for convenience. Each mitigation above is tested by a scenario in [test-plan.md](test-plan.md).
