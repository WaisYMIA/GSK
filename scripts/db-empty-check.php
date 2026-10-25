<?php
// Refuses to continue if the target database already contains any tables.
// There is no override. A database that is not empty is never treated as disposable.
// Reads connection details from the environment.
$dsn = 'mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE');
try {
    $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    $n = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, 'cannot inspect database: '.$e->getMessage().PHP_EOL);
    exit(2);
}
echo 'existing tables in target database: '.$n.PHP_EOL;
if ($n > 0) {
    fwrite(STDERR, 'REFUSING: the target database is not empty. Recreate a fresh empty database (for the compose file: docker compose down -v, then up) and rerun.'.PHP_EOL);
    exit(1);
}
exit(0);
