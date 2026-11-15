#!/usr/bin/env bash
# TerraWatch-1 — FUN-01 / FUN-05 end-to-end play-through over HTTP (like a browser: cookies + CSRF).
# AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
# Usage: bash tests/play_cycle.sh [base_url]   (default http://127.0.0.1:8000)
# Plays D4 (weak, Standard, land cover), then redesigns it into D1 and flies again.
set -u
BASE="${1:-http://127.0.0.1:8000}"
JAR="$(mktemp)"; PAGE="$(mktemp)"
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "PASS $1"; }
bad()  { FAIL=$((FAIL+1)); echo "FAIL $1"; }
get()  { curl -s -L -b "$JAR" -c "$JAR" -o "$PAGE" -w '%{url_effective}' "$BASE$1"; }
token(){ grep -o 'name="csrf" value="[^"]*"' "$PAGE" | head -1 | sed 's/.*value="//;s/"//'; }
post() { local path="$1"; shift; curl -s -L -b "$JAR" -c "$JAR" -o "$PAGE" -w '%{url_effective}' "$BASE$path" --data-urlencode "csrf=$(token)" "$@"; }
has()  { grep -q -- "$1" "$PAGE"; }
check(){ if has "$2"; then ok "$1"; else bad "$1 (missing: $2)"; fi; }

# 1–4: landing, briefing, objective, requirements
get / >/dev/null
url=$(post / --data-urlencode "nickname=Play Test" --data-urlencode "difficulty=DIFF-S")
[[ "$url" == *briefing.php ]] && ok "landing -> briefing" || bad "landing -> briefing ($url)"
check "briefing shows four segments and F01" "Fact card F01"
get /objective.php >/dev/null
url=$(post /objective.php --data-urlencode "objective=OBJ-LAND")
[[ "$url" == *requirements.php ]] && ok "objective -> requirements" || bad "objective -> requirements ($url)"
check "requirements show 4,000 MB per day" "4,000 MB per day"

# 5: designer with D4 (Standard, land): S / SWIR / P1 / C1 / G1 / O1 / L1 / 10
get /designer.php >/dev/null
check "designer shows segment rail" "aria-label=\"Mission segments\""
check "designer loads the live engine script" "/assets/js/engine.js"
# FUN-06: background autosave returns 204 and the draft survives a reload
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/designer.php" --data-urlencode "csrf=$(token)" -d action=autosave -d bus=BUS-L)
[[ "$code" == 204 ]] && ok "FUN-06 autosave returns 204" || bad "FUN-06 autosave returned $code"
get /designer.php >/dev/null
check "FUN-06 autosaved draft restored after reload" 'value="BUS-L" checked'
url=$(post /designer.php -d action=save -d bus=BUS-S -d "instruments[]=INS-SWIR" -d power=PWR-P1 -d comms=COM-C1 \
      -d "stations[]=GS-G1" -d orbit=ORB-O1 -d launch=LV-L1 -d imaging=10)
check "designer dashboard: mass 26.5 / 60 kg" "26.5 / 60.0 kg"
check "designer warns W03" ">W03<"
url=$(post /designer.php -d action=review -d bus=BUS-S -d "instruments[]=INS-SWIR" -d power=PWR-P1 -d comms=COM-C1 \
      -d "stations[]=GS-G1" -d orbit=ORB-O1 -d launch=LV-L1 -d imaging=10)
[[ "$url" == *preflight.php ]] && ok "designer -> preflight" || bad "designer -> preflight ($url)"
check "preflight offers Launch" ">Launch</button>"

# 6–8: launch, simulation, results
url=$(post /preflight.php)
[[ "$url" == *simulation.php?run=* ]] && ok "launch -> simulation" || bad "launch -> simulation ($url)"
RUN1="${url##*run=}"
check "simulation log has EV-STORAGE-FULL message" "storage full"
get "/results.php?run=$RUN1" >/dev/null
check "D4 results: science 21.00" ">21.00<"
check "D4 results: objective not met" "Objective not met"
check "D4 users panel heading" "What the users received"
check "D4 users panel: 30% limitation" "Only 30% of the data"
check "D4 users panel: data lost limitation" "was overwritten before it could be sent"
check "D4 users panel: instrument limitation" "not the best match"
check "D4 users panel disclaimer" "Illustrative. The game does not produce real images or data products."

# 9: debrief, redesign into D1: M / MSI / P2 / C2 / G1 / O1 / L3 / 5
get "/debrief.php?run=$RUN1" >/dev/null
check "debrief first suggestion is about data" "Add contact time, a faster radio or memory"
url=$(post /debrief.php -d action=redesign -d run="$RUN1")
[[ "$url" == *designer.php* ]] && ok "redesign -> designer" || bad "redesign -> designer ($url)"
check "redesign draft keeps old choice (Kestrel)" 'value="BUS-S" checked'
url=$(post /designer.php -d action=review -d bus=BUS-M -d "instruments[]=INS-MSI" -d power=PWR-P2 -d comms=COM-C2 \
      -d "stations[]=GS-G1" -d orbit=ORB-O1 -d launch=LV-L3 -d imaging=5)
url=$(post /preflight.php)
RUN2="${url##*run=}"
get "/results.php?run=$RUN2" >/dev/null
check "D1 results: science 100.00" ">100.00<"
check "D1 results: mission success" "Mission success"
check "D1 results: mission score 98.70" ">98.70<"
check "D1 users panel: only 'received what they asked for'" "the users received what they asked for"
if has "Only " && grep -q "Only [0-9]*% of the data" "$PAGE"; then bad "D1 shows a data limitation"; else ok "D1 has no data limitation"; fi
get "/debrief.php?run=$RUN2" >/dev/null
check "comparison shows +79.00 science delta" "+79.00"

# FUN-05 original run unchanged; SEC checks
get "/results.php?run=$RUN1" >/dev/null
check "FUN-05 original D4 run unchanged (21.00)" ">21.00<"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/objective.php" -X POST -d csrf=wrong -d objective=OBJ-LAND)
[[ "$code" == 403 ]] && ok "SEC-03 POST with a wrong session token is rejected (403)" || bad "SEC-03 got $code"
code=$(curl -s -o /dev/null -w '%{url_effective}' -L "$BASE/results.php?run=$RUN2")
[[ "$code" != *results.php* ]] && ok "SEC-05 another session cannot open this run" || bad "SEC-05 run visible to another session"

rm -f "$JAR" "$PAGE"
echo; echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
