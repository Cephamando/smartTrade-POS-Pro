#!/bin/sh
# Build the sealed image and run it on this PC.
# Existing MySQL and VSDC volumes are reused. Nothing is deleted with -v.
set -eu

cd "$(dirname "$0")/.."
ROOT=$(pwd)

if ! docker info >/dev/null 2>&1; then
    if sudo docker info >/dev/null 2>&1; then
        exec sudo "$ROOT/client/local-up.sh" "$@"
    fi
    echo "Docker is not running. In Ubuntu run: sudo service docker start" >&2
    exit 1
fi

ENV_FILE=$ROOT/.env
if [ ! -f "$ENV_FILE" ]; then
    echo "Copy .env.example to .env and set the database password before starting." >&2
    exit 1
fi

echo "Building smarttradepos-pro-client:sealed"
docker build --pull -f "$ROOT/client/Dockerfile" -t smarttradepos-pro-client:sealed "$ROOT"

volume_for() {
    container=$1
    destination=$2
    if docker inspect "$container" >/dev/null 2>&1; then
        docker inspect "$container" --format '{{range .Mounts}}{{if eq .Destination "'"$destination"'"}}{{.Name}}{{end}}{{end}}'
    fi
}

DB_VOLUME=$(volume_for smartTradePOS_db /var/lib/mysql || true)
VSDC_VOLUME=$(volume_for vsdc /usr/local/tomcat/webapps || true)

if [ -z "$DB_VOLUME" ] && docker volume inspect smarttrade-pos-pro_db_data >/dev/null 2>&1; then
    DB_VOLUME=smarttrade-pos-pro_db_data
fi
if [ -z "$VSDC_VOLUME" ] && docker volume inspect smarttrade-pos-pro_vsdc_webapps >/dev/null 2>&1; then
    VSDC_VOLUME=smarttrade-pos-pro_vsdc_webapps
fi

if [ -n "$DB_VOLUME" ] && [ -z "$VSDC_VOLUME" ]; then
    echo "Found database volume ${DB_VOLUME} but no VSDC volume. Not starting, so the device keys stay with that database." >&2
    exit 1
fi
if [ -z "$DB_VOLUME" ] && [ -n "$VSDC_VOLUME" ]; then
    echo "Found VSDC volume ${VSDC_VOLUME} but no database volume. Not starting, so the device keys stay with that database." >&2
    exit 1
fi

# Free port 8090 and release the database files. Volumes are not removed.
for name in smartTradePOS-PRO_app smartTradePOS_db vsdc; do
    if docker ps -q -f "name=^/${name}$" | grep -q .; then
        echo "Stopping ${name}"
        docker stop "$name" >/dev/null
    fi
done

export LOCAL_ENV_FILE=$ENV_FILE
set -- docker compose --env-file "$ENV_FILE" \
    -f "$ROOT/client/docker-compose.yml" \
    -f "$ROOT/client/docker-compose.env.yml"
if [ -n "$DB_VOLUME" ] && [ -n "$VSDC_VOLUME" ]; then
    echo "Using existing database volume ${DB_VOLUME}"
    echo "Using existing VSDC volume ${VSDC_VOLUME}"
    export LOCAL_DB_VOLUME=$DB_VOLUME
    export LOCAL_VSDC_VOLUME=$VSDC_VOLUME
    set -- "$@" -f "$ROOT/client/docker-compose.local.yml"
else
    echo "No existing shop volumes found. MySQL will initialize from docker/mysql/smartTradePOS_db.sql."
fi

"$@" up -d

PORT=$(grep -E '^APP_PORT=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '[:space:]')
PORT=${PORT:-8090}
echo "Sealed POS is starting at http://localhost:${PORT}"
