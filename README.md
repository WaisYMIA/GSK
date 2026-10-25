# Requirements baseline notice — v2.0

Before implementation, read `docs/GSK_SRS_WORKFLOWS_AND_TASKS_v2.md`. The task pack has been aligned to its workflow and Task 1–8 order. This repository remains a scaffold, not a complete or production-ready application. Start with Task 0 baseline inspection, then Task 1 only; stop at each acceptance gate.

---

# Global Skills Kenya — Laravel implementation scaffold

This is a Laravel source scaffold with a repeatable bootstrap script and CI workflow; it is not a fully installed/running Laravel application yet. The preparation environment has PHP but no Composer, MySQL client/server, PDO database driver, or Docker, and outbound DNS/network access is unavailable, so framework dependencies could not be installed and migrations/tests could not be executed here.

## Target stack
- Laravel 12 (confirm current supported version and PHP compatibility at project kickoff)
- PHP 8.3+; MySQL 8 / MariaDB supported by the selected Laravel release
- Laravel Sanctum for API authentication; policies for object-level authorization
- Queue-backed email notifications; private object/file storage
- cPanel VPS with document root pointed at `public/`, never the project root

## Bootstrap a real Laravel application

The scaffold now includes an automated bootstrap script and CI test workflow. A real Laravel install still requires Composer and access to Packagist; those dependencies cannot be bundled into this source-only archive.

1. Install PHP 8.3+, Composer 2, and the PHP extensions required by Laravel (including `pdo_mysql`).
2. From this extracted directory, run `./scripts/bootstrap-laravel.sh ../gsk-app`. The script creates a clean Laravel 12 project, installs Sanctum, and merges the GSK source files while retaining the generated framework bootstrap/configuration.
3. Start a disposable local MySQL database with Docker Compose if Docker is available: `docker compose -f compose.mysql.yml up -d`. This exposes MySQL on `127.0.0.1:3307` with local-only development credentials defined in the compose file.
4. In `gsk-app/.env`, set `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=gsk_test`, `DB_USERNAME=gsk_app`, and `DB_PASSWORD=gsk_local_only_password` to match the compose service.
5. Run `php artisan migrate:fresh --seed` **only against the disposable database**. This command drops existing tables in the selected database.
6. Run `php artisan test`. The GitHub Actions workflow `.github/workflows/laravel-tests.yml` automates the same bootstrap, MySQL migration, seeding and test sequence on pushes and pull requests.

Do not use the sample database credentials or seeded accounts in staging or production. Do not point `migrate:fresh` at any database containing real data.

## Included
- Core schema migrations and Eloquent models
- Example factories and non-production seed data
- Authorization policy examples
- API route skeletons and a welfare intake controller
- Feature-test skeletons, including security and policy gates
- Six-decision policy register and release gates

## Engineering status and limitations

The included workflow is intended to verify the first executable milestone in a network-enabled CI environment. It has not been run in this preparation environment, so there is no claim yet that migrations succeed or tests pass. Four feature tests are intentionally marked incomplete and must be implemented before release. A green CI run will establish the bootstrapping baseline, not production readiness.

## Not yet implemented
Payment-provider integration, recurring mandates, SMS/email delivery, secure messaging UI, file scanning, employer KYC integrations, consent-management UI, admin/staff RBAC management screens, full public frontend, observability, and production deployment. The tests marked `markTestIncomplete` are deliberately not falsely reported as passing.
