#!/bin/sh
# Background Edge Sync runner for the offline shop PC.
# Pushes local queued sales/shifts/tips/stock deltas to Online HQ and pulls catalog/staff/web orders.
set -eu

if ! docker info >/dev/null 2>&1; then
    if sudo docker info >/dev/null 2>&1; then
        exec sudo "$0" "$@"
    fi
    exit 0
fi

if ! docker ps -q -f "name=^/smarttrade-client-app$" | grep -q .; then
    exit 0
fi

docker exec -u www-data smarttrade-client-app php /var/www/html/public/api/edge_sync.php --cli >/dev/null 2>&1 || true
