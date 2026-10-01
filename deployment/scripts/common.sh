#!/bin/sh
set -eu

DEPLOY_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ENV_FILE=${ENV_FILE:-$DEPLOY_DIR/.env.docker}
COMPOSE_FILE=${COMPOSE_FILE:-$DEPLOY_DIR/docker-compose.yml}

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: Environment file not found: $ENV_FILE" >&2
    echo "Copy .env.example to .env.docker and complete its values." >&2
    exit 1
fi

compose() {
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

require_configured_env() {
    if grep -Eq '=CHANGE_THIS_' "$ENV_FILE"; then
        echo "ERROR: .env.docker still contains a CHANGE_THIS_ value." >&2
        exit 1
    fi

    for key in HYDROFLOW_DISTRIBUTION HYDROFLOW_PULL_POLICY HYDROFLOW_REGISTRY HYDROFLOW_VERSION APP_KEY APP_URL APP_VERSION CUSTOMER_ID INSTALLATION_ID DB_PASSWORD MYSQL_PASSWORD MYSQL_ROOT_PASSWORD MQTT_PASSWORD MQTT_BROKER_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET REVERB_PUBLIC_HOST; do
        if ! grep -Eq "^${key}=.+$" "$ENV_FILE"; then
            echo "ERROR: Required value ${key} is empty or missing in .env.docker." >&2
            exit 1
        fi
    done

    value_of() {
        grep -E "^$1=" "$ENV_FILE" | tail -n 1 | cut -d= -f2-
    }

    [ "$(value_of DB_PASSWORD)" = "$(value_of MYSQL_PASSWORD)" ] || { echo "ERROR: DB_PASSWORD must match MYSQL_PASSWORD." >&2; exit 1; }
    [ "$(value_of MQTT_PASSWORD)" = "$(value_of MQTT_BROKER_PASSWORD)" ] || { echo "ERROR: MQTT_PASSWORD must match MQTT_BROKER_PASSWORD." >&2; exit 1; }
    [ "$(value_of APP_VERSION)" = "$(value_of HYDROFLOW_VERSION)" ] || { echo "ERROR: APP_VERSION must match HYDROFLOW_VERSION." >&2; exit 1; }

    DISTRIBUTION=$(value_of HYDROFLOW_DISTRIBUTION)
    case "$DISTRIBUTION" in
        registry)
            [ "$(value_of HYDROFLOW_PULL_POLICY)" != "never" ] || { echo "ERROR: Registry distribution cannot use pull policy 'never'." >&2; exit 1; }
            ;;
        offline)
            [ "$(value_of HYDROFLOW_PULL_POLICY)" = "never" ] || { echo "ERROR: Offline distribution must use HYDROFLOW_PULL_POLICY=never." >&2; exit 1; }
            ;;
        *)
            echo "ERROR: HYDROFLOW_DISTRIBUTION must be 'registry' or 'offline'." >&2
            exit 1
            ;;
    esac

    compose config --quiet
}

distribution_mode() {
    grep -E '^HYDROFLOW_DISTRIBUTION=' "$ENV_FILE" | tail -n 1 | cut -d= -f2-
}

require_offline_images() {
    version=$(grep -E '^HYDROFLOW_VERSION=' "$ENV_FILE" | tail -n 1 | cut -d= -f2-)
    registry=$(grep -E '^HYDROFLOW_REGISTRY=' "$ENV_FILE" | tail -n 1 | cut -d= -f2-)

    for image in "$registry/hydroflow-app:$version" "$registry/hydroflow-nginx:$version" "$registry/hydroflow-mqtt:$version" "mysql:8.4" "redis:7-alpine"; do
        if ! docker image inspect "$image" >/dev/null 2>&1; then
            echo "ERROR: Offline image is not loaded: $image" >&2
            echo "Run ./scripts/load-images.sh before installation." >&2
            exit 1
        fi
    done

    if grep -Eq '^CLOUDFLARE_TUNNEL_TOKEN=.+$' "$ENV_FILE" && ! docker image inspect cloudflare/cloudflared:latest >/dev/null 2>&1; then
        echo "ERROR: Cloudflare Tunnel is enabled but its offline image is not loaded." >&2
        exit 1
    fi
}

tunnel_args() {
    if grep -Eq '^CLOUDFLARE_TUNNEL_TOKEN=.+$' "$ENV_FILE"; then
        printf '%s' '--profile=tunnel'
    fi
}
