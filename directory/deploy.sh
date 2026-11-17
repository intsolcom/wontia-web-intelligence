#!/bin/sh
set -eu

APP="${1:-/tmp/wontia-build/app}"
ENV_FILE="${ENV_FILE:-/var/lib/dokploy/wontia-directory.env}"
UPLOADS="${UPLOADS:-/var/lib/dokploy/uploads/wontia-sonika-com-co}"
IMAGE="wontia-directory:latest"
PREVIOUS="wontia-directory:previous"
CONTAINER="wontia-directory"

if [ ! -d "$APP/directory" ]; then
    echo "No directory app found in $APP" >&2
    exit 1
fi

docker tag "$IMAGE" "$PREVIOUS" 2>/dev/null || true
docker build -f "$APP/directory/Dockerfile" -t "$IMAGE" "$APP"

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d \
    --name "$CONTAINER" \
    --network intsolcom \
    -p 4018:80 \
    -v "$UPLOADS:/app/public/assets/uploads:ro" \
    --env-file "$ENV_FILE" \
    -e APP_URL=https://sonika.com.co \
    -e SITE_ID=12 \
    --restart unless-stopped \
    "$IMAGE" >/dev/null

i=0
while [ "$i" -lt 20 ]; do
    if curl -fsS -m 2 http://127.0.0.1:4018/directorio/health >/dev/null 2>&1; then
        echo "Directorio OK"
        exit 0
    fi
    i=$((i + 1))
    sleep 1
done

echo "Directorio health check failed; rolling back" >&2
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d \
    --name "$CONTAINER" \
    --network intsolcom \
    -p 4018:80 \
    -v "$UPLOADS:/app/public/assets/uploads:ro" \
    --env-file "$ENV_FILE" \
    -e APP_URL=https://sonika.com.co \
    -e SITE_ID=12 \
    --restart unless-stopped \
    "$PREVIOUS" >/dev/null
exit 1
