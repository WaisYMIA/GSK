#!/usr/bin/env bash
# Waits until the disposable MySQL test database accepts connections.
set -u
tries="${1:-40}"
for i in $(seq 1 "$tries"); do
  if php -r 'try{new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"));exit(0);}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}'; then
    echo "database ready after $i attempt(s)"; exit 0
  fi
  sleep 3
done
echo "database not ready after $tries attempts" >&2
exit 1
