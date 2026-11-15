#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# smartTrade — Automated Kamatera WhatsApp Stack Deployment & Live Webhook Test
# Run from local WSL2 project root:
#   bash scripts/deploy_whatsapp_kamatera.sh
# ==============================================================================

SSH_TARGET="${SSH_TARGET:-root@smartrade.odeliazm.com}"
REMOTE_DIR="${REMOTE_DIR:-~/smartTrade-POS-Pro}"
BRANCH="${BRANCH:-kamatera-production}"
LIVE_URL="${LIVE_URL:-https://smartrade.odeliazm.com}"
FLW_SECRET_HASH="${FLW_SECRET_HASH:-024006a8632f4c3b852f4a3ba1596b7d57a78d3298f31c3ef2a8c8734cdc6414}"
MERCHANT_WA="${MERCHANT_WA:-}"

echo "=================================================================="
echo " Deploying smartTrade WhatsApp Stack to ${SSH_TARGET}"
echo "=================================================================="

echo ""
echo "[1/3] Staging, committing, and pushing existing local files to origin/${BRANCH} ..."

TARGET_FILES=(
    "app/Services/WhatsAppCloudService.php"
    "public/.htaccess"
    "public/order/index.php"
    "public/r/index.php"
    "public/api/flutterwave_webhook.php"
    "public/api/flutterwave_webhook_native.php"
    "public/api/whatsapp_checkout/init_order.php"
    "public/api/whatsapp_checkout/complete_order.php"
    "public/api/whatsapp_checkout/pos_order_actions.php"
    "public/api/whatsapp_checkout/handover_hook.php"
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
    git commit -m "feat(receipt): add 128-bit tokenized /r/<token> receipt page with OG tags, PDF download, and PII masking"
fi
git push origin "${BRANCH}"

echo ""
echo "[2/3] Pulling on ${SSH_TARGET}:${REMOTE_DIR} and syncing container ..."
ssh "${SSH_TARGET}" "FLW_SECRET_HASH='${FLW_SECRET_HASH}' MERCHANT_WA='${MERCHANT_WA}' BRANCH='${BRANCH}' REMOTE_DIR='${REMOTE_DIR}' bash -se" << 'REMOTE_SCRIPT'
set -euo pipefail
eval cd "${REMOTE_DIR}"

if [ -f public/api/flutterwave_webhook_native.php ] && ! grep -q "ST-FLW-" public/api/flutterwave_webhook_native.php; then
    cp -p public/api/flutterwave_webhook_native.php /tmp/flutterwave_webhook_native.bak
elif [ -f public/api/flutterwave_webhook.php ] && ! grep -q "ST-FLW-" public/api/flutterwave_webhook.php; then
    cp -p public/api/flutterwave_webhook.php /tmp/flutterwave_webhook_native.bak
fi

CLEAN_UNTRACKED=(
    "app/Services/WhatsAppCloudService.php"
    "public/.htaccess"
    "public/order/index.php"
    "public/r/index.php"
    "public/api/flutterwave_webhook.php"
    "public/api/flutterwave_webhook_native.php"
    "public/api/whatsapp_checkout/init_order.php"
    "public/api/whatsapp_checkout/complete_order.php"
    "public/api/whatsapp_checkout/pos_order_actions.php"
    "public/api/whatsapp_checkout/handover_hook.php"
    "public/api/webhooks/flutterwave_webhook.php"
    "public/api/webhooks/whatsapp_webhook.php"
    "public/storefront/index.php"
    "public/storefront/kds.php"
    "public/verify_receipt.php"
    "scripts/tests/simulate_flw_webhook.sh"
    "scripts/deploy_whatsapp_kamatera.sh"
)

for uf in "${CLEAN_UNTRACKED[@]}"; do
    if [ -f "${uf}" ] && ! git ls-files --error-unmatch "${uf}" >/dev/null 2>&1; then
        rm -f "${uf}"
        echo "      Removed untracked conflict file: ${uf}"
    fi
done

if ! git diff --quiet || ! git diff --cached --quiet; then
    git stash push -m "auto-stash-before-wa-deploy-$(date +%s)" || true
fi

git pull origin "${BRANCH}"

if [ -f /tmp/flutterwave_webhook_native.bak ]; then
    cp -p /tmp/flutterwave_webhook_native.bak public/api/flutterwave_webhook_native.php
fi

if git stash list | head -n1 | grep -q "auto-stash-before-wa-deploy"; then
    git stash pop || true
fi

touch .env
if ! grep -q "^FLW_SECRET_HASH=" .env; then
    echo "FLW_SECRET_HASH=\"${FLW_SECRET_HASH}\"" >> .env
fi
if ! grep -q "^RECEIPT_TOKEN_TTL_DAYS=" .env; then
    echo "RECEIPT_TOKEN_TTL_DAYS=\"0\"" >> .env
fi
if [[ -n "${MERCHANT_WA}" ]]; then
    sed -i '/^MERCHANT_WHATSAPP_NUMBER=/d' .env || true
    echo "MERCHANT_WHATSAPP_NUMBER=\"${MERCHANT_WA}\"" >> .env
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
        /var/www/html/public/order \
        /var/www/html/public/r \
        /var/www/html/public/assets \
        /var/www/html/public/api/whatsapp_checkout \
        /var/www/html/public/api/webhooks \
        /var/www/html/public/storefront

    SYNC_FILES=(
        "app/Services/WhatsAppCloudService.php"
        "public/.htaccess"
        "public/order/index.php"
        "public/r/index.php"
        "public/api/flutterwave_webhook_native.php"
        "public/api/flutterwave_webhook.php"
        "public/api/whatsapp_checkout/init_order.php"
        "public/api/whatsapp_checkout/complete_order.php"
        "public/api/whatsapp_checkout/pos_order_actions.php"
        "public/api/whatsapp_checkout/handover_hook.php"
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

    # Generate 1200x630 OpenGraph Logo (< 30 KB) and backfill 128-bit receipt tokens for existing orders
    docker exec "${WEB_CID}" php -r '
        require "/var/www/html/app/Services/WhatsAppCloudService.php";
        $db = \SmartTrade\Services\WhatsAppCloudService::getDb();

        $logoPath = "/var/www/html/public/assets/receipt-og-logo.png";
        if (!is_file($logoPath) && function_exists("imagecreatetruecolor")) {
            $im = imagecreatetruecolor(1200, 630);
            $bg = imagecolorallocate($im, 7, 94, 84);
            $fg = imagecolorallocate($im, 255, 255, 255);
            imagefilledrectangle($im, 0, 0, 1200, 630, $bg);
            imagestring($im, 5, 460, 295, "OFFICIAL PAYMENT RECEIPT", $fg);
            imagepng($im, $logoPath, 9);
            imagedestroy($im);
        }

        $rows = $db->query("SELECT id, tx_ref, receipt_token FROM st_direct_wa_orders WHERE payment_status = \"PAID\" ORDER BY id DESC LIMIT 20")->fetchAll();
        foreach ($rows as $r) {
            $tok = \SmartTrade\Services\WhatsAppCloudService::ensureOrderReceiptToken($db, (int)$r["id"], $r["receipt_token"] ?? null);
        }
    ' || true

    docker exec "${WEB_CID}" chown -R www-data:www-data /var/www/html/app/Services /var/www/html/public || true
fi
REMOTE_SCRIPT

echo ""
echo "[3/3] Running live webhook test & verifying /r/<token> OpenGraph receipt route ..."
BASE_URL="${LIVE_URL}" FLW_SECRET_HASH="${FLW_SECRET_HASH}" bash scripts/tests/simulate_flw_webhook.sh
