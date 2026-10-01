#!/bin/sh
set -eu

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    echo "Usage: $0 VERSION [linux/amd64|linux/arm64]" >&2
    echo "Example: $0 1.0.0 linux/amd64" >&2
    exit 1
fi

VERSION=$1
PLATFORM=${2:-linux/amd64}

case "$VERSION" in
    *[!0-9A-Za-z._-]*|'')
        echo "ERROR: Invalid version: $VERSION" >&2
        exit 1
        ;;
esac

case "$PLATFORM" in
    linux/amd64|linux/arm64) ;;
    *)
        echo "ERROR: Platform must be linux/amd64 or linux/arm64." >&2
        exit 1
        ;;
esac

for command in docker gzip sha256sum tar; do
    command -v "$command" >/dev/null 2>&1 || { echo "ERROR: Required command not found: $command" >&2; exit 1; }
done

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ARCH=${PLATFORM#linux/}
DIST_DIR="$PROJECT_DIR/dist"
PACKAGE_NAME="hydroflow-$VERSION-$ARCH-offline"
PACKAGE_DIR="$DIST_DIR/$PACKAGE_NAME"
IMAGE_BUNDLE="$PACKAGE_DIR/deployment/hydroflow-$VERSION-$ARCH-images.tar.gz"
RAW_IMAGE_BUNDLE="$DIST_DIR/.hydroflow-$VERSION-$ARCH-images.tar"

if [ -e "$PACKAGE_DIR" ] || [ -e "$DIST_DIR/$PACKAGE_NAME.tar" ]; then
    echo "ERROR: Output already exists for $PACKAGE_NAME. Move it before rebuilding." >&2
    exit 1
fi

mkdir -p "$PACKAGE_DIR/deployment/scripts" "$PACKAGE_DIR/deployment/certificates"
trap 'rm -f "$RAW_IMAGE_BUNDLE"' EXIT HUP INT TERM

cd "$PROJECT_DIR"
echo "Building Hydroflow $VERSION for $PLATFORM..."
docker buildx build --pull --platform "$PLATFORM" --load --target app -t "arnur/hydroflow-app:$VERSION" .
docker buildx build --platform "$PLATFORM" --load --target nginx -t "arnur/hydroflow-nginx:$VERSION" .

echo "Preparing third-party images for a fully offline installation..."
docker pull --platform "$PLATFORM" postgres:17-alpine
docker pull --platform "$PLATFORM" redis:7-alpine
docker pull --platform "$PLATFORM" cloudflare/cloudflared:latest

echo "Exporting Docker image bundle..."
docker save -o "$RAW_IMAGE_BUNDLE" \
    "arnur/hydroflow-app:$VERSION" \
    "arnur/hydroflow-nginx:$VERSION" \
    postgres:17-alpine \
    redis:7-alpine \
    cloudflare/cloudflared:latest
gzip -c "$RAW_IMAGE_BUNDLE" > "$IMAGE_BUNDLE"

(cd "$(dirname -- "$IMAGE_BUNDLE")" && sha256sum "$(basename -- "$IMAGE_BUNDLE")" > "$(basename -- "$IMAGE_BUNDLE").sha256")

cp deployment/docker-compose.yml "$PACKAGE_DIR/deployment/docker-compose.yml"
cp deployment/.env.offline.example "$PACKAGE_DIR/deployment/.env.example"
cp deployment/README.md "$PACKAGE_DIR/deployment/README.md"
cp deployment/INSTALLATION.md "$PACKAGE_DIR/deployment/INSTALLATION.md"
cp LICENSE.md "$PACKAGE_DIR/deployment/LICENSE.md"
cp legal/SURAT_PERNYATAAN_AMANAH_PENGGUNAAN_HYDROFLOW.md "$PACKAGE_DIR/deployment/SURAT_PERNYATAAN_AMANAH.md"
cp deployment/scripts/*.sh "$PACKAGE_DIR/deployment/scripts/"
cp deployment/certificates/.gitkeep "$PACKAGE_DIR/deployment/certificates/.gitkeep"

printf '%s\n' \
    "Hydroflow version: $VERSION" \
    "Platform: $PLATFORM" \
    "Created UTC: $(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    > "$PACKAGE_DIR/RELEASE.txt"

tar -cf "$DIST_DIR/$PACKAGE_NAME.tar" -C "$DIST_DIR" "$PACKAGE_NAME"
(cd "$DIST_DIR" && sha256sum "$PACKAGE_NAME.tar" > "$PACKAGE_NAME.tar.sha256")

echo "Offline release created:"
echo "  $DIST_DIR/$PACKAGE_NAME.tar"
echo "  $DIST_DIR/$PACKAGE_NAME.tar.sha256"
