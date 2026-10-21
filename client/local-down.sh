#!/bin/sh
# Stop the sealed stack. Docker volumes are left in place.
set -eu

if ! docker info >/dev/null 2>&1; then
    if sudo docker info >/dev/null 2>&1; then
        exec sudo "$0" "$@"
    fi
    echo "Docker is not running." >&2
    exit 1
fi

for name in smarttrade-client-app smarttrade-client-db smarttrade-client-vsdc; do
    if docker ps -q -f "name=^/${name}$" | grep -q .; then
        docker stop "$name" >/dev/null
        echo "Stopped ${name}"
    fi
done
