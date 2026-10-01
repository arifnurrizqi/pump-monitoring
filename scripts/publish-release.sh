#!/bin/sh
set -eu

if [ "$#" -ne 2 ]; then
    echo "Usage: $0 REGISTRY VERSION" >&2
    echo "Example: $0 ghcr.io/arnur-tech 1.0.0" >&2
    exit 1
fi

REGISTRY=${1%/}
VERSION=$2

case "$VERSION" in
    *[!0-9A-Za-z._-]*|'')
        echo "ERROR: Invalid image version: $VERSION" >&2
        exit 1
        ;;
esac

APP_IMAGE="$REGISTRY/hydroflow-app:$VERSION"
NGINX_IMAGE="$REGISTRY/hydroflow-nginx:$VERSION"
MQTT_IMAGE="$REGISTRY/hydroflow-mqtt:$VERSION"

echo "Building Hydroflow release $VERSION..."
docker build --pull --target app -t "$APP_IMAGE" .
docker build --target nginx -t "$NGINX_IMAGE" .
docker build --pull -f docker/mosquitto/Dockerfile -t "$MQTT_IMAGE" .

echo "Pushing release images..."
docker push "$APP_IMAGE"
docker push "$NGINX_IMAGE"
docker push "$MQTT_IMAGE"

echo "Published Hydroflow $VERSION to $REGISTRY."
