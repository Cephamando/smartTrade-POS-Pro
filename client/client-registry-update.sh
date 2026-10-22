#!/bin/sh
# Pull the sealed image from GitLab Container Registry and update the client stack.
# Automatically elevates to root, locks down .env, and scrubs registry tokens into /root/.docker/config.json.
set -eu

if [ "$(id -u)" -ne 0 ]; then
    exec sudo "$0" "$@"
fi

cd "$(dirname "$0")/.."
ROOT=$(pwd)

if ! docker info >/dev/null 2>&1; then
    echo "Docker is not running. Start it with: sudo service docker start" >&2
    exit 1
fi

ENV_FILE=$ROOT/.env
if [ ! -f "$ENV_FILE" ]; then
    echo "Copy .env.example to .env and set the database password before starting." >&2
    exit 1
fi

chmod 600 "$ENV_FILE"

# One-time self-shredding bootstrap for REGISTRY_USER and REGISTRY_TOKEN
REG_USER=$(grep -E '^REGISTRY_USER=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^["'"'"']//;s/["'"'"']$//' || true)
REG_TOKEN=$(grep -E '^REGISTRY_TOKEN=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^["'"'"']//;s/["'"'"']$//' || true)

if [ -n "$REG_USER" ] && [ -n "$REG_TOKEN" ]; then
    echo "Authenticating root with registry.gitlab.com and scrubbing credentials from .env..."
    mkdir -p /root/.docke
    chmod 700 /root/.docke
    printf '%s\n' "$REG_TOKEN" | docker login registry.gitlab.com -u "$REG_USER" --password-stdin
    if [ -f /root/.docker/config.json ]; then
        chmod 600 /root/.docker/config.json
    fi
    grep -v -E '^(REGISTRY_USER|REGISTRY_TOKEN)=' "$ENV_FILE" > "${ENV_FILE}.tmp"
    mv "${ENV_FILE}.tmp" "$ENV_FILE"
    chmod 600 "$ENV_FILE"
fi

# Ensure CLIENT_APP_IMAGE points to the GitLab registry unless explicitly overridden
IMAGE=$(grep -E '^CLIENT_APP_IMAGE=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '[:space:]' || true)
export CLIENT_APP_IMAGE="${IMAGE:-registry.gitlab.com/cephamando/smarttrade-pos-pro:sealed}"

volume_for() {
    container=$1
    destination=$2
    if docker inspect "$container" >/dev/null 2>&1; then
        docker inspect "$container" --format '{{range .Mounts}}{{if eq .Destination "'"$destination"'"}}{{.Name}}{{end}}{{end}}'
    fi
}

DB_VOLUME=$(volume_for smartTradePOS_db /var/lib/mysql || true)
if [ -z "$DB_VOLUME" ]; then
    DB_VOLUME=$(volume_for smarttrade-client-db /var/lib/mysql || true)
fi

VSDC_VOLUME=$(volume_for vsdc /usr/local/tomcat/webapps || true)
if [ -z "$VSDC_VOLUME" ]; then
    VSDC_VOLUME=$(volume_for smarttrade-client-vsdc /usr/local/tomcat/webapps || true)
fi

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

echo "Pulling latest image (${CLIENT_APP_IMAGE})..."
"$@" pull web

echo "Starting containers..."
"$@" up -d --no-build

echo "Cleaning up superseded image layers..."
docker image prune -f

PORT=$(grep -E '^APP_PORT=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '[:space:]' || true)
PORT=${PORT:-8090}
echo "Sealed POS is up to date at http://localhost:${PORT}"
