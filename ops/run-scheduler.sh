#!/usr/bin/env bash

set -u
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
REPO_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd -P)"
DDEV_BIN="/usr/bin/ddev"
RUNTIME_DIR="$REPO_DIR/runtime/scheduler"
LOG_FILE="$RUNTIME_DIR/scheduler.log"
LOCK_FILE="$RUNTIME_DIR/scheduler.lock"
MAX_LOG_BYTES=1048576

if ! mkdir -p "$RUNTIME_DIR"; then
    printf '[%s] scheduler failed: could not create %s\n' "$(date --iso-8601=seconds)" "$RUNTIME_DIR" >&2
    exit 1
fi

log() {
    printf '[%s] %s\n' "$(date --iso-8601=seconds)" "$*" >> "$LOG_FILE"
}

if ! exec 9>"$LOCK_FILE"; then
    printf '[%s] scheduler failed: could not open %s\n' "$(date --iso-8601=seconds)" "$LOCK_FILE" >&2
    exit 1
fi

if ! /usr/bin/flock -n 9; then
    if ! log 'scheduler skipped: host lock is held'; then
        printf '[%s] scheduler skipped: host lock is held\n' "$(date --iso-8601=seconds)" >&2
        exit 1
    fi
    exit 0
fi

if ! touch "$LOG_FILE"; then
    printf '[%s] scheduler failed: could not open %s\n' "$(date --iso-8601=seconds)" "$LOG_FILE" >&2
    exit 1
fi

if [[ -f "$LOG_FILE" && $(wc -c < "$LOG_FILE") -ge $MAX_LOG_BYTES ]]; then
    mv -f "$LOG_FILE" "$LOG_FILE.1" || {
        log 'scheduler failed: could not rotate log'
        exit 1
    }
fi

if [[ ! -x "$DDEV_BIN" ]]; then
    if ! log "scheduler failed: DDEV not available at $DDEV_BIN"; then
        printf '[%s] scheduler failed: DDEV not available at %s\n' "$(date --iso-8601=seconds)" "$DDEV_BIN" >&2
    fi
    exit 1
fi

if ! cd -- "$REPO_DIR"; then
    log "scheduler failed: could not enter $REPO_DIR"
    exit 1
fi

log 'scheduler invoked'
"$DDEV_BIN" artisan schedule:run >> "$LOG_FILE" 2>&1
EXIT_CODE=$?
log "scheduler finished: exit_code=$EXIT_CODE"

exit "$EXIT_CODE"