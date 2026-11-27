# Test plan

**Owner: M5 (Security, QA & Evidence).** Phase 01 deliverable, first written 19 Nov 2026. 20 scenarios, including the five deliberate false-positive candidates the build plan asks for. Re-run in full during the Phase 04 regression (Wed 2 Dec).

## How tests are run and recorded

Each scenario is run against the **live HTTPS site**, not localhost, unless it says otherwise. Results are recorded as:

- **PASS:** behaves as expected
- **FAIL:** doesn't. Log a bug (below)
- **BLOCKED:** the feature it needs doesn't exist yet

Some scenarios are already automated:

| Tool | Command | Covers |
|---|---|---|
| Auth smoke test | `bash tests/auth_smoke.sh https://YOUR-SITE` | TC10, TC12, TC14, plus login, logout, lockout and registration (31 checks) |
| M3's engine tests | `php tests/engine_tests.php` | TC08, TC09, TC15 at the guard level (about 30 SSRF cases, offline) |
| Corpus harness | `php tests/corpus_harness.php` | Precision and recall over the 60-message corpus |

> **(M1 review)** The expected results for TC01–TC05 and FP01–FP05 need to be exact enough to pass or fail, naming the band and the signal. For example, TC03: "band is Suspicious or Dangerous, and `LANG_PIN_REQUEST` is listed with the matched words." M5 should fill these in once M3's PR is merged and the rule codes are final. Put the actual test message in the table too, so anyone can repeat it.

## Functional and security scenarios

| ID | Scenario | Expected result | How to check | Status |
|---|---|---|---|---|
| TC01 | Submit a real scam message with urgent language | The urgency signal fires and the band reflects the risk | Paste on `index.php` | |
| TC02 | Submit a legitimate bank transaction notification | Not classified as Dangerous | Paste on `index.php` | |
| TC03 | Submit a message asking for a mobile-money PIN | PIN request listed as a signal, with the matched words | Paste on `index.php` | |
| TC04 | Submit a message saying a SIM will be blocked unless the user acts now | Urgency and SIM-block / authority signals fire | Paste on `index.php` | |
| TC05 | Submit a message with a suspicious shortened URL | The shortener is flagged as a link signal | Paste on `index.php` | BLOCKED until link rules (M3) |
| TC06 | Submit an empty message | Rejected with "Paste a message to check." | Remove `required` in dev tools, or use curl | |
| TC07 | Submit a message over 5,000 characters | Rejected as too long | Remove `maxlength` in dev tools, or use curl | |
| TC08 | A URL with an unsupported scheme, such as `file://` | Refused, not fetched | Engine tests; live once the resolver exists | |
| TC09 | A malformed URL | Treated as invalid, not fetched | Engine tests | |
| TC10 | A message containing HTML or JavaScript | Shown as text; the script never runs | Paste `<script>alert(1)</script>` | Automated (smoke test) |
| TC11 | SQL syntax in a form, e.g. `' OR 1=1 --` as the login email | Treated as data; normal error message; no SQL error | Login form | |
| TC12 | A form posted without a CSRF token | 403, nothing written | curl | Automated (smoke test) |
| TC13 | A form posted with a wrong CSRF token | 403, nothing written | curl with `csrf_token=wrong` | |
| TC14 | A reporter account opens a moderator page | 403, and the attempt is logged | Sign in as a reporter, go to `/moderate.php` | Automated (smoke test) |
| TC15 | A message with a link to an internal address (`http://127.0.0.1/`, `http://169.254.169.254/`) | The SSRF guard refuses it and the refusal is logged | Live, once the resolver exists | BLOCKED until resolver (Phase 03) |

## False-positive candidates

Legitimate messages that look a bit like scams. These test whether SafeCedi can tell normal messages from fraud, which the plan calls the project's main weakness.

| ID | Legitimate message | Expected result | Status |
|---|---|---|---|
| FP01 | A real bank message containing "Your account requires verification." | Not Dangerous | |
| FP02 | A real telco message saying SIM registration needs updating | Not Dangerous; the evidence shows why it was scored | |
| FP03 | A real delivery notification with a shortened tracking link | The link is checked, not automatically treated as malicious | BLOCKED until link rules |
| FP04 | A real MoMo notification with an amount and reference number | Not flagged as fraud | |
| FP05 | A real promotional message announcing a reward or offer | Not Dangerous without other evidence | |

## Bug reports

For every FAIL, record:

- Test ID
- What went wrong
- Steps to reproduce
- Expected result
- Actual result
- Severity (Critical / High / Medium / Low)
- Suggested fix
- Result when retested after the fix

Log them as GitHub issues labelled `bug`, so the team works from one list and the Phase 04 regression can re-check every one.

## Conclusion

The plan focuses on SSRF protection, input validation, CSRF protection and false positives. Testing runs throughout the build so problems are fixed before submission, not found during it.
