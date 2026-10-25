# Task 1, Stage 1B report: authentication, MFA and sessions

Branch: `task1/identity-access-1b`, built on `task1/identity-access-1a`. Not merged. Written 14 October 2026.

## Evidence status (read this first)
**Nothing in this branch has been executed.** No PHP, Composer, MySQL, migration, test or CI run was available. All 101 test methods in the repository (across Stage 1A and 1B and the original scaffold) are unrun. Stage 1B depends on **Laravel Fortify**, which was never installed or seen here, so a number of assumptions about its API are unverified (list below). Expect first-run failures and fix them from the CI log.
A static check (`scripts/php-static-check.py`, not a linter) found no unbalanced brackets and no missing imports in the new files, other than classes the framework supplies (`Controller`, `TestCase`).

## Delivered
- **Sign-in and sign-out, password reset, email verification, MFA** through Fortify, wired by `App\Providers\GskAuthServiceProvider` (registered by the bootstrap script) and `config/fortify.php`.
- **Middleware** pushed into the `web` group: `EnsureAccountActive`, `EnsureStaffMfa`, `BlockStaffMfaDisable`.
- **Generic password-reset answer** (no account enumeration), custom password rule, and audit listeners for sign-in, failure, sign-out, reset and Fortify's MFA events.
- **`MfaAdminService`** for administrator MFA resets with independent approval for privileged accounts.
- **Routes and views:** `routes/web.php`, accessible Blade views (no build step, no external assets), MFA setup page, staff invitation page. `routes/api.php` no longer uses Sanctum and `/me` is gone.
- **Migration** for the two-factor columns, **bootstrap script** changes (Fortify installed, Sanctum and `install:api` removed, provider and API routing registered by guarded `sed` edits, `resources/` copied), **`.env.example`** security defaults (`APP_DEBUG=false`, secure session cookie, approvals off).

## Tests
101 test methods in 14 files. Stage 1B added 28 in `tests/Feature/Auth`. 3 original placeholders are still incomplete (Tasks 3, 6, 7).
Register coverage: 32 of the 50 Task 0 and Task 1 rows in `gsk_test_register_v2_2.csv` now have a test method naming the ID.
Covered: GSK-008, GSK-010, GSK-029, GSK-033, GSK-061, GSK-062, GSK-068, GSK-079, GSK-080, GSK-081, GSK-082, GSK-083, GSK-084, GSK-085, GSK-086, GSK-087, GSK-088, GSK-089, GSK-090, GSK-091, GSK-092, GSK-093, GSK-094, GSK-095, GSK-096, GSK-097, SEC-05, SEC-07, SEC-12, BASE-04, BASE-05, GSK-131.
Not covered (18): GSK-004, GSK-006, GSK-007, GSK-017, GSK-034, GSK-060, GSK-063, SEC-03, SEC-04, SEC-09, SEC-10, BASE-01, BASE-02, BASE-03, BASE-06, BASE-07, BASE-08, BASE-09.
- GSK-006, 007, 060 (registration and verification of a self-registered account) need candidate registration, which is not built. The register puts them in Task 1 but candidate onboarding is Task 3. **Proposal: move them to Task 3.**
- SEC-03 (CSRF) and SEC-04 (session fixation) need integration tests, not Laravel feature tests.
- GSK-004, 017, 034, 063 belong to modules not built yet (CMS, employer applicants, exports, candidate profile).
- BASE-01, 02, 03, 06, 07, 08, 09 and SEC-09, 10 are covered by the Task 0 harness and CI, not PHPUnit.

## Unverified assumptions (each one can break on the first run)
1. Which Laravel and Fortify versions install together (see `docs/HOSTING_AND_LARAVEL_VERSION_CHECK.md`).
2. Fortify route names and paths: `login`, `logout`, `password.request`, `password.email`, `password.update`, `verification.notice`, `verification.send`, `two-factor.login`, `password.confirm`, and `/user/two-factor-authentication`, `/user/two-factor-qr-code`, `/user/two-factor-secret-key`, `/user/confirmed-two-factor-authentication`, `/user/two-factor-recovery-codes`.
3. Contract names and signatures: `FailedPasswordResetLinkRequestResponse`, `SuccessfulPasswordResetLinkRequestResponse`, `ResetsUserPasswords::reset($user, array $input)`.
4. Event class names used for the MFA audit events, and that `RecoveryCodeReplayed` and `TwoFactorAuthenticationFailed` carry a `user`.
5. `EnableTwoFactorAuthentication` is invokable with a user and stores the secret and codes encrypted, as the tests assume.
6. `Route::pushMiddlewareToGroup('web', ...)` in a provider boot reaches Fortify's routes and runs before its route middleware.
7. The generated skeleton's `bootstrap/providers.php` and `bootstrap/app.php` look like the sed patterns expect. The script fails with a clear error if they do not. The edits were only tested on look-alike files.
8. A failed JSON sign-in returns 422 and a throttled one 429. The challenge flow needs the `login.id` session value, which the tests supply with `withSession`.
9. The framework provides the `sessions` and `password_reset_tokens` tables.

## Decisions needed
1. **First administrator.** No account may be seeded. Options: (a) a one-time console command run by the technical owner with typed confirmation and an interactive password, creating one staff profile, one account and one narrowly scoped bootstrap role; (b) a documented database procedure; (c) a signed, expiring first invitation printed once. Any option creates a role-to-permission mapping that GSK has not approved.
2. **Recovery codes** encrypted by Fortify, or hashed (needs replacing Fortify's storage).
3. **Move GSK-006, 007, 060 to Task 3.**
4. **Task 0 gate and placeholder tests** (see the Stage 1A report): allow a documented allow-list of future-task placeholders, or move them out of PHPUnit.
5. **Email provider** for verification and reset mail (none approved). Until then mail is not delivered.
6. **Role-to-permission mappings.** Still unapproved, so no role exists outside tests.

## How to run
1. Run the Task 0 harness on `main` and review `EVIDENCE.md`.
2. Push `task1/identity-access-1a`, then `task1/identity-access-1b`, run the harness on each, and send the evidence back. Fix from the logs.
