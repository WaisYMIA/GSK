#!/usr/bin/env bash
set -Eeuo pipefail

# Run from the extracted GSK scaffold directory. Creates a fresh Laravel 12 app
# beside the scaffold, then merges GSK source files into it.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-${ROOT}/../gsk-app}"
TARGET="$(mkdir -p "$(dirname "$TARGET")" && cd "$(dirname "$TARGET")" && pwd)/$(basename "$TARGET")"

if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: Composer is required. Install Composer 2 and retry." >&2; exit 2
fi
if [[ -e "$TARGET" && -n "$(find "$TARGET" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]]; then
  echo "ERROR: target directory is not empty: $TARGET" >&2; exit 2
fi

composer create-project laravel/laravel "$TARGET" "^12.0" --no-interaction
cd "$TARGET"
# Task 1: sessions are the web authentication method (no Sanctum tokens). Fortify provides two-factor authentication.
composer require laravel/fortify --no-interaction

# Merge application source. The scaffold intentionally replaces generated User,
# seeder, API routes, and tests; generated framework bootstrap/config files remain.
for path in app config database resources routes tests; do
  cp -a "$ROOT/$path/." "$TARGET/$path/"
done
mkdir -p "$TARGET/docs"
cp -a "$ROOT/docs/." "$TARGET/docs/"

# Register the GSK auth provider in the Laravel 12 providers file, fail clearly if the file differs.
if ! grep -q "GskAuthServiceProvider" "$TARGET/bootstrap/providers.php"; then
  sed -i 's#^\];#    App\\Providers\\GskAuthServiceProvider::class,\n];#' "$TARGET/bootstrap/providers.php"
fi
if ! grep -q "GskAuthServiceProvider" "$TARGET/bootstrap/providers.php"; then
  echo "ERROR: could not register GskAuthServiceProvider in bootstrap/providers.php." >&2; exit 3
fi

# Register routes/api.php (install:api used to do this; it is no longer run because it installs Sanctum).
if ! grep -q "routes/api.php" "$TARGET/bootstrap/app.php"; then
  sed -i "s#^\(\s*\)web: __DIR__.'/../routes/web.php',#\1web: __DIR__.'/../routes/web.php',\n\1api: __DIR__.'/../routes/api.php',#" "$TARGET/bootstrap/app.php"
fi
# Fail clearly if the framework skeleton differs instead of silently proceeding.
if ! grep -q "routes/api.php" "$TARGET/bootstrap/app.php"; then
  echo "ERROR: Laravel API route registration was not found in bootstrap/app.php." >&2
  echo "Inspect the generated Laravel 12 bootstrap file and register routes/api.php." >&2
  exit 3
fi

# Configure a local MySQL connection only if the generated .env still has defaults.
if [[ -f "$ROOT/.env.example" ]]; then
  cp "$ROOT/.env.example" "$TARGET/.env.gsk.example"
fi
php artisan key:generate
php artisan storage:link --no-interaction || true
php artisan config:clear

echo
echo "Laravel application created at: $TARGET"
echo "Next: set DB_* values in $TARGET/.env for a disposable database, then run:"
echo "  cd '$TARGET'"
echo "  php artisan migrate:fresh --seed"
echo "  php artisan test"
echo "WARNING: migrate:fresh DROPS TABLES in the configured database. Use a disposable development/test database only."
