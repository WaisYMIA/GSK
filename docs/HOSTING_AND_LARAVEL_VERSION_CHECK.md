# Hosting and Laravel version check (to complete before pinning the framework)

Status: **open**. The VPS has not been inspected. Do not build a Laravel 13 kit (r5), and do not pin Laravel 12 as the long-term baseline, until this check is done.

## Reported by search and review (to be re-checked against Laravel's official pages)
- Laravel 12: security fixes until 24 February 2027 (bug fixes already ended).
- Laravel 13: requires PHP 8.3 to 8.5; security fixes reported until 17 March 2028.
Verify on laravel.com ("Release Notes", "Upgrade Guide") on the day, and record the date checked: ____.

## Commands to run on the VPS (over SSH), with output pasted into the evidence
```
php -v
php -m
composer --version
mysql --version      # or: mysqld --version
cat /etc/os-release
df -h
nproc; free -m
crontab -l
```
Also record: SSH available (yes/no), process manager available for queue workers (Supervisor, systemd), outbound ports for email, document root control, PHP-FPM or LiteSpeed, backup facility.

## Compatibility checks to run in a scratch directory (not in production)
1. `composer create-project laravel/laravel check-13 "^13.0"` succeeds on PHP 8.3 or newer.
2. `composer require laravel/fortify` resolves under that version.
3. Read the official 12-to-13 upgrade notes. Check the items that affect this scaffold: dependency updates, CSRF and request-forgery changes, session and auth defaults.
4. Run the Task 0 harness against the result.

## Decision table
| Finding | Action |
|---|---|
| PHP 8.3 or newer on the VPS and steps 1 to 4 pass | Prepare r5 for Laravel 13 |
| PHP older than 8.3 and the host cannot upgrade | Report the limitation and options (move host, or pin Laravel 12 with a dated upgrade plan) for the owner's decision |
| Fortify does not resolve under 13 | Report, and consider Laravel 12 until it does |

Record the selected PHP and Laravel versions, the dependency checks and the date here: ____
