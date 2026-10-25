# Task 1, Stage 1A report: identity and access core

> Superseded for totals by `docs/TASK1_STAGE_1B_REPORT.md`. Stage 1A content below is unchanged.

Branch: `task1/identity-access-1a` (not merged). Written 14 October 2026. Authorised by the GSK owner to be written now and merged **only after** Task 0 evidence is reviewed.

## Evidence status (read this first)
**Nothing in this branch has been executed.** No Composer, no PHP, no MySQL, no migration, no test run, no CI run existed in the environment where it was written. All 73 test methods are unrun. There is no commit SHA from CI and no run URL.
What was done instead: a static check of all PHP files for balanced brackets and missing imports (`scripts/php-static-check.py`, not a linter). Result: no problems in the new files beyond classes the framework skeleton provides (`Tests\TestCase`). This is **not** a PHP syntax check. Expect first-run failures and fix them from the CI log.

## What Stage 1A delivers
- **Schema (5 migrations):** `prepare_users_for_gsk` (alter-or-create, robust to the suspected duplicate-users problem), staff identity, access control, membership/scope/approval/break-glass, audit extension. The `users` and `staff_*` tables were removed from the original core migration.
- **Services (`app/Support/Access`):** `AccessService` (default deny, scoped, no caching), `ApprovalService` (independence by staff profile, fail closed), `RoleAssignmentService`, `AccountLifecycleService` (invitation-only staff, identity check, suspension), `BreakGlassService` (allow-listed keys, time-limited, independent review), `AuditLogger`, `SensitiveAccess`, `SessionRevoker`, `Scope`, `AccessDenied`.
- **Policies:** the three admin/staff bypasses are removed. Welfare access needs a welfare permission through a role assignment (oversight, or case-scoped). Employer vacancy edits need an active owner or admin membership.
- **Config:** `config/gsk.php`. Approvals are **off by default**. Public route allow-list is explicit.
- **Seeders:** permission catalogue (21 keys) is reference data. No roles, no mappings, no demo accounts outside local and testing, no admin account, no fixed password.
- **Docs:** `ERD_identity.md` (generated from the migrations), `PERMISSIONS.md`, `STAFF_ONBOARDING_CONTROLS.md`, `AUDIT_PROTECTION.md`, `CHANGE_REQUESTS.md`, `THIRD_PARTY_SERVICES.md`, `HOSTING_AND_LARAVEL_VERSION_CHECK.md`, `DECISION_REGISTER.md`.

## Not delivered (Stage 1B and later)
Login, logout, password reset and email-verification routes and views; Laravel Fortify MFA (setup, challenge, recovery, rate limits); `EnsureStaffMfa` and `EnsureAccountActive` middleware; login throttling; moving `routes/api.php` from Sanctum to sessions and CSRF; recovery-code audit; invitation emails. Tests GSK-004, 006, 007, 008, 061, 062, 085, 086, 087, 088 and SEC-03, 04, 05 depend on these and are not written.

## Tests written
73 test methods in 10 files (7 pre-existing plus 66 new). 3 pre-existing placeholders are still marked incomplete (employer gate, consent withdrawal, recurring donation) because they belong to Tasks 3, 6 and 7. The `staff_can_only_access_explicitly_granted_permissions` placeholder is now a real test.
Register coverage: 23 of the 50 Task 0 and Task 1 rows in `gsk_test_register_v2_2.csv` have a test method that names the ID: GSK-010, 029, 033, 068, 079, 080, 081, 082, 083, 084, 089, 090, 091, 092, 093, 094, 095, 096, 097, SEC-07, SEC-12, BASE-04, BASE-05.
Not yet covered (27): GSK-004, 006, 007, 008, 017, 034, 060, 061, 062, 063, 085, 086, 087, 088, 131, SEC-03, 04, 05, 09, 10, BASE-01, 02, 03, 06, 07, 08, 09. BASE-01 to 03, 06, 07 are covered by the Task 0 harness, not by PHPUnit.

## Known issues and risks
1. **The Task 0 gate cannot be met while placeholder tests exist.** The harness fails the gate on any incomplete test. The scaffold has 4 placeholders (3 remain on this branch). They test features that do not exist yet. Deleting them or marking them skipped is forbidden. **Decision needed:** allow a documented allow-list of future-task placeholders in the Task 0 gate, or move them out of PHPUnit into the register as Not run.
2. `RouteAuditTest` assumes the framework routes `/`, `up`, `storage/{path}` and `sanctum/csrf-cookie`. If the generated app differs the test will list the offenders.
3. `BASE-05` fails if tests run on SQLite. That is intended.
4. The audit trail is append-only in the application only. See `docs/AUDIT_PROTECTION.md`.
5. A privileged assignment consumes its approval inside the assignment transaction. A failed assignment after consumption rolls back with it.
6. Role-to-permission mappings are not defined. Tests build their own fixture roles.
7. `config/` is now copied by `scripts/bootstrap-laravel.sh` (one-line change).

## How to run
1. Run the Task 0 baseline on `main` first and review `EVIDENCE.md`.
2. Then push this branch and run the same harness. Send the evidence back. Fix failures from the logs.
