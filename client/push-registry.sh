#!/bin/sh
# Build the sealed client image and push it to GitLab Container Registry.
set -eu

cd "$(dirname "$0")/.."
ROOT=$(pwd)

IMAGE="${CLIENT_APP_IMAGE:-registry.gitlab.com/cephamando/smarttrade-pos-pro:sealed}"

if ! docker info >/dev/null 2>&1; then
    if sudo docker info >/dev/null 2>&1; then
        exec sudo "$ROOT/client/push-registry.sh" "$@"
    fi
    echo "Docker is not running. In Ubuntu run: sudo service docker start" >&2
    exit 1
fi

echo "Building ${IMAGE}..."
docker build --pull -f "$ROOT/client/Dockerfile" -t "$IMAGE" -t smarttradepos-pro-client:sealed "$ROOT"

echo "Pushing ${IMAGE}..."
docker push "$IMAGE"
echo "Done. Pushed ${IMAGE}"
