#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 BACKUP.sql.gz [--yes]" >&2
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
RESTORE_SQL="$DEPLOY_DIR/.restore-$$.sql"
trap 'rm -f "$RESTORE_SQL"' EXIT HUP INT TERM
gzip -dc "$BACKUP_FILE" > "$RESTORE_SQL"
compose stop app queue reverb scheduler
compose exec -T mysql sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$RESTORE_SQL"
compose up -d app queue reverb scheduler nginx
echo "Database restored from: $BACKUP_FILE"
