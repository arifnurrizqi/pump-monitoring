#!/bin/sh
set -eu
. "$(dirname -- "$0")/common.sh"

require_configured_env
PROFILE=$(tunnel_args)
MODE=$(distribution_mode)

if [ "$MODE" = "registry" ]; then
    echo "Pulling versioned Hydroflow images..."
    compose pull
else
    echo "Using preloaded offline images; registry pull is disabled."
    require_offline_images
fi

echo "Starting database and MQTT..."
compose up -d mysql mqtt

echo "Running database migrations..."
compose run --rm app php artisan migrate --force

echo "Starting Hydroflow ${PROFILE:+with Cloudflare Tunnel profile}..."
if [ -n "$PROFILE" ]; then
    compose --profile tunnel up -d --remove-orphans
else
    compose up -d --remove-orphans
fi

compose ps
echo "Installation completed. Verify the dashboard health endpoint at /up."
