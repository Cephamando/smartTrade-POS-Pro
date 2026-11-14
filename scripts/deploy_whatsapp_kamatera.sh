#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# smartTrade — Automated Kamatera WhatsApp Stack Deployment & Live Webhook Test
# Usage:
#   bash scripts/deploy_whatsapp_kamatera.sh
#   SSH_TARGET="root@odelia" bash scripts/deploy_whatsapp_kamatera.sh
# ==============================================================================

SSH_TARGET="${SSH_TARGET:-root@smarTrade.odeliazm.com}"
REMOTE_DIR="${REMOTE_DIR:-~/smartTrade-POS-Pro}"
BRANCH="${BRANCH:-kamatera-production}"
LIVE_URL="${LIVE_URL:-https://smarTrade.odeliazm.com}"
FLW_SECRET_HASH="${FLW_SECRET_HASH:-024006a8632f4c3b852f4a3ba1596b7d57a78d3298f31c3ef2a8c8734cdc6414}"

echo "=================================================================="
echo " Deploying smartTrade WhatsApp Stack to ${SSH_TARGET}"
echo "=================================================================="

echo ""
echo "[1/3] Staging, committing, and pushing local files to origin/${BRANCH} ..."
git add app/Services/WhatsAppCloudService.php \
        public/api/whatsapp_checkout/init_order.php \
        public/api/whatsapp_checkout/pos_order_actions.php \
        public/api/webhooks/flutterwave_webhook.php \
        public/api/webhooks/whatsapp_webhook.php \
        public/storefront/index.php \
        public/storefront/kds.php \
        public/verify_receipt.php \
        scripts/tests/simulate_flw_webhook.sh

if ! git diff --cached --quiet; then
    git commit -m "feat(whatsapp): deploy self-hosted Flutterwave WhatsApp storefront, KDS, and ZRA receipt"
fi
git push origin "${BRANCH}"

echo ""
echo "[2/3] Pulling on ${SSH_TARGET}:${REMOTE_DIR} and syncing container ..."
ssh "${SSH_TARGET}" "FLW_SECRET_HASH='${FLW_SECRET_HASH}' BRANCH='${BRANCH}' REMOTE_DIR='${REMOTE_DIR}' bash -se" << 'REMOTE_SCRIPT'
set -euo pipefail
eval cd "${REMOTE_DIR}"

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
    docker exec "${WEB_CID}" mkdir -p /var/www/html/app/Services \
        /var/www/html/public/api/whatsapp_checkout \
        /var/www/html/public/api/webhooks \
        /var/www/html/public/storefront
    docker cp app/Services/WhatsAppCloudService.php "${WEB_CID}:/var/www/html/app/Services/WhatsAppCloudService.php"
    docker cp public/api/whatsapp_checkout/init_order.php "${WEB_CID}:/var/www/html/public/api/whatsapp_checkout/init_order.php"
    docker cp public/api/whatsapp_checkout/pos_order_actions.php "${WEB_CID}:/var/www/html/public/api/whatsapp_checkout/pos_order_actions.php"
    docker cp public/api/webhooks/flutterwave_webhook.php "${WEB_CID}:/var/www/html/public/api/webhooks/flutterwave_webhook.php"
    docker cp public/api/webhooks/whatsapp_webhook.php "${WEB_CID}:/var/www/html/public/api/webhooks/whatsapp_webhook.php"
    docker cp public/storefront/index.php "${WEB_CID}:/var/www/html/public/storefront/index.php"
    docker cp public/storefront/kds.php "${WEB_CID}:/var/www/html/public/storefront/kds.php"
    docker cp public/verify_receipt.php "${WEB_CID}:/var/www/html/public/verify_receipt.php"
    docker cp .env "${WEB_CID}:/var/www/html/.env"
    docker exec "${WEB_CID}" chown -R www-data:www-data /var/www/html/app/Services /var/www/html/public || true
    echo "      Synced files into container ${WEB_CID}."
fi
REMOTE_SCRIPT

echo ""
echo "[3/3] Running live HTTPS webhook test suite against ${LIVE_URL} ..."
BASE_URL="${LIVE_URL}" FLW_SECRET_HASH="${FLW_SECRET_HASH}" bash scripts/tests/simulate_flw_webhook.sh
