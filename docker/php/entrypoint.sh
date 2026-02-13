#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    if [ -f .env.docker.example ]; then
        cp .env.docker.example .env
    else
        cp .env.example .env
    fi
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f composer.lock ] && [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force --no-interaction
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
