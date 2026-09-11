#!/bin/bash
# WWI Update from Git — ejecutar en el VPS: bash /opt/wwi-src/deploy/wwi-update.sh
# Flujo: git pull -> .env de secretos -> vendor -> docker build -> recrear contenedores del manifiesto -> smoke test
set -e
SRC="${WWI_SRC:-/opt/wwi-src}"
cd "$SRC"

echo "== 1. git pull =="
git pull --ff-only origin main
git log --oneline -1

echo "== 2. env de producción =="
if [ -f /root/wwi-secrets/.env ]; then
    cp /root/wwi-secrets/.env "$SRC/.env"
else
    echo "WARN: /root/wwi-secrets/.env no existe — se usará .env.example"
fi

echo "== 3. vendor (autoload) =="
if [ ! -d "$SRC/vendor" ]; then
    cp -r /tmp/wontia-build/app/vendor "$SRC/vendor" 2>/dev/null || echo "WARN: vendor no encontrado"
fi

echo "== 4. docker build =="
docker build -t wontia-web-intelligence:latest . 2>&1 | tail -1

echo "== 5. recrear contenedores =="
while IFS='|' read -r name port site app_url app_name keyfile volume extra; do
    [ -z "$name" ] && continue
    case "$name" in \#*) continue ;; esac
    printf -- "-- %s (port %s, site %s)\n" "$name" "$port" "$site"
    KEY=""
    if [ -n "$keyfile" ] && [ -f "$keyfile" ]; then KEY=$(cat "$keyfile"); fi
    ARGS="-d --name $name --network intsolcom -p $port:80 -e SITE_ID=$site"
    [ -n "$app_url" ] && ARGS="$ARGS -e APP_URL=$app_url"
    [ -n "$app_name" ] && ARGS="$ARGS -e APP_NAME=$app_name"
    [ -n "$KEY" ] && ARGS="$ARGS -e BRICK_API_KEY=$KEY"
    [ -n "$volume" ] && ARGS="$ARGS -v $volume:/app/public/assets/uploads"
    [ -n "$extra" ] && ARGS="$ARGS $extra"
    docker rm -f "$name" >/dev/null 2>&1 || true
    docker run $ARGS --restart unless-stopped wontia-web-intelligence:latest >/dev/null
done < "$SRC/deploy/containers.conf"

echo "== 6. smoke test =="
sleep 3
for p in 4003 4004 4009 4010; do
    printf "port %s: " "$p"
    curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:$p/api/v1/health" || true
done
echo "UPDATE_OK"
