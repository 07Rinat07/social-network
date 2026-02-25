#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f package-lock.json ]; then
    echo "ERROR: package-lock.json not found in /var/www/html" >&2
    exit 1
fi

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

if command -v sha256sum >/dev/null 2>&1; then
    lock_hash="$(sha256sum package-lock.json | awk '{print $1}')"
else
    lock_hash="$(cksum package-lock.json | awk '{print $1 \"-\" $2}')"
fi

stamp_file="node_modules/.package-lock.hash"
current_hash=""

if [ -f "$stamp_file" ]; then
    current_hash="$(cat "$stamp_file" 2>/dev/null || true)"
fi

if [ ! -d node_modules ] || [ "$current_hash" != "$lock_hash" ]; then
    run_with_retry 4 4 npm ci --no-audit --no-fund
    mkdir -p node_modules
    printf '%s' "$lock_hash" > "$stamp_file"
fi
