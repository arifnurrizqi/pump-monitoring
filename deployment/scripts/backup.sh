#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

require_configured_env
BACKUP_DIR=${BACKUP_DIR:-$DEPLOY_DIR/backups}
mkdir -p "$BACKUP_DIR"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP_FILE="$BACKUP_DIR/hydroflow-$STAMP.sql.gz"
RAW_BACKUP="$BACKUP_DIR/.hydroflow-$STAMP.sql"
trap 'rm -f "$RAW_BACKUP"' EXIT HUP INT TERM

compose exec -T mysql sh -c 'exec mysqldump --single-transaction --quick --lock-tables=false -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > "$RAW_BACKUP"
gzip -c "$RAW_BACKUP" > "$BACKUP_FILE"
chmod 600 "$BACKUP_FILE"
echo "Database backup created: $BACKUP_FILE"
