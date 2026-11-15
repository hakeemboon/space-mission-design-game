#!/usr/bin/env bash
# TerraWatch-1 — security tests SEC-01 to SEC-09 (Document 09 section 8), over HTTP.
# AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
# Usage: bash tests/security.sh [base_url] [prod_base_url]
#   base_url       the normal (development) server, default http://127.0.0.1:8000
#   prod_base_url  optional server started with a production config whose DB password is
#                  wrong, used for SEC-07 (generic error page) and the dev-page 404 check.
set -u
BASE="${1:-http://127.0.0.1:8000}"
PROD="${2:-}"
JAR="$(mktemp)"; JAR2="$(mktemp)"; PAGE="$(mktemp)"
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "PASS $1"; }
bad()  { FAIL=$((FAIL+1)); echo "FAIL $1"; }
get()  { curl -s -L -b "$JAR" -c "$JAR" -o "$PAGE" -w '%{url_effective}' "$BASE$1"; }
token(){ grep -o 'name="csrf" value="[^"]*"' "$PAGE" | head -1 | sed 's/.*value="//;s/"//'; }
post() { local path="$1"; shift; curl -s -L -b "$JAR" -c "$JAR" -o "$PAGE" -w '%{url_effective}' "$BASE$path" --data-urlencode "csrf=$(token)" "$@"; }
start_mission() { # $1 nickname
  get / >/dev/null; post / --data-urlencode "nickname=$1" -d difficulty=DIFF-S >/dev/null
  get /objective.php >/dev/null; post /objective.php -d objective=OBJ-LAND >/dev/null
  get /designer.php >/dev/null
}

# SEC-01 SQL injection in the nickname is stored and shown as plain text
start_mission "' OR '1'='1"
if grep -q "&#039; OR &#039;1&#039;=&#039;1" "$PAGE"; then ok "SEC-01 SQL-injection nickname stored and shown as text"; else bad "SEC-01 nickname not shown literally"; fi

# SEC-02 XSS: the Document 09 payload is 25 characters, over the 24-character limit, so it is refused;
# a 21-character payload is accepted and must be escaped on every page
: > "$JAR"; get / >/dev/null
post / --data-urlencode "nickname=<script>alert(1)</script>" -d difficulty=DIFF-S >/dev/null
if grep -q "Enter a nickname of 1 to 24 characters" "$PAGE"; then ok "SEC-02a 25-char <script> nickname refused by length rule"; else bad "SEC-02a long payload not refused"; fi
: > "$JAR"; start_mission "<img src=x onerror=1>"
if grep -q "<img src=x onerror=1>" "$PAGE"; then bad "SEC-02b raw HTML from nickname rendered"; elif grep -q "&lt;img src=x onerror=1&gt;" "$PAGE"; then ok "SEC-02b HTML nickname escaped on designer"; else bad "SEC-02b nickname not found"; fi
get /briefing.php >/dev/null
if grep -q "<img src=x onerror=1>" "$PAGE"; then bad "SEC-02c raw HTML on briefing"; else ok "SEC-02c HTML nickname escaped on briefing"; fi

# SEC-03 a state-changing POST without the session token is rejected
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/designer.php" -d action=save -d bus=BUS-L)
[[ "$code" == 403 ]] && ok "SEC-03 POST without session token rejected (403)" || bad "SEC-03 got $code"

# SEC-04 a browser-sent score is ignored; the server computes its own (D4 scores 21.00)
get /designer.php >/dev/null
post /designer.php -d action=review -d bus=BUS-S -d "instruments[]=INS-SWIR" -d power=PWR-P1 -d comms=COM-C1 \
     -d "stations[]=GS-G1" -d orbit=ORB-O1 -d launch=LV-L1 -d imaging=10 >/dev/null
url=$(post /preflight.php -d science_score=100 -d mission_score=100 -d outcome=SUCCESS -d operational_months=99)
RUN="${url##*run=}"
get "/results.php?run=$RUN" >/dev/null
if grep -q ">21.00<" "$PAGE" && grep -q "Objective not met" "$PAGE"; then ok "SEC-04 tampered score ignored (stored science 21.00)"; else bad "SEC-04 stored score affected"; fi

# SEC-05 another session cannot open or change this session's run or mission
eff=$(curl -s -L -b "$JAR2" -c "$JAR2" -o "$PAGE" -w '%{url_effective}' "$BASE/results.php?run=$RUN")
[[ "$eff" != *results.php* ]] && ok "SEC-05a other session redirected away from the run" || bad "SEC-05a run visible to another session"
eff=$(curl -s -L -b "$JAR2" -c "$JAR2" -o "$PAGE" -w '%{url_effective}' "$BASE/debrief.php?run=$RUN")
[[ "$eff" != *debrief.php* ]] && ok "SEC-05b other session cannot open the debrief" || bad "SEC-05b debrief visible to another session"

# SEC-06 unknown component codes are rejected cleanly (dropped; H01 explains)
: > "$JAR"; start_mission "Unknown IDs"
post /designer.php -d action=save -d bus=BUS-ZZZ -d "instruments[]=INS-NOPE" -d power=999 -d comms="1 OR 1=1" \
     -d "stations[]=GS-G1" -d orbit=ORB-O1 -d launch=LV-L1 -d imaging=10 >/dev/null
if grep -q ">H01<" "$PAGE" && grep -q "no bus selected" "$PAGE" && ! grep -qi "exception\|stack\|PDO" "$PAGE"; then
  ok "SEC-06 unknown IDs rejected with a clean H01 message"; else bad "SEC-06 unexpected response"; fi

# SEC-07 database error shows a generic message; details only in the log (production server)
if [[ -n "$PROD" ]]; then
  body=$(curl -s -w '\n%{http_code}' "$PROD/")
  code="${body##*$'\n'}"
  if [[ "$code" == 500 ]] && echo "$body" | grep -q "Something went wrong" && ! echo "$body" | grep -qi "PDO\|SQLSTATE\|\.php\|C:\\\\\|terrawatch_app"; then
    ok "SEC-07 database error: generic message, HTTP 500, no paths or SQL"; else bad "SEC-07 error page leaks details or wrong status ($code)"; fi
  code=$(curl -s -o /dev/null -w '%{http_code}' "$PROD/dev/sim03.php")
  [[ "$code" == 404 || "$code" == 500 ]] && ok "SEC-07b dev check page not served in production ($code)" || bad "SEC-07b dev page served ($code)"
else
  echo "SKIP SEC-07 (no production server given)"
fi

# SEC-08 config, source and include files are not reachable by URL
allok=1
for p in /config/config.local.php /../config/config.local.php /src/bootstrap.php /src/db.php /database/seed.sql /tests/engine_tests.php /.git/config /storage/logs/php-error.log; do
  c=$(curl -s -o "$PAGE" -w '%{http_code}' --path-as-is "$BASE$p")
  if grep -q "db_pass\|INSERT INTO\|declare(strict_types" "$PAGE"; then allok=0; echo "      $p exposed ($c)"; fi
done
[[ $allok == 1 ]] && ok "SEC-08 config/source/seed/log files not served" || bad "SEC-08 a private file was served"

# SEC-09 session cookie flags and security headers
hdr=$(curl -s -D - -o /dev/null "$BASE/")
echo "$hdr" | grep -qi "set-cookie:.*HttpOnly.*SameSite=Lax" && ok "SEC-09 session cookie HttpOnly + SameSite" || bad "SEC-09 cookie flags missing"
echo "$hdr" | grep -qi "content-security-policy:.*frame-ancestors 'none'" && echo "$hdr" | grep -qi "x-content-type-options: nosniff" \
  && ok "SEC-09b CSP, nosniff and anti-framing headers present" || bad "SEC-09b security headers missing"

rm -f "$JAR" "$JAR2" "$PAGE"
echo; echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
