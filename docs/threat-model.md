SAFECEDI – THREAT MODEL

Owner: M5 (Security, QA & Evidence)
Phase 01 Deliverable
Originally written: 19 November 2026

⸻

1. What SafeCedi Is, from an Attacker’s Point of View

SafeCedi helps people in Ghana identify fraudulent mobile-money messages, suspicious links and phishing attempts. Anyone can paste a suspicious SMS or WhatsApp message without creating an account and receive a risk band — Looks Safe, Unclear, Suspicious or Dangerous — together with the evidence behind the assessment.

The application analyses language patterns, sender details, URLs and reputation data.

Two characteristics make SafeCedi a potential target:

* Public input: Anyone, including unauthenticated users, can submit text for analysis. The submission form is a major attack surface.
* External URL fetching: SafeCedi retrieves URLs submitted by users and follows redirect chains. This creates a significant Server-Side Request Forgery (SSRF) risk.

⸻

2. Assets to Protect

Asset	What It Is	Why It Matters
User accounts	Login credentials and account details	Prevent unauthorised access
Submitted messages	Messages pasted for analysis	Protect user privacy
Extracted URLs	Links extracted from submitted messages	Prevent malicious outbound requests
Verdict records	Scores, risk bands and supporting evidence	Maintain accuracy and integrity
Database	Users, submissions, reports, rules and feedback	Prevent unauthorised reading or modification
API credentials	Google Safe Browsing API key	Prevent abuse of API quota
Moderation system	Tools moderators use to manage reports	Prevent unauthorised administrative actions

⸻

3. Threats

Threat 1: Server-Side Request Forgery (SSRF)

What it is

An attacker tricks the server into making requests to internal or restricted addresses.

SafeCedi follows links extracted from submitted messages. Without proper validation, an attacker could submit a URL pointing to 127.0.0.1, a private network address or the cloud metadata address 169.254.169.254, causing the server to fetch it.

Impact

* Access to internal services.
* Exposure of server information or cloud credentials.
* Access to restricted network resources.
* Possible compromise of the hosting environment.

Mitigations

* Resolve the hostname before making any HTTP request.
* Refuse private, loopback and link-local IP addresses.
* Allow only HTTP and HTTPS protocols.
* Limit redirect chains to a maximum of five hops.
* Re-check the destination IP address at every redirect.
* Use connection timeouts and response-size limits.
* Log refused requests using the application’s logging system.

Whenever the URL resolver refuses a URL, it must record a warning using:

app_log('warning', 'ssrf_refused', [...]);

The log should contain relevant security information without unnecessarily recording sensitive user-submitted message content.

Where it Lives in the Code

engine/SsrfGuard.php (M3)

SsrfGuard::check() determines whether a URL may be fetched. The guard uses limits of:

* 5 redirect hops
* 256 KB maximum response size
* 5 seconds timeout

pinnedResolveEntry() makes cURL connect to the address that was checked, preventing DNS changes between validation and connection.

SSRF is the headline risk in the project plan.

⸻

Threat 2: SQL Injection

What it is

An attacker inserts SQL syntax into an input to manipulate the application’s database queries.

Potential entry points include:

* Submitted messages.
* URLs.
* Login forms.
* Report submissions.
* Feedback forms.

Impact

* Records read, changed or deleted.
* User data exposed.
* Database integrity compromised.

Mitigations

* Use PDO prepared statements for every database query.
* Use db_run() and db_one() in includes/db.php, with real prepared statements and emulation disabled.
* Never concatenate user input directly into SQL statements.
* Validate input on the server.
* Restrict the production database user to only the required privileges: SELECT, INSERT, UPDATE and DELETE.

The production database permissions are documented in docs/deploy-guide.md, Step 2.

⸻

Threat 3: Cross-Site Scripting (XSS)

What it is

Malicious scripts injected through user content execute in another user’s browser.

SafeCedi stores and redisplays messages, URLs, reports and feedback. Since scam messages are inherently untrusted content, any of these fields could contain malicious HTML or JavaScript.

Impact

* Session theft.
* Actions performed in another user’s name.
* Manipulated pages.
* Compromised moderator accounts.

Mitigations

* Escape every user-supplied value at the point of output using e().
* Use htmlspecialchars() with ENT_QUOTES | ENT_SUBSTITUTE and UTF-8.
* Never insert untrusted content into HTML, attributes or JavaScript without appropriate escaping.
* Validate input before storing it.
* Use the HttpOnly session cookie attribute to prevent JavaScript from reading the session cookie.
* Implement a Content Security Policy (CSP).

Content Security Policy

The application must send a Content Security Policy through send_security_headers().

The policy must restrict the sources from which scripts, styles, images, fonts and other resources may be loaded.

It must account for the application’s required external resources, including:

* Google Fonts used by M4’s CSS.
* The source from which jQuery is loaded.

External sources should be explicitly allowlisted rather than allowing unrestricted resource loading.

The CSP should be tested to ensure it does not block legitimate application functionality while preventing unauthorised scripts from executing.

⸻

Threat 4: Cross-Site Request Forgery (CSRF)

What it is

Another website tricks a logged-in user’s browser into submitting a form to SafeCedi.

For example, an attacker could attempt to trick a moderator into approving or deleting a report without their knowledge.

Impact

* Reports changed without authorisation.
* Unwanted account changes.
* Unauthorised moderation actions.

Mitigations

* Generate a random token for each session using:

bin2hex(random_bytes(32))

* Include the token in every state-changing form using csrf_field().
* Validate the token on the server before performing database writes using csrf_verify().
* Compare tokens using the constant-time hash_equals() function.
* Reject requests with missing or invalid tokens using HTTP 403.
* Use a POST form with a CSRF token for logout instead of a link.
* Set the session cookie to SameSite=Lax as an additional defence.

⸻

Threat 5: Unauthorised Access

What it is

An attacker accesses features or information that their role does not permit.

For example, an ordinary user could attempt to access /moderate.php by manually entering the URL.

Impact

* Exposed user data.
* Unauthorised modification of reports.
* Abuse of moderator privileges.

Mitigations

* Implement role-based access control.
* Use require_login() and require_role(ROLE_MODERATOR) at the beginning of every protected page.
* Re-read the user’s role from the database on every request so a demoted moderator loses access on their next request.
* Hash passwords using password_hash().
* Verify passwords using password_verify().
* Lock login attempts for 15 minutes after five failed attempts.
* Regenerate the session ID after login to prevent session fixation.
* Apply a 30-minute idle session timeout.

⸻

Threat 6: Denial of Service and Resource Abuse

What it is

An attacker floods the application with large messages or numerous URLs.

Link resolution and reputation lookups are particularly expensive because they require external requests.

Impact

* Slow application response.
* Excessive Safe Browsing API usage.
* Server resource exhaustion.
* Reduced availability for legitimate users.
* Legitimate accounts being locked out.

Mitigations

* Limit message length to 5,000 characters.
* Implement per-IP rate limiting on submissions.
* Limit the number of URLs processed per submission.
* Apply HTTP timeouts.
* Apply response-size limits.
* Cache Safe Browsing results using reputation_cache with an expiry time.
* Monitor repeated requests and suspicious activity.

Login Lockout Limitation

The current login lockout is applied per account. This means an attacker could deliberately submit repeated failed login attempts against a real moderator’s account and cause that account to be locked for 15 minutes.

This creates a potential denial-of-service risk against legitimate users.

Recommended improvement: Implement per-IP throttling in addition to the account-based lockout. This would reduce the risk of an attacker deliberately locking another user’s account.

⸻

Threat 7: Leaked Secrets

What it is

Sensitive credentials may accidentally be exposed through the Git repository, application files or logs.

Examples include:

* Google Safe Browsing API keys.
* Database passwords.
* Other API credentials.
* Secret configuration values.

Impact

* Unauthorised API usage.
* Abuse of external service quotas.
* Unauthorised database access.
* Financial or operational damage.
* Compromise of the hosting environment.

Mitigations

* Store sensitive credentials in environment variables.
* Use .env for local configuration.
* Ensure .env is included in .gitignore.
* Never commit API keys or database passwords to the repository.
* Review the repository before committing changes.
* Check the repository’s entire Git history for accidentally committed secrets before deployment.
* Rotate any credentials that are discovered to have been exposed.

A Phase 04 deployment check must confirm that the Safe Browsing API key and other secrets are absent from the repository history.

⸻

Threat 8: Session Hijacking

What it is

An attacker obtains a user’s session cookie and uses it to impersonate that user.

A stolen moderator session could allow an attacker to access privileged moderation functionality.

Impact

* Unauthorised account access.
* Exposure of account information.
* Unauthorised moderation actions.
* Account impersonation.

Mitigations

* Set the session cookie to HttpOnly.
* Set Secure when the application is running over HTTPS.
* Use SameSite=Lax.
* Enable strict session mode.
* Regenerate the session ID after login.
* Apply a 30-minute idle timeout.
* Use HTTPS in production.

These controls reduce the risk of session theft and misuse.

⸻

Threat 9: Privacy of Submissions

What it is

Users may paste real SMS or WhatsApp messages containing personal information.

Messages may contain:

* Names.
* Phone numbers.
* Transaction references.
* Account information.
* Financial details.
* Other personal information.

If this information is unnecessarily stored or logged, it could expose users to privacy risks.

Impact

* Exposure of personal information.
* Privacy violations.
* Potential financial or social harm.
* Loss of user trust.

Mitigations

* Store hashed IP addresses rather than raw IP addresses.
* Never log full submitted message text.
* Avoid storing unnecessary personal information.
* Limit access to submitted messages.
* Apply appropriate retention rules.
* Ensure logs contain only the information needed for troubleshooting and security monitoring.

The application should collect and retain only the information required to operate SafeCedi.

⸻

Threat 10: Information Leaks in Errors

What it is

Application errors may expose sensitive technical information.

For example, PHP error messages or database exceptions could reveal:

* Server file paths.
* Database details.
* Credentials.
* Internal application structure.
* SQL queries.

Impact

* Information that helps attackers plan further attacks.
* Exposure of sensitive configuration.
* Increased risk of database or server compromise.

Mitigations

* Display a friendly error page to users.
* Assign a reference code to unexpected errors.
* Log technical details internally.
* Never display stack traces or database credentials to users.
* Disable detailed error display in production.

Users should receive a message indicating that an error occurred and, where appropriate, a reference code they can provide when reporting the problem.

⸻

Threat 11: Open Redirect

What it is

An open redirect occurs when an application redirects a user to an attacker-controlled external website using a redirect parameter.

For example, an attacker could create a link that appears to lead to SafeCedi but redirects the victim to a malicious website.

Impact

* Phishing attacks.
* Users misled into visiting malicious websites.
* Abuse of SafeCedi’s reputation.
* Increased risk of credential theft.

Mitigations

* Validate redirect destinations.
* Use safe_local_path() for redirect parameters such as next.
* Allow only local application paths.
* Reject external URLs and unsafe redirect destinations.

⸻

4. Threat Summary

Threat	Likelihood	Impact	Risk	Main Mitigation
SSRF	High	Critical	Critical	URL/IP validation on every hop and pinned DNS resolution
SQL Injection	Medium	Critical	High	PDO prepared statements
XSS	Medium	High	High	Output escaping and CSP
CSRF	Medium	High	High	CSRF tokens
Unauthorised Access	Medium	High	High	Role-based access control
Denial of Service	Medium	High	High	Rate limiting and resource limits
Leaked Secrets	Medium	Critical	High	Environment variables and Git-history checks
Session Hijacking	Medium	High	High	Secure session configuration
Privacy of Submissions	Medium	High	High	Data minimisation and hashed IPs
Information Leaks in Errors	Medium	Medium	Medium	Friendly errors and internal logging
Open Redirect	Medium	Medium	Medium	Local-path validation

⸻

5. Conclusion

SSRF is SafeCedi’s most significant security concern because the application fetches URLs supplied by users. SQL injection, XSS, CSRF and unauthorised access are also important threats.

Additional risks include leaked secrets, session hijacking, privacy violations, information leaks through errors, open redirects and denial-of-service attacks.

Security controls must be enforced on the server. Browser-side validation is only for convenience and cannot replace server-side security.

Each mitigation must be verified through an appropriate test scenario in test-plan.md. Testing must distinguish between controls that have already been implemented and those that remain planned or require integration.

SafeCedi’s security approach should follow the principle of minimising trust: all user input, submitted messages, URLs, external responses and redirect destinations must be treated as potentially malicious until validated.
