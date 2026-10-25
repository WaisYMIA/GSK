# Task 0 baseline kit (revision 2)

Adds files to the GSK v2 pack. It changes no existing file.

- `scripts/task0-evidence.sh`: runs composer validate, bootstrap (with a trace of any migrations it ran and the generated database defaults), clean MySQL migration, rollback and re-migration, seeding, route list, Pint and tests. Records each exact command, exit code and log. Writes `task0-evidence/EVIDENCE.md` and `junit.xml`.
- `scripts/wait-for-db.sh`, `scripts/db-empty-check.php`: database readiness and an empty-database check.
- `.github/workflows/task0-baseline.yml`: runs the harness on GitHub Actions with a disposable MySQL 8.4 service and uploads the evidence pack.

## Step states in EVIDENCE.md
PASS (exit 0) | FAIL (ran, nonzero) | BLOCKED (a prerequisite failed, so it was not run) | NOT RUN (never reached) | OBSERVED (informational only).
The gate is MET only if every gate step is PASS, **including `composer_validate` and a verified test summary**. The test summary is verified from `junit.xml` and the `Tests:` line: it must exist, show more than zero tests, and show no failed, errored, incomplete, skipped or risky tests. If either is missing, the gate fails. A killed or aborted run leaves an EVIDENCE.md that says ABORTED.

## Safety
The harness refuses to run unless **all** of these hold:
1. `GSK_ALLOW_DESTRUCTIVE_DB=yes` is set (the workflow sets it for its own MySQL service; set it yourself only for a throwaway database).
2. `DB_DATABASE` is `gsk_test` or `gsk_ci_*`, and `DB_HOST` is local or the CI service.
3. The target database contains no tables. There is **no override**. Bootstrap runs with the database variables unset, so framework scripts cannot touch the test database first.
These checks reduce risk. They cannot prove that a database is disposable. Never point this at production or a shared database.

## Run on GitHub (preferred)
1. Create a **private** repository. Extract this kit merged into the v2 pack, then `git init -b main && git add . && git commit -m "chore: GSK v2 pack plus Task 0 kit"`.
2. `git remote add origin <your repo URL> && git push -u origin main`
3. `git checkout -b task0/baseline && git push -u origin task0/baseline` (the push starts the workflow), or start it from the Actions tab.
4. Download the `task0-evidence` artifact. Send back `EVIDENCE.md`, the logs, the commit SHA and the run URL.
Do not commit secrets or real personal data. Review repository access and Actions permissions.

## Run locally
Needs bash 4+, PHP 8.3 or newer with the usual extensions, Composer 2, python3, and a **fresh, empty, disposable** MySQL database named `gsk_test` (recreate it for every run, for example `docker compose down -v` then `up`):
`GSK_ALLOW_DESTRUCTIVE_DB=yes DB_PORT=3307 scripts/task0-evidence.sh`

## What it does not do
It does not fix anything. A failing step is a finding. If the first run fails at the suspected duplicate users migration, that log is the evidence, and any fix is proposed for review, not applied silently. The harness cannot itself prove that PHPUnit used MySQL; test BASE-05 must assert the driver.
