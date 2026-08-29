#!/bin/bash
set -euo pipefail

DEST_WAR="/usr/local/tomcat/webapps/sandboxvsdc.war"
DEST_DIR="/usr/local/tomcat/webapps/sandboxvsdc"
SEARCH_ROOT="/opt/zra-api"

find_war() {
    find "${SEARCH_ROOT}" -type f -iname '*.war' 2>/dev/null | head -n 1 || true
}

echo "[vsdc] Looking for a ZRA sandbox WAR under ${SEARCH_ROOT}..."
WAR="$(find_war)"

if [ -z "${WAR}" ]; then
    if [ -f "${DEST_WAR}" ] || [ -d "${DEST_DIR}" ]; then
        echo "[vsdc] No new WAR in /api; using the previously deployed sandboxvsdc."
    else
        echo "[vsdc] Waiting up to 60s for a .war file to appear in api/ ..."
        for _ in $(seq 1 30); do
            WAR="$(find_war)"
            if [ -n "${WAR}" ]; then
                break
            fi
            sleep 2
        done
        if [ -z "${WAR}" ]; then
            echo "[vsdc] ERROR: No .war file found in /api or /api/sandboxvsdc."
            echo "[vsdc] Copy the ZRA sandbox WAR there and restart: docker compose up -d vsdc"
            exit 1
        fi
    fi
fi

if [ -n "${WAR}" ]; then
    echo "[vsdc] Deploying ${WAR} as sandboxvsdc.war"
    cp -f "${WAR}" "${DEST_WAR}"
fi

echo "[vsdc] JAVA_OPTS=${JAVA_OPTS:-}"
exec catalina.sh run
