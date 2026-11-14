#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# smartTrade — Automated Kamatera WhatsApp Stack Deployment & Live Webhook Test
# Run from local WSL2:
#   bash scripts/deploy_whatsapp_kamatera.sh
# ==============================================================================

SSH_TARGET="${SSH_TARGET:-root@smartrade.odeliazm.com}"
REMOTE_DIR="${REMOTE_DIR:-~/smartTrade-POS-Pro}"
BRANCH="${BRANCH:-kamatera-production}"
LIVE_URL="${LIVE_URL:-https://smartrade.odeliazm.com}"
FLW_SECRET_HASH="${FLW_SECRET_HASH:-024006a8632f4c3b852f4a3ba1596b7d57a78d3298f31c3ef2a8c8734cdc6414}"

echo "=================================================================="
echo " Deploying smartTrade WhatsApp Stack to ${SSH_TARGET}"
echo "=================================================================="

echo ""
echo "[1/3] Staging, committing, and pushing existing local files to origin/${BRANCH} ..."

TARGET_FILES=(
    "app/Services/WhatsAppCloudService.php"
    "public/api/flutterwave_webhook.php"
    "public/api/flutterwave_webhook_native.php"
    "public/api/whatsapp_checkout/init_order.php"
    "public/api/whatsapp_checkout/pos_order_actions.php"
    "public/api/webhooks/flutterwave_webhook.php"
    "public/api/webhooks/whatsapp_webhook.php"
    "public/storefront/index.php"
    "public/storefront/kds.php"
    "public/verify_receipt.php"
    "scripts/tests/simulate_flw_webhook.sh"
    "scripts/deploy_whatsapp_kamatera.sh"
)

for f in "${TARGET_FILES[@]}"; do
    if [[ -f "${f}" ]]; then
        git add "${f}"
        echo "      + Staged ${f}"
    fi
done

if ! git diff --cached --quiet; then
    git commit -m "feat(whatsapp): add ST-FLW prefix router on /api/flutterwave_webhook.php and WhatsApp checkout stack"
fi
git push origin "${BRANCH}"

echo ""
echo "[2/3] Pulling on ${SSH_TARGET}:${REMOTE_DIR} and syncing container ..."
ssh "${SSH_TARGET}" "FLW_SECRET_HASH='${FLW_SECRET_HASH}' BRANCH='${BRANCH}' REMOTE_DIR='${REMOTE_DIR}' bash -se" << 'REMOTE_SCRIPT'
set -euo pipefail
eval cd "${REMOTE_DIR}"

if [ -f public/api/flutterwave_webhook.php ] && [ ! -f public/api/flutterwave_webhook_native.php ]; then
    if ! grep -q "ST-FLW-" public/api/flutterwave_webhook.php; then
        cp -p public/api/flutterwave_webhook.php public/api/flutterwave_webhook_native.php
    fi
fi

git stash push -m "auto-stash-before-wa-deploy-$(date +%s)" || true
git pull origin "${BRANCH}"
git stash pop || true

touch .env
if ! grep -q "^FLW_SECRET_HASH=" .env; then
    echo "FLW_SECRET_HASH=\"${FLW_SECRET_HASH}\"" >> .env
fi

WEB_CID=$(docker ps --format '{{.ID}}\t{{.Names}}\t{{.Image}}' \
    | grep -viE 'mysql|mariadb|vsdc|tomcat|phpmyadmin|nginx|redis' \
    | grep -iE 'smarttrade|app|web|php|pos' \
    | head -n1 | awk '{print $1}' || true)

if [[ -n "${WEB_CID}" ]]; then
    docker exec "${WEB_CID}" bash -c '
        if [ -f /var/www/html/public/api/flutterwave_webhook.php ] && [ ! -f /var/www/html/public/api/flutterwave_webhook_native.php ]; then
            if ! grep -q "ST-FLW-" /var/www/html/public/api/flutterwave_webhook.php; then
                cp -p /var/www/html/public/api/flutterwave_webhook.php /var/www/html/public/api/flutterwave_webhook_native.php
            fi
        fi
    ' || true

    docker exec "${WEB_CID}" mkdir -p /var/www/html/app/Services \
        /var/www/html/public/api/whatsapp_checkout \
        /var/www/html/public/api/webhooks \
        /var/www/html/public/storefront

    SYNC_FILES=(
        "app/Services/WhatsAppCloudService.php"
        "public/api/flutterwave_webhook_native.php"
        "public/api/flutterwave_webhook.php"
        "public/api/whatsapp_checkout/init_order.php"
        "public/api/whatsapp_checkout/pos_order_actions.php"
        "public/api/webhooks/flutterwave_webhook.php"
        "public/api/webhooks/whatsapp_webhook.php"
        "public/storefront/index.php"
        "public/storefront/kds.php"
        "public/verify_receipt.php"
    )

    for sf in "${SYNC_FILES[@]}"; do
        if [[ -f "${sf}" ]]; then
            docker cp "${sf}" "${WEB_CID}:/var/www/html/${sf}"
        fi
    done

    docker cp .env "${WEB_CID}:/var/www/html/.env" || true
    docker exec "${WEB_CID}" chown -R www-data:www-data /var/www/html/app/Services /var/www/html/public || true
    echo "      Synced router and WhatsApp stack into container ${WEB_CID}."
fi
REMOTE_SCRIPT

echo ""
echo "[3/3] Running live HTTPS webhook test suite against ${LIVE_URL}/api/flutterwave_webhook.php ..."
BASE_URL="${LIVE_URL}" FLW_SECRET_HASH="${FLW_SECRET_HASH}" bash scripts/tests/simulate_flw_webhook.sh
