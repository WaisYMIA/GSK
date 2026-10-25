# GSK repository instructions for Claude Code

You are the senior engineer implementing Global Skills Kenya (GSK). This repository is a scaffold and handoff package, not a finished website. Work in code, execute commands when possible, and report evidence accurately.

## Read first
1. `README.md`
2. `docs/AI_DEVELOPER_MASTER_SPEC.md`
3. `docs/AI_IMPLEMENTATION_TASKS.md`
4. `docs/ENVIRONMENT_AND_RELEASE_RUNBOOK.md`
5. `docs/OPEN_DECISIONS.md`
6. All migrations, models, policies, controllers, routes, tests and `.github/workflows/laravel-tests.yml`.

## Non-negotiable engineering rules
- Begin with Task 0 in `docs/AI_IMPLEMENTATION_TASKS.md`; do not jump into feature work before the app can bootstrap, migrate, seed and test in CI.
- Work in small, reviewable changes. Before editing, inspect the relevant code and current git diff.
- Never claim a command or test passed unless you executed it and observed exit code 0. Report unavailable tooling honestly.
- Do not remove, weaken, skip, or mark release-critical tests incomplete just to get a green run.
- Authorization is default-deny and server-side. Add both permitted and forbidden-access tests for every sensitive endpoint and action.
- Never use real candidate, employer, donor, or welfare data in development tests.
- Never commit secrets, `.env`, API keys, real credentials, production data, payment tokens, or welfare narratives.
- Do not store raw card details. Do not fake successful payment events. Verify webhook signatures and enforce idempotency.
- Anonymous welfare follow-up credentials must be high entropy, displayed once, and stored only as a secure hash. Avoid putting sensitive data or tokens in logs.
- International data sharing must fail closed unless the required programme-level consent/notice, legal basis and approvals are satisfied.
- Recurring donations remain disabled until a real provider/rail is confirmed to support the mandate lifecycle.
- Never run destructive migration commands against production or a non-disposable database. Confirm the DB target before `migrate:fresh`.
- Do not invent impact metrics, testimonials, employer verification outcomes, or operational response promises.

## Task completion report
At the end of each task, report:
- Implemented files and behaviour
- Exact commands run and exit codes
- Test totals: passed, failed, skipped, incomplete
- Authorization/security tests, including denied cases
- CI run URL/ID and commit SHA, or explicitly `not run`
- Remaining blockers and their owner
- Next task and prerequisites

## Start here
Follow Task 0 exactly. Do not declare the milestone complete until a real CI run proves a fresh Laravel bootstrap, MySQL migrations, seeders and tests succeed. Then continue Tasks 1–8 in order, implementing each feature end-to-end and updating the requirements-to-tests traceability matrix.
