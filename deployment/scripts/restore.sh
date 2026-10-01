#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 BACKUP.dump [--yes]" >&2
    exit 1
fi

BACKUP_FILE=$1
if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file not found: $BACKUP_FILE" >&2
    exit 1
fi

if [ "${2:-}" != "--yes" ]; then
    printf 'Restore will overwrite the current Hydroflow database. Type RESTORE to continue: '
    read -r CONFIRMATION
    [ "$CONFIRMATION" = "RESTORE" ] || { echo "Restore cancelled."; exit 1; }
fi

require_configured_env
compose stop app telemetry-subscriber reverb scheduler
compose exec -T postgres sh -c 'exec pg_restore --clean --if-exists --no-owner --no-acl -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$BACKUP_FILE"
compose up -d app telemetry-subscriber reverb scheduler nginx
echo "Database restored from: $BACKUP_FILE"
