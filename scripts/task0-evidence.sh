#!/usr/bin/env bash
# GSK Task 0 evidence harness (revision 2).
# Runs each baseline step, records the exact command, exit code and log, and writes
# task0-evidence/EVIDENCE.md. It reports only what it observed.
#
# Step states:  PASS | FAIL (exit N) | BLOCKED (a prerequisite failed) | NOT RUN | OBSERVED (informational)
# The gate is MET only if every gate step is PASS, including the verified test summary.
# It refuses to run unless GSK_ALLOW_DESTRUCTIVE_DB=yes and the target is a local or CI test database.
set -u -o pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${APP_DIR:-${RUNNER_TEMP:-/tmp}/gsk-app}"
EVID="${EVID_DIR:-$ROOT/task0-evidence}"

if [ "${BASH_VERSINFO[0]:-0}" -lt 4 ]; then echo "REFUSING: bash 4 or newer is required (found ${BASH_VERSION:-unknown})." >&2; exit 64; fi

declare -a ORDER=()
declare -A STATUS RC CMDS MODE
BLOCK_APP=0; BLOCK_DB=0; BLOCK_MIG=0

plan() { ORDER+=("$1"); MODE["$1"]="$2"; STATUS["$1"]="NOT RUN"; RC["$1"]="-"; CMDS["$1"]="(not run)"; }

# run NAME NEEDS DIR COMMAND   (NEEDS: none | app | db | mig)
run() {
  local name="$1" needs="$2" dir="$3" cmd="$4" log rc
  log="$EVID/logs/$name.log"
  CMDS["$name"]="$cmd"
  if [ "$needs" != none ] && [ "$BLOCK_APP" = 1 ]; then STATUS["$name"]="BLOCKED (bootstrap failed)"; return 1; fi
  if { [ "$needs" = db ] || [ "$needs" = mig ]; } && [ "$BLOCK_DB" = 1 ]; then STATUS["$name"]="BLOCKED (database not ready or guard failed)"; return 1; fi
  if [ "$needs" = mig ] && [ "$BLOCK_MIG" = 1 ]; then STATUS["$name"]="BLOCKED (migrate:fresh failed)"; return 1; fi
  { echo "\$ (cd $dir) $cmd"; echo "--- started $(date -u +%FT%TZ)"; } > "$log"
  ( cd "$dir" && bash -o pipefail -c "$cmd" ) >> "$log" 2>&1
  rc=$?
  echo "--- exit $rc" >> "$log"
  RC["$name"]="$rc"
  if [ "${MODE[$name]}" = info ]; then STATUS["$name"]="OBSERVED (exit $rc)"
  elif [ "$rc" -eq 0 ]; then STATUS["$name"]="PASS"
  else STATUS["$name"]="FAIL (exit $rc)"; fi
  return "$rc"
}

# Fails closed unless a recognisable, non-zero, clean test result exists.
verify_tests() {
  local name=test_summary_verified log="$EVID/logs/test_summary_verified.log" ok=1 line clean total p f i s r
  CMDS["$name"]="parse junit.xml and the 'Tests:' summary line"
  case "${STATUS[tests]}" in
    PASS*|FAIL*) ;;
    *) STATUS["$name"]="BLOCKED (tests did not run)"; return 1 ;;
  esac
  : > "$log"
  if [ ! -s "$EVID/junit.xml" ]; then
    echo "junit.xml missing or empty" >> "$log"; ok=0
  elif ! command -v python3 >/dev/null 2>&1; then
    echo "python3 not available to read junit.xml" >> "$log"; ok=0
  else
    python3 - "$EVID/junit.xml" >> "$log" 2>&1 <<'PY' || ok=0
import sys, xml.etree.ElementTree as ET
root = ET.parse(sys.argv[1]).getroot()
s = root if root.tag == 'testsuite' else root.find('testsuite')
if s is None:
    print('junit: no testsuite element'); sys.exit(1)
g = lambda k: int(float(s.get(k, '0')))
t, f, e, k = g('tests'), g('failures'), g('errors'), g('skipped')
print('junit: tests=%d failures=%d errors=%d skipped_or_incomplete=%d' % (t, f, e, k))
sys.exit(0 if t > 0 and f == 0 and e == 0 and k == 0 else 1)
PY
  fi
  line="$(sed 's/\x1b\[[0-9;]*m//g' "$EVID/logs/tests.log" 2>/dev/null | grep -E 'Tests:' | tail -1)"
  if [ -z "$line" ]; then
    echo "no 'Tests:' summary line found in tests.log" >> "$log"; ok=0
  else
    echo "summary line: $line" >> "$log"
    clean="$line"
    p=$(echo "$clean" | grep -oE '[0-9]+ passed' | grep -oE '[0-9]+' || echo 0)
    f=$(echo "$clean" | grep -oE '[0-9]+ failed' | grep -oE '[0-9]+' || echo 0)
    i=$(echo "$clean" | grep -oE '[0-9]+ incomplete' | grep -oE '[0-9]+' || echo 0)
    s=$(echo "$clean" | grep -oE '[0-9]+ skipped' | grep -oE '[0-9]+' || echo 0)
    r=$(echo "$clean" | grep -oE '[0-9]+ risky' | grep -oE '[0-9]+' || echo 0)
    : "${p:=0}" "${f:=0}" "${i:=0}" "${s:=0}" "${r:=0}"
    total=$((p+f+i+s+r))
    echo "counts: passed=$p failed=$f incomplete=$i skipped=$s risky=$r total=$total" >> "$log"
    TEST_COUNTS="passed $p, failed $f, incomplete $i, skipped $s, risky $r (total $total)"
    if [ "$total" -le 0 ] || [ "$f" -ne 0 ] || [ "$i" -ne 0 ] || [ "$s" -ne 0 ] || [ "$r" -ne 0 ]; then ok=0; fi
  fi
  if [ "$ok" -eq 1 ] && [ "${STATUS[tests]}" = PASS ]; then
    STATUS["$name"]="PASS"; echo "verdict: PASS" >> "$log"; return 0
  fi
  STATUS["$name"]="FAIL (test result not clean or not verifiable)"; echo "verdict: FAIL" >> "$log"; return 1
}

TEST_COUNTS="not available (tests did not produce a verifiable summary)"

write_evidence() { # $1 = aborted|final
  local mode="${1:-final}" sha runurl n gatefail=0 failn=0 blockn=0 notrun=0 result first="" name st phpv
  sha="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo 'not a git repository')"
  runurl="not run in GitHub Actions"
  if [ -n "${GITHUB_RUN_ID:-}" ]; then
    runurl="${GITHUB_SERVER_URL:-https://github.com}/${GITHUB_REPOSITORY:-unknown}/actions/runs/${GITHUB_RUN_ID} (run ID ${GITHUB_RUN_ID}, attempt ${GITHUB_RUN_ATTEMPT:-1})"
  fi
  phpv="$(grep -m1 -E '^PHP [0-9]' "$EVID/logs/php_version.log" 2>/dev/null)"; [ -z "$phpv" ] && phpv="not captured (php not available or step not run)"
  for name in "${ORDER[@]}"; do
    st="${STATUS[$name]}"
    case "$st" in FAIL*) failn=$((failn+1));; BLOCKED*) blockn=$((blockn+1));; "NOT RUN") [ "${MODE[$name]}" = gate ] && notrun=$((notrun+1));; esac
    if [ "${MODE[$name]}" = gate ] && [ "$st" != PASS ]; then gatefail=$((gatefail+1)); [ -z "$first" ] && first="$name ($st)"; fi
  done
  if [ "$mode" = aborted ]; then result="ABORTED: the harness did not finish. Nothing below proves success."
  elif [ "$gatefail" -eq 0 ]; then result="GATE MET: every gate step passed and the test result was verified."
  elif [ "$failn" -gt 0 ]; then result="GATE NOT MET: at least one step FAILED. First non-passing gate step: $first."
  else result="GATE NOT MET: baseline INCOMPLETE. First non-passing gate step: $first."; fi
  {
    echo "# GSK Task 0 evidence"
    echo
    echo "**Result: $result**"
    echo
    echo "- Generated (UTC): $(date -u +%FT%TZ)"
    echo "- Commit SHA: $sha"
    echo "- CI run: $runurl"
    echo "- PHP: $phpv"
    echo "- App directory: $APP_DIR"
    echo "- Database target: ${DB_HOST:-?}:${DB_PORT:-?}/${DB_DATABASE:-?} (guards: opt-in flag, name, host, must be empty, no override)"
    echo "- Gate steps not passing: $gatefail | FAIL: $failn | BLOCKED: $blockn | NOT RUN: $notrun"
    echo "- Test counts: $TEST_COUNTS"
    echo
    echo "| step | type | state | exit code | command | log |"
    echo "|---|---|---|---|---|---|"
    for name in "${ORDER[@]}"; do
      local cmd="${CMDS[$name]//|/\\|}" lg="-"
      [ -f "$EVID/logs/$name.log" ] && lg="logs/$name.log"
      echo "| $name | ${MODE[$name]} | ${STATUS[$name]} | ${RC[$name]} | \`$cmd\` | $lg |"
    done
    echo
    echo "States: PASS = exit 0. FAIL = ran and exited nonzero. BLOCKED = not run because a prerequisite failed. NOT RUN = never reached. OBSERVED = informational, never counts toward the gate."
    echo
    echo "Known limits: the harness sets MySQL through environment variables, which should override phpunit.xml defaults, but it cannot itself prove PHPUnit connected to MySQL. Test BASE-05 must assert the driver. 'static_admin_branches' lists code lines only; it is not a runtime test."
  } > "$EVID/EVIDENCE.md"
  echo "Evidence written to $EVID/EVIDENCE.md"
  echo "Result: $result"
  [ "$mode" = final ] && [ "$gatefail" -eq 0 ]
}

main() {
  export APP_ENV=testing DB_CONNECTION=mysql
  export DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3306}" DB_DATABASE="${DB_DATABASE:-gsk_test}"
  export DB_USERNAME="${DB_USERNAME:-gsk_app}" DB_PASSWORD="${DB_PASSWORD:-gsk_test_password}"

  if [ "${GSK_ALLOW_DESTRUCTIVE_DB:-}" != "yes" ]; then
    echo "REFUSING: this harness runs migrate:fresh. Set GSK_ALLOW_DESTRUCTIVE_DB=yes only for a throwaway database or the CI service." >&2; exit 64
  fi
  case "$DB_DATABASE" in gsk_test|gsk_ci_*) ;; *) echo "REFUSING: DB_DATABASE must be gsk_test (or gsk_ci_*). Got: $DB_DATABASE" >&2; exit 64 ;; esac
  case "$DB_HOST" in 127.0.0.1|localhost|mysql) ;; *) echo "REFUSING: DB_HOST must be local or the CI service. Got: $DB_HOST" >&2; exit 64 ;; esac

  mkdir -p "$EVID/logs"; rm -f "$EVID"/logs/*.log "$EVID/junit.xml" "$EVID/EVIDENCE.md"
  trap 'write_evidence aborted; exit 130' INT TERM

  plan php_version info; plan composer_version info; plan node_version info; plan git_version info
  plan composer_validate gate; plan bootstrap gate; plan bootstrap_migration_trace info; plan composer_validate_app gate
  plan migration_inventory info; plan phpunit_db_defaults info
  plan db_ready gate; plan db_empty_check gate; plan db_info info
  plan migrate_fresh_seed gate; plan migrate_status gate; plan migrate_rollback gate; plan migrate_again gate; plan seed_again gate
  plan route_list gate; plan pint_check gate; plan tests gate; plan test_summary_verified gate
  plan static_admin_branches info
  write_evidence aborted >/dev/null   # placeholder so a killed run never looks like a pass

  run php_version none "$ROOT" "php -v"
  run composer_version none "$ROOT" "composer --version"
  run node_version none "$ROOT" "node --version"
  run git_version none "$ROOT" "git --version"
  run composer_validate none "$ROOT" "composer validate --no-check-lock"
  # Database variables are unset for bootstrap so framework scripts (create-project, install:api)
# cannot run migrations against the MySQL test database before the evidence steps.
run bootstrap none "$ROOT" "env -u DB_CONNECTION -u DB_HOST -u DB_PORT -u DB_DATABASE -u DB_USERNAME -u DB_PASSWORD ./scripts/bootstrap-laravel.sh '$APP_DIR'" || BLOCK_APP=1
  run bootstrap_migration_trace app "$APP_DIR" "grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE)=' .env; echo '--- migration lines in bootstrap log:'; grep -n -i -E 'migrat' '$EVID/logs/bootstrap.log' || echo 'none found'; echo '--- sqlite files:'; ls -la database/*.sqlite 2>&1"
  run composer_validate_app app "$APP_DIR" "composer validate"
  run migration_inventory app "$APP_DIR" "ls -1 database/migrations; echo ---; grep -l \"Schema::create('users'\" database/migrations/*.php"
  run phpunit_db_defaults app "$APP_DIR" "grep -n 'DB_' phpunit.xml"
  run db_ready app "$APP_DIR" "'$ROOT/scripts/wait-for-db.sh' 40" || BLOCK_DB=1
  run db_empty_check db "$APP_DIR" "php '$ROOT/scripts/db-empty-check.php'" || BLOCK_DB=1
  run db_info db "$APP_DIR" "php artisan db:show"
  run migrate_fresh_seed db "$APP_DIR" "php artisan migrate:fresh --seed --force" || BLOCK_MIG=1
  run migrate_status mig "$APP_DIR" "php artisan migrate:status"
  run migrate_rollback mig "$APP_DIR" "php artisan migrate:rollback --force"
  run migrate_again mig "$APP_DIR" "php artisan migrate --force"
  run seed_again mig "$APP_DIR" "php artisan db:seed --force"
  run route_list app "$APP_DIR" "php artisan route:list"
  run pint_check app "$APP_DIR" "vendor/bin/pint --test"
  run tests db "$APP_DIR" "php artisan test --log-junit '$EVID/junit.xml'"
  verify_tests
  run static_admin_branches none "$ROOT" "grep -n \"'admin'\" app/Policies/*.php"
  trap - INT TERM
  write_evidence final
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then main "$@"; fi
