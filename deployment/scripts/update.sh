#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

require_configured_env
PROFILE=$(tunnel_args)
MODE=$(distribution_mode)

"$DEPLOY_DIR/scripts/backup.sh"
if [ "$MODE" = "registry" ]; then
    compose pull
else
    require_offline_images
fi
compose run --rm app php artisan migrate --force

if [ -n "$PROFILE" ]; then
    compose --profile tunnel up -d --remove-orphans
else
    compose up -d --remove-orphans
fi

compose ps
echo "Update completed. Keep the generated backup until verification is complete."
