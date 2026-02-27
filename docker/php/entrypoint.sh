#!/usr/bin/env sh
set -e

cd /var/www/html

run_with_retry() {
    max_attempts="$1"
    delay_seconds="$2"
    shift 2

    attempt=1
    while true; do
        if "$@"; then
            return 0
        fi

        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "ERROR: command failed after ${max_attempts} attempts: $*" >&2
            return 1
        fi

        echo "WARN: command failed (attempt ${attempt}/${max_attempts}), retrying in ${delay_seconds}s: $*" >&2
        attempt=$((attempt + 1))
        sleep "$delay_seconds"
    done
}

should_seed_on_empty_users_table() {
    php <<'PHP'
<?php

$connection = getenv('DB_CONNECTION') ?: 'mysql';
if ($connection !== 'mysql') {
    echo '0';
    exit(0);
}

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

if ($database === '') {
    echo '0';
    exit(0);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $database);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $existsQuery = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'";
    $usersTableExists = (int) $pdo->query($existsQuery)->fetchColumn();
    if ($usersTableExists <= 0) {
        echo '0';
        exit(0);
    }

    $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo $usersCount === 0 ? '1' : '0';
} catch (Throwable) {
    echo '0';
}
PHP
}

if [ ! -f .env ]; then
    if [ -f .env.docker.example ]; then
        cp .env.docker.example .env
    else
        cp .env.example .env
    fi
fi

if [ ! -f vendor/autoload.php ]; then
    run_with_retry 5 5 composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f composer.lock ] && [ composer.lock -nt vendor/autoload.php ]; then
    run_with_retry 5 5 composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    run_with_retry 20 3 php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS_ON_EMPTY_DB:-1}" = "1" ]; then
    should_seed="$(should_seed_on_empty_users_table)"
    if [ "$should_seed" = "1" ]; then
        run_with_retry 10 3 php artisan db:seed --force --no-interaction
    fi
fi

# Docker profile expects ffmpeg preinstalled in the image.
# Fail fast if it is missing so IPTV transcode does not break silently.
if [ -z "${IPTV_FFMPEG_BIN:-}" ]; then
    IPTV_FFMPEG_BIN="/usr/bin/ffmpeg"
fi

if [ -x "${IPTV_FFMPEG_BIN}" ] || command -v "${IPTV_FFMPEG_BIN}" >/dev/null 2>&1; then
    : # ok
else
    echo "ERROR: IPTV_FFMPEG_BIN is not executable: ${IPTV_FFMPEG_BIN}" >&2
    exit 1
fi

export IPTV_FFMPEG_BIN

# Keep the storage symlink relative so the same workspace works
# both in Docker and in local `php artisan serve`.
if [ -L public/storage ]; then
    current_target="$(readlink public/storage || true)"
    if [ "$current_target" != "../storage/app/public" ]; then
        rm -f public/storage
    fi
fi

if [ ! -e public/storage ]; then
    ln -s ../storage/app/public public/storage >/dev/null 2>&1 || true
fi

exec "$@"
