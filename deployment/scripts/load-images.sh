#!/bin/sh
set -eu

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 hydroflow-VERSION-ARCH-images.tar.gz" >&2
    exit 1
fi

BUNDLE=$1
CHECKSUM_FILE="$BUNDLE.sha256"

if [ ! -f "$BUNDLE" ]; then
    echo "ERROR: Image bundle not found: $BUNDLE" >&2
    exit 1
fi

if [ ! -f "$CHECKSUM_FILE" ]; then
    echo "ERROR: Checksum file not found: $CHECKSUM_FILE" >&2
    exit 1
fi

if ! command -v sha256sum >/dev/null 2>&1; then
    echo "ERROR: sha256sum is required to verify the bundle." >&2
    exit 1
fi

if ! command -v gzip >/dev/null 2>&1; then
    echo "ERROR: gzip is required to load the bundle." >&2
    exit 1
fi

BUNDLE_DIR=$(CDPATH= cd -- "$(dirname -- "$BUNDLE")" && pwd)
BUNDLE_NAME=$(basename -- "$BUNDLE")
HOST_MACHINE=$(uname -m)
case "$HOST_MACHINE" in
    x86_64|amd64) HOST_ARCH=amd64 ;;
    aarch64|arm64) HOST_ARCH=arm64 ;;
    *) HOST_ARCH=unknown ;;
esac

case "$BUNDLE_NAME" in
    *-amd64-images.tar.gz) BUNDLE_ARCH=amd64 ;;
    *-arm64-images.tar.gz) BUNDLE_ARCH=arm64 ;;
    *) BUNDLE_ARCH=unknown ;;
esac

if [ "$HOST_ARCH" != "unknown" ] && [ "$BUNDLE_ARCH" != "unknown" ] && [ "$HOST_ARCH" != "$BUNDLE_ARCH" ]; then
    echo "ERROR: Bundle architecture $BUNDLE_ARCH does not match server architecture $HOST_ARCH." >&2
    exit 1
fi

echo "Verifying bundle checksum..."
(cd "$BUNDLE_DIR" && sha256sum -c "$BUNDLE_NAME.sha256")

echo "Loading Docker images..."
gzip -dc "$BUNDLE" | docker load

echo "Offline images loaded successfully:"
docker image ls --format '{{.Repository}}:{{.Tag}}' | grep -E '^(arnur/hydroflow-|mysql:8\.4|cloudflare/cloudflared:latest)' || true
