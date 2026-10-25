# GSK Development Environment and Release Runbook

## 1. Minimum developer/AI-agent environment

- Git and repository write access.
- PHP version compatible with the selected Laravel release, Composer 2 and required PHP extensions (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `intl` as needed, and GD/Imagick for image handling).
- MySQL 8 or the explicitly selected MariaDB version; Docker is recommended for disposable local DBs.
- Node.js/npm only if the selected frontend build requires it.
- GitHub repository with Actions enabled and permission to inspect CI logs.
- Sandbox credentials for payment provider and mail/SMS provider when integration tasks begin. Secrets belong in CI/environment secret stores, never in Git.

## 2. First-run sequence

1. Clone/extract the scaffold into a clean working directory.
2. Read `docs/AI_DEVELOPER_MASTER_SPEC.md` and `docs/AI_IMPLEMENTATION_TASKS.md`.
3. Run `composer validate` and inspect `composer.json`/`composer.lock`.
4. Run the bootstrap script only into an empty target directory. Review its output and resulting Laravel configuration.
5. Copy `.env.example` to `.env`; generate an app key; configure a disposable database.
6. Start the database and wait for readiness; confirm the DB name is a test-only database.
7. Run `php artisan migrate:fresh --seed` only against that disposable database.
8. Run `php artisan route:list`, Pint, static analysis and `php artisan test`.
9. Fix every failure; commit; open a pull request and require CI.

## 3. CI requirements

CI must use a MySQL service matching production, install from the committed lock file, cache Composer safely, wait for database health, set explicit test-only environment variables, run schema migration and seed, run formatter check/static analysis/security audit/tests, and publish logs/artifacts on failure. Use branch protection requiring green checks and code review. A workflow file existing in the repo is not evidence that the workflow ran.

## 4. Production safety checklist

- [ ] Production secrets stored in hosting secret/environment configuration; no secrets in Git or build artifacts.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS and secure cookies enabled.
- [ ] Web root points to Laravel `public/` only.
- [ ] Private documents, welfare attachments, logs, backups and `.env` are outside public web access.
- [ ] Dedicated least-privilege database user; no sample/dev accounts or passwords.
- [ ] Scheduled tasks configured and monitored; queue workers supervised and restarted on deploy.
- [ ] Backups encrypted, access-controlled, retention-configured and restore-tested.
- [ ] Error reporting, uptime, disk, queue, scheduler, payment reconciliation and welfare escalation alerts configured.
- [ ] Rate limits, WAF/bot controls and upload quarantine/scanning enabled.
- [ ] Payment live mode remains disabled until provider onboarding, webhooks, refund/reconciliation and finance UAT pass.
- [ ] Recurring donations disabled until provider/rail mandate, cancellation, retry and reconciliation capabilities are verified.
- [ ] Welfare response targets, named primary/backup coverage and emergency copy approved and tested.
- [ ] Retention periods, legal holds, cross-border safeguards and notices approved by responsible legal/privacy owner.
- [ ] Employer verification policy, evidence retention and re-verification rules approved.
- [ ] UAT sign-off, security review, deployment rehearsal and rollback plan recorded.

## 5. cPanel VPS deployment outline

1. Provision a supported PHP runtime and extensions; install Composer 2; create a database/user with least privilege.
2. Deploy source outside the public document root. Configure the domain document root to the app's `public/` directory only.
3. Inject production `.env` values via secure hosting configuration; generate a unique app key before storing real data; configure mail, cache, queue, storage, payment and monitoring credentials.
4. Build/install production dependencies with the committed lock file (`composer install --no-dev --prefer-dist --optimize-autoloader`). Build frontend assets if applicable.
5. Run backup before schema change; run reviewed forward migrations (never `migrate:fresh` in production); run approved seeders only if idempotent and production-safe.
6. Apply `php artisan config:cache`, `route:cache` where compatible, `view:cache`, storage link only for non-sensitive public assets, and restart queue workers.
7. Configure scheduler cron once per minute; supervise queue workers using supported cPanel/VPS process management.
8. Smoke-test health, login/MFA, public pages, private file access denial, email, payment sandbox/live-mode status and welfare escalation route.
9. Monitor logs/metrics; retain deployment artifact and rollback instructions. For rollback, restore code and apply a reviewed forward-fix or restore a coordinated DB backup only under an approved recovery plan.

## 6. Go-live decision

The project owner must receive a release evidence pack: commit SHA, CI URL, migration/test output, coverage, security scan, accessibility report, payment sandbox/reconciliation proof, backup restore proof, safeguarding UAT, privacy/legal approvals, known issues and rollback instructions. Do not launch while a critical acceptance gate is unchecked.
