#!/usr/bin/env sh
set -eu

cd /var/www/html

TARGET_ENV_FILE="${LARAVEL_ENV_FILE:-.env}"

[ -f "$TARGET_ENV_FILE" ] || exit 1
[ -f vendor/autoload.php ] || exit 1

php <<'PHP'
<?php

$driver = getenv('DB_CONNECTION') ?: 'mysql';

if ($driver !== 'mysql') {
    exit(0);
}

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$timeout = max(1, (int) (getenv('DB_CONNECT_TIMEOUT') ?: 5));

if ($database === '') {
    fwrite(STDERR, "Missing DB_DATABASE\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database),
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $timeout,
        ]
    );

    $pdo->query('SELECT 1');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
PHP
