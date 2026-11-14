#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# smartTrade — Flutterwave Signed Webhook Simulator (v3 & v4 HMAC-SHA256)
# Targets Unified Endpoint: /api/flutterwave_webhook.php
# ==============================================================================

FLW_SECRET_HASH="${FLW_SECRET_HASH:-024006a8632f4c3b852f4a3ba1596b7d57a78d3298f31c3ef2a8c8734cdc6414}"
BRANCH_ID="${BRANCH_ID:-1}"
PRODUCT_ID="${PRODUCT_ID:-1}"
QTY="${QTY:-1}"
CUSTOMER_NAME="${CUSTOMER_NAME:-Chanda Mwale (Webhook Test)}"
CUSTOMER_PHONE="${CUSTOMER_PHONE:-260977123456}"
TX_REF="${TX_REF:-}"
AMOUNT_ZMW="${AMOUNT_ZMW:-}"
FLW_TX_ID="${FLW_TX_ID:-99$(date +%s)}"
BASE_URL="${BASE_URL:-}"
PHP_SERVER_PID=""
DOCKER_PHP_CID=""

cleanup() {
    if [[ -n "${PHP_SERVER_PID}" ]] && kill -0 "${PHP_SERVER_PID}" 2>/dev/null; then
        kill "${PHP_SERVER_PID}" 2>/dev/null || true
    fi
    if [[ -n "${DOCKER_PHP_CID}" ]]; then
        docker rm -f "${DOCKER_PHP_CID}" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

if [[ -z "${BASE_URL}" ]]; then
    CANDIDATE_PORTS=()
    if command -v docker >/dev/null 2>&1; then
        while IFS= read -r p; do
            [[ -n "${p}" ]] && CANDIDATE_PORTS+=("${p}")
        done < <(docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}' 2>/dev/null \
            | grep -viE 'phpmyadmin|adminer|mysql|mariadb|postgres|redis' \
            | grep -oE '0\.0\.0\.0:[0-9]+->80/tcp' \
            | sed -E 's/0\.0\.0\.0:([0-9]+)->80\/tcp/\1/' || true)
    fi

    for p in "${CANDIDATE_PORTS[@]}"; do
        PROBE_CODE=$(curl -sS --connect-timeout 2 -o /dev/null -w "%{http_code}" "http://127.0.0.1:${p}/api/whatsapp_checkout/init_order.php" || echo "000")
        if [[ "${PROBE_CODE}" == "405" || "${PROBE_CODE}" == "200" ]]; then
            BASE_URL="http://127.0.0.1:${p}"
            echo "[INFO] Verified smartTrade container endpoint on ${BASE_URL}"
            break
        fi
    done

    if [[ -z "${BASE_URL}" ]]; then
        if [[ ! -d "public" ]]; then
            echo "[ERROR] Must be run from the smartTrade-POS-Pro project root containing public/."
            exit 1
        fi

        MYSQL_CID=""
        MYSQL_IP="127.0.0.1"
        MYSQL_NET="bridge"
        DOCKER_MYSQL_ROOT_PASS=""
        DOCKER_MYSQL_DB=""
        DOCKER_MYSQL_USER=""
        DOCKER_MYSQL_PASS=""

        if command -v docker >/dev/null 2>&1; then
            MYSQL_CID=$(docker ps --format '{{.ID}}\t{{.Image}}\t{{.Names}}' 2>/dev/null | grep -iE 'mysql|mariadb' | head -n1 | awk '{print $1}' || true)
            if [[ -n "${MYSQL_CID}" ]]; then
                MYSQL_IP=$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}' "${MYSQL_CID}" 2>/dev/null | awk '{print $1}' || true)
                MYSQL_NET=$(docker inspect -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}} {{end}}' "${MYSQL_CID}" 2>/dev/null | awk '{print $1}' || echo "bridge")
                DOCKER_MYSQL_ROOT_PASS=$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "${MYSQL_CID}" 2>/dev/null | grep '^MYSQL_ROOT_PASSWORD=' | head -n1 | cut -d= -f2- || true)
                DOCKER_MYSQL_DB=$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "${MYSQL_CID}" 2>/dev/null | grep '^MYSQL_DATABASE=' | head -n1 | cut -d= -f2- || true)
                DOCKER_MYSQL_USER=$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "${MYSQL_CID}" 2>/dev/null | grep '^MYSQL_USER=' | head -n1 | cut -d= -f2- || true)
                DOCKER_MYSQL_PASS=$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "${MYSQL_CID}" 2>/dev/null | grep '^MYSQL_PASSWORD=' | head -n1 | cut -d= -f2- || true)
                echo "[INFO] Resolved running Docker MySQL container (${MYSQL_CID}) at DB_HOST=${MYSQL_IP} (network: ${MYSQL_NET})"
            fi
        fi

        FALLBACK_PORT="${FALLBACK_PORT:-8099}"

        if php -r 'exit((extension_loaded("pdo_mysql") || extension_loaded("mysqli")) ? 0 : 1);' 2>/dev/null; then
            export DOCKER_MYSQL_HOST="${MYSQL_IP}"
            export DOCKER_MYSQL_ROOT_PASS="${DOCKER_MYSQL_ROOT_PASS}"
            export DOCKER_MYSQL_DB="${DOCKER_MYSQL_DB}"
            export DOCKER_MYSQL_USER="${DOCKER_MYSQL_USER}"
            export DOCKER_MYSQL_PASS="${DOCKER_MYSQL_PASS}"
            export FLW_SECRET_HASH="${FLW_SECRET_HASH}"
            php -S "127.0.0.1:${FALLBACK_PORT}" -t public >/dev/null 2>&1 &
            PHP_SERVER_PID=$!
            sleep 1
            BASE_URL="http://127.0.0.1:${FALLBACK_PORT}"
            echo "[INFO] Started temporary host PHP server on ${BASE_URL} (PID ${PHP_SERVER_PID})"
        else
            echo "[INFO] Host PHP lacks pdo_mysql/mysqli; locating local Docker PHP image..."
            PHP_DOCKER_IMAGE=""

            for cid in $(docker ps -q 2>/dev/null || true); do
                if docker exec "${cid}" php -r 'exit((extension_loaded("pdo_mysql") || extension_loaded("mysqli")) ? 0 : 1);' >/dev/null 2>&1; then
                    PHP_DOCKER_IMAGE=$(docker inspect -f '{{.Config.Image}}' "${cid}" 2>/dev/null || true)
                    if [[ -n "${PHP_DOCKER_IMAGE}" ]]; then
                        break
                    fi
                fi
            done

            if [[ -z "${PHP_DOCKER_IMAGE}" ]]; then
                while IFS= read -r img; do
                    [[ -z "${img}" ]] && continue
                    if docker run --rm --entrypoint php "${img}" -r 'exit((extension_loaded("pdo_mysql") || extension_loaded("mysqli")) ? 0 : 1);' >/dev/null 2>&1; then
                        PHP_DOCKER_IMAGE="${img}"
                        break
                    fi
                done < <(docker images --format '{{.Repository}}:{{.Tag}}' 2>/dev/null | grep -vi '<none>' | grep -iE 'smarttrade|smartcivils|php|phpmyadmin|odelia|web|app' || true)
            fi

            if [[ -z "${PHP_DOCKER_IMAGE}" ]]; then
                echo "[ERROR] No local PHP runtime with pdo_mysql/mysqli found. Run: sudo apt-get install -y php-mysql"
                exit 1
            fi

            DOCKER_PHP_CID=$(docker run -d --rm \
                --entrypoint php \
                --network "${MYSQL_NET}" \
                -p "127.0.0.1:${FALLBACK_PORT}:8099" \
                -e DOCKER_MYSQL_HOST="${MYSQL_IP}" \
                -e DOCKER_MYSQL_ROOT_PASS="${DOCKER_MYSQL_ROOT_PASS}" \
                -e DOCKER_MYSQL_DB="${DOCKER_MYSQL_DB}" \
                -e DOCKER_MYSQL_USER="${DOCKER_MYSQL_USER}" \
                -e DOCKER_MYSQL_PASS="${DOCKER_MYSQL_PASS}" \
                -e FLW_SECRET_HASH="${FLW_SECRET_HASH}" \
                -v "$(pwd):/workspace" \
                -w /workspace \
                "${PHP_DOCKER_IMAGE}" \
                -S "0.0.0.0:8099" -t public)

            sleep 1
            BASE_URL="http://127.0.0.1:${FALLBACK_PORT}"
            echo "[INFO] Started temporary Docker PHP server (${PHP_DOCKER_IMAGE}) on ${BASE_URL}"
        fi
    fi
fi

INIT_URL="${BASE_URL%/}/api/whatsapp_checkout/init_order.php"
WEBHOOK_URL="${BASE_URL%/}/api/flutterwave_webhook.php"
QUEUE_URL="${BASE_URL%/}/api/whatsapp_checkout/pos_order_actions.php?branch_id=${BRANCH_ID}&payment_status=PAID"

echo "=================================================================="
echo " smartTrade -> Unified Flutterwave Webhook Test Suite"
echo " Target Webhook : ${WEBHOOK_URL}"
echo "=================================================================="

if [[ -z "${TX_REF}" || -z "${AMOUNT_ZMW}" ]]; then
    echo ""
    echo "[1/4] Seeding PENDING_PAYMENT order via ${INIT_URL} ..."

    INIT_PAYLOAD=$(cat <<JSON
{
  "branch_id": ${BRANCH_ID},
  "customer_name": "${CUSTOMER_NAME}",
  "customer_phone": "${CUSTOMER_PHONE}",
  "delivery_type": "DELIVERY",
  "delivery_address": "Plot 104, Great East Road, Lusaka",
  "items": [
    {
      "product_id": ${PRODUCT_ID},
      "qty": ${QTY}
    }
  ]
}
JSON
)

    INIT_RESPONSE=$(curl -sS --connect-timeout 10 -w "\n%{http_code}" -X POST "${INIT_URL}" \
        -H "Content-Type: application/json" \
        -d "${INIT_PAYLOAD}")

    INIT_HTTP_CODE=$(echo "${INIT_RESPONSE}" | tail -n1)
    INIT_BODY=$(echo "${INIT_RESPONSE}" | sed '$d')

    echo "      HTTP Status : ${INIT_HTTP_CODE}"
    echo "      Response    : ${INIT_BODY}"

    if [[ "${INIT_HTTP_CODE}" != "200" ]]; then
        echo "[ERROR] Failed to initialize test order."
        exit 1
    fi

    TX_REF=$(php -r '$d = json_decode($argv[1], true); echo $d["tx_ref"] ?? "";' "${INIT_BODY}")
    AMOUNT_ZMW=$(php -r '$d = json_decode($argv[1], true); echo $d["amount_zmw"] ?? "";' "${INIT_BODY}")

    if [[ -z "${TX_REF}" || -z "${AMOUNT_ZMW}" ]]; then
        echo "[ERROR] Could not extract tx_ref or amount_zmw from init_order.php response."
        exit 1
    fi
    echo "      Seeded Order: TX_REF=${TX_REF} | AMOUNT_ZMW=${AMOUNT_ZMW}"
else
    echo ""
    echo "[1/4] Using supplied TX_REF=${TX_REF} and AMOUNT_ZMW=${AMOUNT_ZMW}"
fi

WEBHOOK_PAYLOAD=$(cat <<JSON
{
  "event": "charge.completed",
  "event.type": "MOBILEMONEYZAMBIA_TRANSACTION",
  "data": {
    "id": ${FLW_TX_ID},
    "tx_ref": "${TX_REF}",
    "flw_ref": "FLW-ZM-MOCK-${FLW_TX_ID}",
    "device_fingerprint": "st-wa-webhook-sim",
    "amount": ${AMOUNT_ZMW},
    "currency": "ZMW",
    "charged_amount": ${AMOUNT_ZMW},
    "app_fee": 4.40,
    "merchant_fee": 0,
    "processor_response": "Approved or completed successfully",
    "auth_model": "MOBILEMONEY",
    "ip": "102.23.120.10",
    "narration": "smartTrade WhatsApp Order ${TX_REF}",
    "status": "successful",
    "payment_type": "mobilemoneyzambia",
    "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "account_id": 100200,
    "customer": {
      "id": 880011,
      "name": "${CUSTOMER_NAME}",
      "phone_number": "${CUSTOMER_PHONE}",
      "email": "${CUSTOMER_PHONE}@whatsapp.odeliazm.com",
      "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
    }
  }
}
JSON
)

FLW_V4_SIGNATURE=$(printf '%s' "${WEBHOOK_PAYLOAD}" | openssl dgst -sha256 -hmac "${FLW_SECRET_HASH}" -binary | base64 | tr -d '\n')

echo ""
echo "[2/4] Testing forged webhook signature rejection (Expects HTTP 401) ..."

FORGED_RESPONSE=$(curl -sS --connect-timeout 10 -w "\n%{http_code}" -X POST "${WEBHOOK_URL}" \
    -H "Content-Type: application/json" \
    -H "verif-hash: INVALID_FORGED_HASH_000" \
    -H "flutterwave-signature: INVALID_FORGED_HMAC_000" \
    -d "${WEBHOOK_PAYLOAD}")

FORGED_HTTP_CODE=$(echo "${FORGED_RESPONSE}" | tail -n1)
FORGED_BODY=$(echo "${FORGED_RESPONSE}" | sed '$d')

echo "      HTTP Status : ${FORGED_HTTP_CODE}"
echo "      Response    : ${FORGED_BODY}"

echo ""
echo "[3/4] Sending validly signed Flutterwave webhook for TX_REF=${TX_REF} ..."
echo "      Computed v4 HMAC-SHA256 : ${FLW_V4_SIGNATURE}"

VALID_RESPONSE=$(curl -sS --connect-timeout 10 -w "\n%{http_code}" -X POST "${WEBHOOK_URL}" \
    -H "Content-Type: application/json" \
    -H "verif-hash: ${FLW_SECRET_HASH}" \
    -H "flutterwave-signature: ${FLW_V4_SIGNATURE}" \
    -d "${WEBHOOK_PAYLOAD}")

VALID_HTTP_CODE=$(echo "${VALID_RESPONSE}" | tail -n1)
VALID_BODY=$(echo "${VALID_RESPONSE}" | sed '$d')

echo "      HTTP Status : ${VALID_HTTP_CODE}"
echo "      Response    : ${VALID_BODY}"

echo ""
echo "[4/4] Replaying identical webhook to verify idempotency lock (Expects 'already_processed') ..."

REPLAY_RESPONSE=$(curl -sS --connect-timeout 10 -w "\n%{http_code}" -X POST "${WEBHOOK_URL}" \
    -H "Content-Type: application/json" \
    -H "verif-hash: ${FLW_SECRET_HASH}" \
    -H "flutterwave-signature: ${FLW_V4_SIGNATURE}" \
    -d "${WEBHOOK_PAYLOAD}")

REPLAY_HTTP_CODE=$(echo "${REPLAY_RESPONSE}" | tail -n1)
REPLAY_BODY=$(echo "${REPLAY_RESPONSE}" | sed '$d')

echo "      HTTP Status : ${REPLAY_HTTP_CODE}"
echo "      Response    : ${REPLAY_BODY}"

echo ""
echo "=================================================================="
echo " Active Paid POS Queue Check (${QUEUE_URL}):"
curl -sS --connect-timeout 10 "${QUEUE_URL}" | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo " Total Active Paid Orders in Queue: " . ($d["count"] ?? 0) . PHP_EOL;'
echo "=================================================================="
