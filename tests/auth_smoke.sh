#!/usr/bin/env bash
# Auth & access-control smoke test  (owner: M1)
#
# Drives the real site over HTTP with curl, exactly like a browser would.
# Safe to run against the live URL: it only creates throwaway reporter
# accounts with unique emails.
#
#   bash tests/auth_smoke.sh http://127.0.0.1:8000
#   bash tests/auth_smoke.sh https://your-live-site.example
#
# Optional: test the moderator path with an existing moderator account
#   MOD_EMAIL=mod@example.com MOD_PASSWORD='...' bash tests/auth_smoke.sh URL

set -u
BASE="${1:-http://127.0.0.1:8000}"
BASE="${BASE%/}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
JAR="$TMP/jar"
STAMP="$(date +%s)$RANDOM"
EMAIL="smoke-$STAMP@example.com"
PASS='correct horse battery'
pass=0; fail=0

ok()   { echo "  PASS  $1"; pass=$((pass+1)); }
bad()  { echo "  FAIL  $1"; fail=$((fail+1)); }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected $3, got $2)"; fi; }
has()  { if grep -q -- "$2" "$3"; then ok "$1"; else bad "$1 (missing: $2)"; fi; }
hasnt(){ if grep -q -- "$2" "$3"; then bad "$1 (found: $2)"; else ok "$1"; fi; }

# GET/POST helpers: print the status code, save body to $TMP/body, headers to $TMP/head
get()  { curl -s -o "$TMP/body" -D "$TMP/head" -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/$1"; }
post() { local path="$1"; shift; curl -s -o "$TMP/body" -D "$TMP/head" -w '%{http_code}' -b "$JAR" -c "$JAR" "$@" "$BASE/$path"; }
token(){ sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' "$TMP/body" | head -1; }
sid()  { awk '$6=="safecedi_sid"{print $7}' "$JAR" | tail -1; }
location(){ awk 'tolower($1)=="location:"{print $2}' "$TMP/head" | tr -d '\r'; }

echo "Smoke testing $BASE"

echo "— Anonymous access"
check "home page loads"                     "$(get index.php)" 200
has   "security header: nosniff"            "X-Content-Type-Options: nosniff" "$TMP/head"
has   "security header: frame deny"         "X-Frame-Options: DENY" "$TMP/head"
has   "session cookie is HttpOnly"          "HttpOnly" "$TMP/head"
T="$(token)"
check "anonymous can submit a message"      "$(post check.php --data-urlencode "csrf_token=$T" --data-urlencode 'message=<script>alert(1)</script> Dear customer send your PIN')" 200
hasnt "pasted HTML is escaped, not run"     "<script>alert(1)</script>" "$TMP/body"
has   "pasted HTML shown as text"           "&lt;script&gt;" "$TMP/body"
check "submit without CSRF token refused"   "$(post check.php --data-urlencode 'message=hello')" 403
check "submit via GET refused"              "$(get check.php)" 405
check "account page needs login"            "$(get account.php)" 303
check "moderate page needs login"           "$(get moderate.php)" 303
check "health check hidden without token"   "$(get healthcheck.php)" 404

echo "— Registration"
get register.php >/dev/null; T="$(token)"
post register.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" \
     --data-urlencode "display_name=Smoke Tester" --data-urlencode "password=short" \
     --data-urlencode "password_confirm=short" >/dev/null
has   "short password rejected server-side" "at least 10 characters" "$TMP/body"
T="$(token)"
post register.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" \
     --data-urlencode "display_name=<b>x</b>" --data-urlencode "password=$PASS" \
     --data-urlencode "password_confirm=$PASS" >/dev/null
has   "HTML in display name rejected"       "not allowed" "$TMP/body"
T="$(token)"; BEFORE="$(sid)"
check "valid registration redirects"        "$(post register.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" --data-urlencode 'display_name=Smoke Tester' --data-urlencode "password=$PASS" --data-urlencode "password_confirm=$PASS")" 303
AFTER="$(sid)"
if [ -n "$BEFORE" ] && [ "$BEFORE" != "$AFTER" ]; then ok "session ID regenerated on login"; else bad "session ID regenerated on login"; fi
check "account page now loads"              "$(get account.php)" 200
has   "account shows the reporter role"     "Reporter" "$TMP/body"
check "reporter blocked from moderation"    "$(get moderate.php)" 403

echo "— Logout"
T="$(token)"
check "logout via GET refused"              "$(get logout.php)" 405
get account.php >/dev/null; T="$(token)"
check "logout via POST works"               "$(post logout.php --data-urlencode "csrf_token=$T")" 303
check "account page locked again"           "$(get account.php)" 303

echo "— Login"
get register.php >/dev/null; T="$(token)"
post register.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" \
     --data-urlencode 'display_name=Someone Else' --data-urlencode "password=$PASS" \
     --data-urlencode "password_confirm=$PASS" >/dev/null
has   "duplicate email refused"             "already registered" "$TMP/body"
get login.php >/dev/null; T="$(token)"
post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=nobody-$STAMP@example.com" --data-urlencode 'password=whatever123' >/dev/null
has   "unknown email: generic message"      "Email or password is incorrect" "$TMP/body"
T="$(token)"
post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" --data-urlencode 'password=wrong password' >/dev/null
has   "wrong password: same generic message" "Email or password is incorrect" "$TMP/body"
T="$(token)"
check "correct login (email in caps) works" "$(post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$(echo "$EMAIL" | tr a-z A-Z)" --data-urlencode "password=$PASS")" 303
check "signed in after login"               "$(get account.php)" 200
T="$(token)"; post logout.php --data-urlencode "csrf_token=$T" >/dev/null

echo "— Brute-force lockout (5 misses)"
get login.php >/dev/null
for i in 1 2 3 4 5; do
  T="$(token)"
  post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" --data-urlencode "password=wrong-$i-xxxxx" >/dev/null
done
T="$(token)"
post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" >/dev/null
has   "correct password refused while locked" "Too many failed attempts" "$TMP/body"

if [ -n "${MOD_EMAIL:-}" ] && [ -n "${MOD_PASSWORD:-}" ]; then
  echo "— Moderator"
  rm -f "$JAR"; get login.php >/dev/null; T="$(token)"
  check "moderator login"                   "$(post login.php --data-urlencode "csrf_token=$T" --data-urlencode "email=$MOD_EMAIL" --data-urlencode "password=$MOD_PASSWORD")" 303
  check "moderator can open moderation"     "$(get moderate.php)" 200
  has   "nav shows Moderate link"           'href="moderate.php"' "$TMP/body"
else
  echo "— Moderator: skipped (set MOD_EMAIL and MOD_PASSWORD to include it)"
fi

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
