#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

require_configured_env
BACKUP_DIR=${BACKUP_DIR:-$DEPLOY_DIR/backups}
mkdir -p "$BACKUP_DIR"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP_FILE="$BACKUP_DIR/hydroflow-$STAMP.dump"
TEMP_BACKUP="$BACKUP_DIR/.hydroflow-$STAMP.dump"
trap 'rm -f "$TEMP_BACKUP"' EXIT HUP INT TERM

compose exec -T postgres sh -c 'exec pg_dump -Fc -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "$TEMP_BACKUP"
mv "$TEMP_BACKUP" "$BACKUP_FILE"
chmod 600 "$BACKUP_FILE"
echo "Database backup created: $BACKUP_FILE"
