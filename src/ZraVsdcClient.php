<?php

/**
 * ZRA Smart Invoice VSDC client for smartTradePOS-Pro.
 * Talks to the local Tomcat sandbox WAR, then persists fiscal results.
 */
class ZraVsdcClient
{
    private PDO $pdo;
    private string $baseUrl;
    private string $tpin;
    private string $bhfId;
    private string $dvcSrlNo;
    private static bool $schemaReady = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tpin = getenv('ZRA_TPIN') ?: '1004784338';
        $this->bhfId = getenv('ZRA_BHF_ID') ?: '000';
        $this->dvcSrlNo = getenv('ZRA_DVC_SRL_NO') ?: 'STPOS001V2';
        $this->baseUrl = rtrim(getenv('ZRA_VSDC_URL') ?: 'http://vsdc:8080/sandboxvsdc', '/');
        $this->ensureSchema();
    }

    public function getConfig(): array
    {
        return [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'dvcSrlNo' => $this->dvcSrlNo,
            'baseUrl' => $this->baseUrl,
        ];
    }

    /**
     * VSDC often returns an empty qrCodeUrl or the placeholder "[Link]".
     * Build the Digitax/ZRA receipt URL: TPIN (10) + branch (3) + rcptSign.
     */
    public function receiptQrUrl(?string $rcptSign, ?string $vsdcUrl = null): ?string
    {
        $vsdcUrl = trim((string)$vsdcUrl);
        if ($this->isUsableHttpUrl($vsdcUrl)) {
            return $vsdcUrl;
        }
        $sign = preg_replace('/\s+/', '', (string)$rcptSign);
        if ($sign === '') {
            return null;
        }
        $tpin = str_pad(preg_replace('/\D+/', '', $this->tpin) ?: $this->tpin, 10, '0', STR_PAD_LEFT);
        $bhf = str_pad(preg_replace('/\D+/', '', $this->bhfId) ?: $this->bhfId, 3, '0', STR_PAD_LEFT);
        $portal = rtrim((string)(getenv('ZRA_PORTAL_URL') ?: ''), '/');
        if ($portal === '') {
            $haystack = $this->baseUrl . ' ' . (string)getenv('ZRA_VSDC_URL');
            $portal = (stripos($haystack, 'sandbox') !== false)
                ? 'https://sandboxportal.zra.org.zm'
                : 'https://www.zra.org.zm';
        }
        return $portal . '/common/link/ebm/receipt/indexEbmReceiptData?Data=' . $tpin . $bhf . $sign;
    }

    private function isUsableHttpUrl(string $url): bool
    {
        if ($url === '' || strlen($url) < 12) {
            return false;
        }
        if (preg_match('/\[Link\]|placeholder|^n\/?a$/i', $url)) {
            return false;
        }
        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }

    public function getSettings(): array
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vsdc_%'");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
    }

    public function isInitialized(): bool
    {
        $settings = $this->getSettings();
        return !empty($settings['vsdc_sdcId']);
    }

    public function ping(): array
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_exec($ch);
        $error = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'http' => $http, 'message' => $error, 'layer' => 'local'];
        }
        return [
            'ok' => $http > 0 && $http < 500,
            'http' => $http,
            'message' => ($http > 0 && $http < 500) ? 'Local VSDC is reachable' : 'Local VSDC returned HTTP ' . $http,
            'layer' => 'local',
        ];
    }

    /**
     * Always hits the live VSDC (does not use the local "already initialized" short-circuit).
     * Use this to tell local Tomcat failure from ZRA sandbox gateway failure.
     */
    public function testConnection(): array
    {
        $ping = $this->ping();
        if (empty($ping['ok'])) {
            return [
                'resultCd' => '999',
                'resultMsg' => 'Local VSDC unreachable: ' . ($ping['message'] ?? 'connection refused'),
                'layer' => 'local',
                'hint' => 'The Tomcat container is not accepting HTTP. Place the sandbox WAR in api/ (or api/sandboxvsdc/) and run: docker compose up -d --force-recreate vsdc',
            ];
        }

        $res = $this->post('initializer/selectInitInfo', [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'dvcSrlNo' => $this->dvcSrlNo,
        ]);
        $this->captureInitInfo($res);
        $explained = $this->explainResult($res);
        return $res + $explained;
    }

    public function explainResult(array $res): array
    {
        $cd = (string)($res['resultCd'] ?? '');
        $msg = strtolower((string)($res['resultMsg'] ?? ''));

        if (str_contains($msg, 'curl error') || $cd === '999') {
            return [
                'layer' => 'local',
                'hint' => 'smartTradePOS cannot reach the vsdc container at ' . $this->baseUrl . '. Confirm `docker ps` shows vsdc as Up, then retry.',
            ];
        }

        if ($cd === '838' || $cd === '894' || str_contains($msg, 'zra api refused') || str_contains($msg, 'check connection')) {
            return [
                'layer' => 'zra',
                'hint' => 'Local VSDC answered, but it could not talk to ZRA’s sandbox gateway. Confirm internet from the vsdc container and retry. Do not register a new device yet.',
            ];
        }

        if ($cd === '899' || str_contains($msg, 'regarding client')) {
            return [
                'layer' => 'client',
                'hint' => 'VSDC has no usable API keys for this serial. Code 902 (“This device is installed”) means ZRA already initialized STPOS001V2, but this Tomcat copy never stored sdcId/mrcNo. Recreating the container wiped the local SQLite keystore. Register a new device serial on the portal (e.g. STPOS001V3), put that value in .env as ZRA_DVC_SRL_NO, wipe the vsdc_webapps volume, start VSDC, and Initialize once immediately.',
            ];
        }

        if ($cd === '902' || str_contains($msg, 'device is installed') || str_contains($msg, 'already installed')) {
            $hasKeys = !empty($this->getSettings()['vsdc_sdcId']);
            return [
                'layer' => $hasKeys ? 'ok' : 'client',
                'hint' => $hasKeys
                    ? 'Device is already initialized. Next: Sync standard codes.'
                    : 'ZRA says this serial is already installed, but smartTradePOS has no SDC ID. The local keystore is empty. Sync codes to confirm; if you get 899, register a new device serial on the portal and initialize that one once.',
            ];
        }

        if ($cd === '000' || str_contains($msg, 'succeeded') || str_contains($msg, 'already initialized')) {
            $hint = 'VSDC accepted the request.';
            if (isset($res['synced'])) {
                $ok = (int)($res['synced'] ?? 0);
                $fail = (int)($res['failed'] ?? 0);
                if ($ok === 0 && $fail === 0) {
                    $hint = 'All active products are already registered. Ring a test cash sale and check the receipt for SDC ID, signature, and QR.';
                } elseif ($fail === 0) {
                    $hint = 'Those products are on ZRA. Click Register products again until Unsynced products is 0, then ring a test cash sale.';
                } else {
                    $hint = 'Some products registered. Fix the listed class codes, then click Register products again.';
                }
            } elseif (isset($res['stored'])) {
                $hint = 'Classification dictionary is stored. Next: Register products.';
            } elseif (str_contains($msg, 'synced') && str_contains($msg, 'item')) {
                $hint = 'Products are on ZRA. Ring a test cash sale and check the receipt for SDC ID, signature, and QR.';
            }
            return [
                'layer' => 'ok',
                'hint' => $hint,
            ];
        }

        if (!empty($res['errors']) && is_array($res['errors'])) {
            return [
                'layer' => 'vsdc',
                'hint' => 'Item registration uses level-4 UNSPSC codes from Sync UNSPSC classes. Empty or class-level codes (for example 50101500) are rejected. Assign a commodity-level code on the product, or let the POS pick the closest synced class.',
            ];
        }

        if ($cd === '910' || str_contains($msg, 'parameter')) {
            $hint = (string)($res['resultMsg'] ?? 'Request parameter error');
            if (str_contains($msg, 'custtpin')) {
                $hint = 'Walk-in sales must omit customer TPIN. Retry the sale; empty custTpin is no longer sent.';
            }
            return [
                'layer' => 'vsdc',
                'hint' => $hint,
            ];
        }

        return [
            'layer' => 'vsdc',
            'hint' => 'VSDC responded with code ' . ($cd !== '' ? $cd : 'unknown') . ': ' . (string)($res['resultMsg'] ?? ''),
        ];
    }

    public function friendlyMessage(array $res): string
    {
        $msg = trim((string)($res['resultMsg'] ?? ''));
        if ($msg === '') {
            return 'Request completed.';
        }
        if (preg_match('/it\s*is\s*succeeded/i', $msg)) {
            return trim(preg_replace('/it\s*is\s*succeeded/i', 'Succeeded', $msg)) ?: 'Succeeded.';
        }
        return $msg;
    }

    public function initialize(bool $force = false): array
    {
        if (!$force && $this->isInitialized()) {
            return ['resultCd' => '000', 'resultMsg' => 'Device already initialized', 'data' => ['info' => $this->getSettings()]];
        }

        $res = $this->post('initializer/selectInitInfo', [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'dvcSrlNo' => $this->dvcSrlNo,
        ]);

        $this->captureInitInfo($res);
        return $res;
    }

    /**
     * Persist SDC identifiers from any init-shaped payload.
     * "This device is installed" is success: VSDC already has the keys and will not re-init.
     */
    private function captureInitInfo(array $res): void
    {
        $info = [];
        if (!empty($res['data']['info']) && is_array($res['data']['info'])) {
            $info = $res['data']['info'];
        } elseif (!empty($res['data']) && is_array($res['data'])) {
            $info = $res['data'];
        }

        $sdcId = $info['sdcId'] ?? $res['sdcId'] ?? '';
        $mrcNo = $info['mrcNo'] ?? $res['mrcNo'] ?? '';
        $taxprNm = $info['taxprNm'] ?? $res['taxprNm'] ?? '';
        $bhfNm = $info['bhfNm'] ?? $res['bhfNm'] ?? '';

        if ($sdcId !== '' || $mrcNo !== '' || $taxprNm !== '') {
            $existing = $this->getSettings();
            $this->putSettings([
                'vsdc_device_installed' => '1',
                'vsdc_sdcId' => $sdcId !== '' ? $sdcId : ($existing['vsdc_sdcId'] ?? ''),
                'vsdc_mrcNo' => $mrcNo !== '' ? $mrcNo : ($existing['vsdc_mrcNo'] ?? ''),
                'vsdc_taxprNm' => $taxprNm !== '' ? $taxprNm : ($existing['vsdc_taxprNm'] ?? ''),
                'vsdc_bhfNm' => $bhfNm !== '' ? $bhfNm : ($existing['vsdc_bhfNm'] ?? ''),
                'vsdc_last_sale_no' => (string)($info['lastSaleInvcNo'] ?? $existing['vsdc_last_sale_no'] ?? 0),
                'vsdc_last_invc_no' => (string)($info['lastInvcNo'] ?? $existing['vsdc_last_invc_no'] ?? 0),
                'vsdc_last_sar_no' => (string)($info['lastPchsInvcNo'] ?? $existing['vsdc_last_sar_no'] ?? 0),
            ]);
        }
    }

    private function isInstalledMessage(array $res): bool
    {
        $msg = strtolower((string)($res['resultMsg'] ?? ''));
        return ($res['resultCd'] ?? '') === '000'
            || str_contains($msg, 'device is installed')
            || str_contains($msg, 'already installed')
            || str_contains($msg, 'already initialized');
    }

    public function syncStandardCodes(string $lastReqDt = '20231215000000'): array
    {
        $res = $this->post('code/selectCodes', [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'lastReqDt' => $lastReqDt,
        ]);

        if (($res['resultCd'] ?? '') === '000' && !empty($res['data']['clsList'])) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO zra_standard_codes (code_class, code_class_name, code, code_name, user_defined_name)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE code_name = VALUES(code_name), user_defined_name = VALUES(user_defined_name)"
            );
            foreach ($res['data']['clsList'] as $cls) {
                $cdCls = $cls['cdCls'] ?? '';
                $cdClsNm = $cls['cdClsNm'] ?? '';
                foreach ($cls['dtlList'] ?? [] as $dtl) {
                    $stmt->execute([
                        $cdCls,
                        $cdClsNm,
                        $dtl['cd'] ?? '',
                        $dtl['cdNm'] ?? '',
                        $dtl['userDfnNm1'] ?? null,
                    ]);
                }
            }
            $this->putSettings(['vsdc_last_code_dt' => date('YmdHis')]);
        }

        return $res;
    }

    public function syncItemClasses(string $lastReqDt = '20231215000000'): array
    {
        $stored = 0;
        $pages = 0;
        $cursor = $lastReqDt;
        $seen = [];
        $final = ['resultCd' => '001', 'resultMsg' => 'There is no search result', 'data' => null];

        while ($pages < 20 && !isset($seen[$cursor])) {
            $seen[$cursor] = true;
            $res = $this->post('itemClass/selectItemsClass', [
                'tpin' => $this->tpin,
                'bhfId' => $this->bhfId,
                'lastReqDt' => $cursor,
            ]);
            $pages++;
            $list = $res['data']['itemClsList'] ?? [];
            if (($res['resultCd'] ?? '') === '000' && $list) {
                $final = $res;
                $stored += $this->storeItemClasses($list);
                $next = (string)($res['resultDt'] ?? '');
                if ($next === '' || $next === $cursor) {
                    break;
                }
                $cursor = $next;
                continue;
            }
            if ($stored > 0) {
                break;
            }
            $final = $res;
            break;
        }

        $defaultCls = $this->firstValidItemClass() ?: '50202201';
        $this->putSettings([
            'vsdc_last_class_dt' => $cursor,
            'vsdc_default_unspsc' => $defaultCls,
        ]);
        $final['resultCd'] = $stored > 0 ? '000' : (string)($final['resultCd'] ?? '001');
        $final['resultMsg'] = 'Stored ' . $stored . ' classification(s) across ' . $pages . ' page(s). Default item class is ' . $defaultCls . '.';
        $final['stored'] = $stored;
        $final['pages'] = $pages;
        return $final;
    }

    public function saveItem(array $product, string $userId = 'ADMIN', string $userNm = 'ADMIN'): array
    {
        $itemCd = $this->ensureItemCode((int)$product['id'], $product['sku'] ?? null, $product['zra_item_cd'] ?? null);
        $unspsc = $this->resolveUnspsc($product['unspsc_code'] ?? null);
        $vatCat = $this->mapVatCategory($product['tax_class'] ?? 'A');
        $itemTy = (($product['type'] ?? 'item') === 'service') ? '3' : '2';
        $name = mb_substr((string)($product['name'] ?? 'Item'), 0, 200);
        $userId = mb_substr((string)$userId, 0, 20) ?: 'ADMIN';
        $userNm = mb_substr((string)$userNm, 0, 60) ?: 'ADMIN';
        $sku = trim((string)($product['sku'] ?? ''));
        $bcd = $sku !== '' ? mb_substr($sku, 0, 20) : null;

        $payload = array_filter([
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'itemCd' => $itemCd,
            'itemClsCd' => $unspsc,
            'itemTyCd' => $itemTy,
            'itemNm' => $name,
            'itemStdNm' => $name,
            'orgnNatCd' => 'ZM',
            'pkgUnitCd' => 'NT',
            'qtyUnitCd' => 'U',
            'vatCatCd' => $vatCat,
            'bcd' => $bcd,
            'dftPrc' => (float)($product['price'] ?? 0),
            'sftyQty' => 0,
            'isrcAplcbYn' => 'N',
            'useYn' => ((int)($product['is_active'] ?? 1) === 1) ? 'Y' : 'N',
            'regrNm' => $userNm,
            'regrId' => $userId,
            'modrNm' => $userNm,
            'modrId' => $userId,
        ], static fn($v) => $v !== null && $v !== '');

        $res = $this->post('items/saveItem', $payload);
        $this->logSync('item', (int)$product['id'], $res);
        if (($res['resultCd'] ?? '') === '000') {
            $this->pdo->prepare("UPDATE products SET zra_item_cd = ?, zra_synced = 1, unspsc_code = ? WHERE id = ?")
                ->execute([$itemCd, $unspsc, (int)$product['id']]);
        }
        return $res + ['itemCd' => $itemCd, 'itemClsCd' => $unspsc];
    }

    public function syncUnsyncedProducts(int $limit = 50): array
    {
        $this->ensureProductColumns();
        $stmt = $this->pdo->query(
            "SELECT id, name, sku, price, type, is_active, tax_class, unspsc_code, zra_item_cd, zra_synced
             FROM products
             WHERE is_active = 1 AND (zra_synced = 0 OR zra_item_cd IS NULL OR zra_item_cd = '')
             ORDER BY id ASC
             LIMIT " . (int)$limit
        );
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ok = 0;
        $fail = 0;
        $errors = [];
        $firstFailCd = '910';

        foreach ($products as $product) {
            $res = $this->saveItem($product);
            if (($res['resultCd'] ?? '') === '000') {
                $ok++;
            } else {
                $fail++;
                if ($fail === 1 && !empty($res['resultCd'])) {
                    $firstFailCd = (string)$res['resultCd'];
                }
                $errors[] = ($product['name'] ?? $product['id'])
                    . ' [class ' . ($res['itemClsCd'] ?? '?') . ']: '
                    . ($res['resultMsg'] ?? 'Unknown error')
                    . ' (' . ($res['resultCd'] ?? '?') . ')';
            }
        }

        return [
            'resultCd' => $fail === 0 ? '000' : $firstFailCd,
            'resultMsg' => "Synced $ok item(s)" . ($fail ? ", $fail failed" : ''),
            'synced' => $ok,
            'failed' => $fail,
            'errors' => $errors,
        ];
    }

    public function fiscalizeSale(int $saleId, array $opts = []): array
    {
        if (!$this->isInitialized()) {
            return ['resultCd' => 'skipped', 'resultMsg' => 'VSDC device is not initialized'];
        }

        $sale = $this->pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $sale->execute([$saleId]);
        $saleData = $sale->fetch(PDO::FETCH_ASSOC);
        if (!$saleData) {
            return ['resultCd' => '904', 'resultMsg' => "Sale #$saleId not found"];
        }

        if (!empty($saleData['zra_rcpt_sign'])) {
            return ['resultCd' => '000', 'resultMsg' => 'Sale already fiscalized', 'data' => $saleData];
        }

        $paymentStatus = strtolower((string)($saleData['payment_status'] ?? ''));
        if (!in_array($paymentStatus, ['paid', 'refunded', 'partially_refunded'], true)) {
            return ['resultCd' => 'skipped', 'resultMsg' => 'Sale is not fully paid'];
        }

        $itemsStmt = $this->pdo->prepare(
            "SELECT si.*, p.name, p.sku, p.tax_class, p.unspsc_code, p.unit, p.zra_item_cd, p.type,
                    p.price AS catalog_price, p.is_active, p.zra_synced
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             WHERE si.sale_id = ? AND si.status NOT IN ('voided')"
        );
        $itemsStmt->execute([$saleId]);
        $saleItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$saleItems) {
            $this->markSaleSkipped($saleId, 'No line items to send to ZRA');
            return ['resultCd' => 'skipped', 'resultMsg' => 'No fiscalizable line items'];
        }
        if (abs((float)$saleData['final_total']) < 0.005 && !$this->linesAreRefund($saleItems)) {
            $this->markSaleSkipped($saleId, 'Zero-value sale is not sent to ZRA');
            return ['resultCd' => 'skipped', 'resultMsg' => 'Zero-value sale is not sent to ZRA'];
        }

        $isRefund = ((float)$saleData['final_total'] < 0) || $this->linesAreRefund($saleItems);
        $user = $this->lookupUser((int)$saleData['user_id']);
        if ($isRefund) {
            $orig = $this->resolveOriginalInvoice($saleData, $saleItems, $opts);
            if ($orig['orgInvcNo'] <= 0 || $orig['orgSdcId'] === '') {
                $msg = 'Refund needs the original ZRA invoice (SDC ID and receipt number). Refund from the fiscalized receipt, not a blank walk-in return.';
                $this->markSaleFailed($saleId, $msg);
                return ['resultCd' => '910', 'resultMsg' => $msg];
            }
            $opts['orgInvcNo'] = $orig['orgInvcNo'];
            $opts['orgSdcId'] = $orig['orgSdcId'];
        }
        foreach ($saleItems as $i => $line) {
            if (empty($line['zra_synced']) || empty($line['zra_item_cd'])) {
                $itemRes = $this->saveItem([
                    'id' => (int)$line['product_id'],
                    'name' => $line['name'],
                    'sku' => $line['sku'] ?? null,
                    'price' => $line['catalog_price'] ?? $line['price'],
                    'type' => $line['type'] ?? 'item',
                    'is_active' => $line['is_active'] ?? 1,
                    'tax_class' => $line['tax_class'] ?? 'A',
                    'unspsc_code' => $line['unspsc_code'] ?? null,
                    'zra_item_cd' => $line['zra_item_cd'] ?? null,
                ], $user['id'], $user['name']);
                if (($itemRes['resultCd'] ?? '') !== '000') {
                    $msg = 'Could not register ' . ($line['name'] ?? 'item') . ': ' . ($itemRes['resultMsg'] ?? 'VSDC rejected the item');
                    $this->markSaleFailed($saleId, $msg);
                    return $itemRes + ['resultMsg' => $msg];
                }
                $saleItems[$i]['zra_item_cd'] = $itemRes['itemCd'] ?? $line['zra_item_cd'];
                $saleItems[$i]['unspsc_code'] = $itemRes['itemClsCd'] ?? $line['unspsc_code'];
                $saleItems[$i]['zra_synced'] = 1;
            }
        }
        $payload = $this->buildSalesPayload($saleData, $saleItems, $isRefund, $user, $opts);

        $res = $this->post('trnsSales/saveSales', $payload);
        $this->logSync('sale', $saleId, $res);

        if (($res['resultCd'] ?? '') === '000') {
            $data = $res['data'] ?? [];
            $this->pdo->prepare(
                "UPDATE sales SET
                    zra_rcpt_no = ?, zra_intrl_data = ?, zra_rcpt_sign = ?,
                    zra_sdc_id = ?, zra_mrc_no = ?, zra_vsdc_rcpt_pbct_date = ?,
                    zra_qr_code_url = ?, zra_status = 'synced', zra_cis_invc_no = ?,
                    zra_org_invc_no = ?, zra_last_error = NULL
                 WHERE id = ?"
            )->execute([
                $data['rcptNo'] ?? null,
                $data['intrlData'] ?? null,
                $data['rcptSign'] ?? null,
                $data['sdcId'] ?? ($this->getSettings()['vsdc_sdcId'] ?? null),
                $data['mrcNo'] ?? ($this->getSettings()['vsdc_mrcNo'] ?? null),
                $data['vsdcRcptPbctDate'] ?? null,
                $this->receiptQrUrl($data['rcptSign'] ?? null, $data['qrCodeUrl'] ?? null),
                $payload['cisInvcNo'],
                $payload['orgInvcNo'] ?: null,
                $saleId,
            ]);

            $this->putSettings(['vsdc_last_sale_no' => (string)((int)($this->getSettings()['vsdc_last_sale_no'] ?? 0) + 1)]);
        } else {
            $this->markSaleFailed($saleId, $res['resultMsg'] ?? 'VSDC rejected the sale');
        }

        return $res;
    }

    public function retryFailedSales(int $limit = 20): array
    {
        $this->ensureSaleColumns();
        $stmt = $this->pdo->query(
            "SELECT id FROM sales
             WHERE " . $this->fiscalizablePendingWhere() . "
             ORDER BY id ASC
             LIMIT " . (int)$limit
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ok = 0;
        $fail = 0;
        foreach ($ids as $id) {
            $res = $this->fiscalizeSale((int)$id);
            if (($res['resultCd'] ?? '') === '000') {
                $ok++;
            } elseif (($res['resultCd'] ?? '') !== 'skipped') {
                $fail++;
            }
        }
        return [
            'resultCd' => $fail === 0 ? '000' : '901',
            'resultMsg' => "Retried " . count($ids) . " sale(s): $ok synced, $fail failed",
            'synced' => $ok,
            'failed' => $fail,
        ];
    }

    public function syncGrv(int $grvId): array
    {
        if (!$this->isInitialized()) {
            return ['resultCd' => 'skipped', 'resultMsg' => 'VSDC device is not initialized'];
        }

        $grv = $this->pdo->prepare("SELECT * FROM grvs WHERE id = ?");
        $grv->execute([$grvId]);
        $header = $grv->fetch(PDO::FETCH_ASSOC);
        if (!$header) {
            return ['resultCd' => '904', 'resultMsg' => "GRV #$grvId not found"];
        }
        if (!empty($header['zra_synced'])) {
            return ['resultCd' => '000', 'resultMsg' => 'GRV already synced'];
        }

        $itemsStmt = $this->pdo->prepare(
            "SELECT gi.*, p.name, p.sku, p.tax_class, p.unspsc_code, p.zra_item_cd
             FROM grv_items gi
             JOIN products p ON gi.product_id = p.id
             WHERE gi.grv_id = ?"
        );
        $itemsStmt->execute([$grvId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            return ['resultCd' => 'skipped', 'resultMsg' => 'GRV has no items'];
        }

        $sarNo = (int)($this->getSettings()['vsdc_last_sar_no'] ?? 0) + 1;
        $itemList = [];
        $totTaxbl = 0.0;
        $totTax = 0.0;
        $totAmt = 0.0;
        $seq = 1;

        foreach ($items as $item) {
            $qty = (float)$item['quantity'];
            $prc = (float)$item['unit_cost'];
            $sply = round($qty * $prc, 4);
            $vat = $this->splitVat($sply, $item['tax_class'] ?? 'A');
            $totTaxbl += $vat['taxbl'];
            $totTax += $vat['tax'];
            $totAmt += $sply;
            $itemList[] = [
                'itemSeq' => $seq++,
                'itemCd' => $this->ensureItemCode((int)$item['product_id'], $item['sku'] ?? null, $item['zra_item_cd'] ?? null),
                'itemClsCd' => $this->resolveUnspsc($item['unspsc_code'] ?? null),
                'itemNm' => $item['name'],
                'pkgUnitCd' => 'NT',
                'qtyUnitCd' => 'U',
                'qty' => $qty,
                'prc' => $prc,
                'splyAmt' => $sply,
                'taxblAmt' => $vat['taxbl'],
                'vatCatCd' => $this->mapVatCategory($item['tax_class'] ?? 'A'),
                'taxAmt' => $vat['tax'],
                'totAmt' => $sply,
            ];
        }

        $res = $this->post('stock/saveStockItems', [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'sarNo' => $sarNo,
            'orgSarNo' => 0,
            'regTyCd' => 'M',
            'custTpin' => null,
            'custNm' => null,
            'custBhfId' => null,
            'sarTyCd' => '02',
            'ocrnDt' => date('Ymd'),
            'totItemCnt' => count($itemList),
            'totTaxblAmt' => round($totTaxbl, 4),
            'totTaxAmt' => round($totTax, 4),
            'totAmt' => round($totAmt, 4),
            'remark' => $header['reference_no'] ?: ('GRV-' . $grvId),
            'regrId' => 'ADMIN',
            'regrNm' => 'ADMIN',
            'modrNm' => 'ADMIN',
            'modrId' => 'ADMIN',
            'itemList' => $itemList,
        ]);

        $this->logSync('grv', $grvId, $res);

        if (($res['resultCd'] ?? '') === '000') {
            $this->pdo->prepare("UPDATE grvs SET zra_synced = 1 WHERE id = ?")->execute([$grvId]);
            $this->putSettings(['vsdc_last_sar_no' => (string)$sarNo]);
            $this->updateStockMaster($items, (int)$header['location_id']);
        }

        return $res;
    }

    public function statusSummary(): array
    {
        $this->ensureSaleColumns();
        $this->ensureProductColumns();
        $pendingSales = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM sales WHERE " . $this->fiscalizablePendingWhere()
        )->fetchColumn();
        $syncedSales = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM sales WHERE zra_rcpt_sign IS NOT NULL AND zra_rcpt_sign <> ''"
        )->fetchColumn();
        $unsyncedProducts = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM products WHERE is_active = 1 AND COALESCE(zra_synced, 0) = 0"
        )->fetchColumn();
        $codeCount = (int)$this->pdo->query("SELECT COUNT(*) FROM zra_standard_codes")->fetchColumn();
        $classCount = 0;
        try {
            $classCount = (int)$this->pdo->query("SELECT COUNT(*) FROM zra_item_classes")->fetchColumn();
        } catch (Throwable $e) {
            $classCount = 0;
        }

        return [
            'pending_sales' => $pendingSales,
            'synced_sales' => $syncedSales,
            'unsynced_products' => $unsyncedProducts,
            'standard_codes' => $codeCount,
            'item_classes' => $classCount,
        ];
    }

    private function buildSalesPayload(array $sale, array $items, bool $isRefund, array $user, array $opts): array
    {
        $settings = $this->getSettings();
        $nextInvcNo = (int)($settings['vsdc_last_sale_no'] ?? 0) + 1;
        $now = new DateTime('now', new DateTimeZone('Africa/Lusaka'));
        $cfmDt = $now->format('YmdHis');
        $salesDt = $now->format('Ymd');

        $buckets = ['A' => 0.0, 'B' => 0.0, 'C1' => 0.0, 'C2' => 0.0, 'C3' => 0.0, 'D' => 0.0];
        $taxBuckets = $buckets;
        $itemList = [];
        $seq = 1;
        $totTaxbl = 0.0;
        $totTax = 0.0;
        $totAmt = 0.0;

        foreach ($items as $item) {
            $qty = abs((float)$item['quantity']);
            $price = abs((float)$item['price']);
            $splyAmt = round($qty * $price, 4);
            $vatCat = $this->mapVatCategory($item['tax_class'] ?? 'A');
            $vat = $this->splitVat($splyAmt, $item['tax_class'] ?? 'A');

            if (isset($buckets[$vatCat])) {
                $buckets[$vatCat] += $vat['taxbl'];
                $taxBuckets[$vatCat] += $vat['tax'];
            } else {
                $buckets['A'] += $vat['taxbl'];
                $taxBuckets['A'] += $vat['tax'];
            }

            $totTaxbl += $vat['taxbl'];
            $totTax += $vat['tax'];
            $totAmt += $splyAmt;

            $itemList[] = [
                'itemSeq' => $seq++,
                'itemCd' => $this->ensureItemCode((int)$item['product_id'], $item['sku'] ?? null, $item['zra_item_cd'] ?? null),
                'itemClsCd' => $this->resolveUnspsc($item['unspsc_code'] ?? null),
                'itemNm' => $item['name'],
                'bcd' => $item['sku'] ?: null,
                'pkgUnitCd' => 'NT',
                'pkg' => max(1.0, $qty),
                'qtyUnitCd' => 'U',
                'qty' => $qty,
                'prc' => $price,
                'splyAmt' => $splyAmt,
                'dcRt' => 0,
                'dcAmt' => 0,
                'isrccCd' => '',
                'isrccNm' => '',
                'isrcRt' => 0,
                'isrcAmt' => 0,
                'vatCatCd' => $vatCat,
                'exciseTxCatCd' => null,
                'tlCatCd' => null,
                'iplCatCd' => null,
                'vatTaxblAmt' => $vat['taxbl'],
                'vatAmt' => $vat['tax'],
                'exciseTaxblAmt' => 0,
                'tlTaxblAmt' => 0,
                'iplTaxblAmt' => 0,
                'iplAmt' => 0,
                'tlAmt' => 0,
                'exciseTxAmt' => 0,
                'totAmt' => $splyAmt,
            ];
        }

        $orgInvcNo = (int)($opts['orgInvcNo'] ?? ($sale['zra_org_invc_no'] ?? 0));
        $orgSdcId = trim((string)($opts['orgSdcId'] ?? ''));
        $custTpin = $this->sanitizeTpin($opts['custTpin'] ?? ($sale['customer_tpin'] ?? null));
        $payload = [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'orgInvcNo' => $orgInvcNo,
            'cisInvcNo' => 'STP-' . str_pad((string)$sale['id'], 8, '0', STR_PAD_LEFT),
            'custNm' => mb_substr((string)($sale['customer_name'] ?: 'Walk-in Customer'), 0, 60),
            'salesTyCd' => 'N',
            'rcptTyCd' => $isRefund ? 'R' : 'S',
            'pmtTyCd' => $this->mapPayment($sale['payment_method'] ?? 'Cash'),
            'salesSttsCd' => '02',
            'cfmDt' => $cfmDt,
            'salesDt' => $salesDt,
            'stockRlsDt' => $cfmDt,
            'cnclReqDt' => null,
            'cnclDt' => null,
            'rfdDt' => $isRefund ? $cfmDt : null,
            'rfdRsnCd' => $isRefund ? ($opts['rfdRsnCd'] ?? '06') : null,
            'totItemCnt' => count($itemList),
            'taxblAmtA' => round($buckets['A'], 4),
            'taxblAmtB' => round($buckets['B'], 4),
            'taxblAmtC1' => round($buckets['C1'], 4),
            'taxblAmtC2' => round($buckets['C2'], 4),
            'taxblAmtC3' => round($buckets['C3'], 4),
            'taxblAmtD' => round($buckets['D'], 4),
            'taxblAmtRvat' => 0,
            'taxblAmtE' => 0,
            'taxblAmtF' => 0,
            'taxblAmtIpl1' => 0,
            'taxblAmtIpl2' => 0,
            'taxblAmtTl' => 0,
            'taxblAmtEcm' => 0,
            'taxblAmtExeeg' => 0,
            'taxblAmtTot' => 0,
            'taxRtA' => 16,
            'taxRtB' => 16,
            'taxRtC1' => 0,
            'taxRtC2' => 0,
            'taxRtC3' => 0,
            'taxRtD' => 0,
            'taxRtRvat' => 16,
            'taxRtE' => 0,
            'taxRtF' => 10,
            'taxRtIpl1' => 5,
            'taxRtIpl2' => 0,
            'taxRtTl' => 1.5,
            'taxRtEcm' => 5,
            'taxRtExeeg' => 3,
            'taxRtTot' => 0,
            'taxAmtA' => round($taxBuckets['A'], 4),
            'taxAmtB' => round($taxBuckets['B'], 4),
            'taxAmtC1' => round($taxBuckets['C1'], 4),
            'taxAmtC2' => round($taxBuckets['C2'], 4),
            'taxAmtC3' => round($taxBuckets['C3'], 4),
            'taxAmtD' => round($taxBuckets['D'], 4),
            'taxAmtRvat' => 0,
            'taxAmtE' => 0,
            'taxAmtF' => 0,
            'taxAmtIpl1' => 0,
            'taxAmtIpl2' => 0,
            'taxAmtTl' => 0,
            'taxAmtEcm' => 0,
            'taxAmtExeeg' => 0,
            'taxAmtTot' => 0,
            'totTaxblAmt' => round($totTaxbl, 4),
            'totTaxAmt' => round($totTax, 4),
            'cashDcRt' => 0,
            'cashDcAmt' => 0,
            'totAmt' => round(abs((float)$sale['final_total'] ?: $totAmt), 2),
            'prchrAcptcYn' => 'N',
            'remark' => $isRefund ? 'POS refund' : '',
            'regrId' => substr($user['id'], 0, 20),
            'regrNm' => substr($user['name'], 0, 60),
            'modrId' => substr($user['id'], 0, 20),
            'modrNm' => substr($user['name'], 0, 60),
            'saleCtyCd' => '1',
            'currencyTyCd' => 'ZMW',
            'exchangeRt' => 1,
            'itemList' => $itemList,
        ];
        if ($custTpin) {
            $payload['custTpin'] = $custTpin;
        }
        if ($isRefund) {
            $payload['orgSdcId'] = $orgSdcId;
            $payload['orgInvcNo'] = $orgInvcNo;
            $payload['rfdDt'] = $cfmDt;
            $payload['rfdRsnCd'] = $opts['rfdRsnCd'] ?? '06';
        }

        unset($nextInvcNo);
        return $this->omitEmptyOptional($payload);
    }

    /**
     * Credit notes must cite the original Smart Invoice SDC ID and receipt number.
     */
    private function resolveOriginalInvoice(array $sale, array $items, array $opts): array
    {
        $orgInvcNo = (int)($opts['orgInvcNo'] ?? ($sale['zra_org_invc_no'] ?? 0));
        $orgSdcId = trim((string)($opts['orgSdcId'] ?? ''));
        if ($orgInvcNo > 0 && $orgSdcId !== '') {
            return ['orgInvcNo' => $orgInvcNo, 'orgSdcId' => $orgSdcId];
        }

        foreach ($items as $line) {
            $origItemId = (int)($line['refund_sale_item_id'] ?? 0);
            if ($origItemId > 0) {
                $stmt = $this->pdo->prepare(
                    "SELECT s.zra_rcpt_no, s.zra_sdc_id
                     FROM sale_items si
                     JOIN sales s ON s.id = si.sale_id
                     WHERE si.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$origItemId]);
                $orig = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($orig && (int)($orig['zra_rcpt_no'] ?? 0) > 0 && trim((string)($orig['zra_sdc_id'] ?? '')) !== '') {
                    return [
                        'orgInvcNo' => (int)$orig['zra_rcpt_no'],
                        'orgSdcId' => trim((string)$orig['zra_sdc_id']),
                    ];
                }
            }

            $productId = (int)($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $stmt = $this->pdo->prepare(
                "SELECT s.zra_rcpt_no, s.zra_sdc_id
                 FROM sale_items si
                 JOIN sales s ON s.id = si.sale_id
                 WHERE si.product_id = ?
                   AND s.id <> ?
                   AND s.zra_rcpt_no IS NOT NULL AND s.zra_rcpt_no > 0
                   AND s.zra_sdc_id IS NOT NULL AND s.zra_sdc_id <> ''
                   AND COALESCE(s.zra_rcpt_sign, '') <> ''
                 ORDER BY s.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$productId, (int)($sale['id'] ?? 0)]);
            $orig = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($orig && (int)($orig['zra_rcpt_no'] ?? 0) > 0) {
                return [
                    'orgInvcNo' => (int)$orig['zra_rcpt_no'],
                    'orgSdcId' => trim((string)$orig['zra_sdc_id']),
                ];
            }
        }

        $settingsSdc = trim((string)($this->getSettings()['vsdc_sdcId'] ?? ''));
        return ['orgInvcNo' => $orgInvcNo, 'orgSdcId' => $orgSdcId !== '' ? $orgSdcId : $settingsSdc];
    }

    private function sanitizeTpin(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        $digits = preg_replace('/\D/', '', (string)$value) ?? '';
        return preg_match('/^\d{10}$/', $digits) ? $digits : null;
    }

    private function omitEmptyOptional(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $out[$key] = array_map(
                        fn($row) => is_array($row) ? $this->omitEmptyOptional($row) : $row,
                        $value
                    );
                } else {
                    $out[$key] = $this->omitEmptyOptional($value);
                }
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private function updateStockMaster(array $items, int $locationId): void
    {
        $stockList = [];
        foreach ($items as $item) {
            $itemCd = $this->ensureItemCode((int)$item['product_id'], $item['sku'] ?? null, $item['zra_item_cd'] ?? null);
            $qtyStmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = ? AND location_id = ?");
            $qtyStmt->execute([(int)$item['product_id'], $locationId]);
            $stockList[] = [
                'itemCd' => $itemCd,
                'rsdQty' => (float)$qtyStmt->fetchColumn(),
            ];
        }
        if (!$stockList) {
            return;
        }
        $this->post('stockMaster/saveStockMaster', [
            'tpin' => $this->tpin,
            'bhfId' => $this->bhfId,
            'regrId' => 'ADMIN',
            'regrNm' => 'ADMIN',
            'modrNm' => 'ADMIN',
            'modrId' => 'ADMIN',
            'stockItemList' => $stockList,
        ]);
    }

    private function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['resultCd' => '999', 'resultMsg' => "CURL Error: $error", 'data' => null];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            return ['resultCd' => (string)$httpCode, 'resultMsg' => (string)$response, 'data' => null];
        }
        return $decoded;
    }

    private function putSettings(array $pairs): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($pairs as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }
    }

    private function ensureItemCode(int $productId, ?string $sku, ?string $existing): string
    {
        if ($existing) {
            return $existing;
        }
        $code = $sku ? preg_replace('/[^A-Za-z0-9\-]/', '', $sku) : '';
        if ($code === '' || $code === null) {
            $code = 'STP' . str_pad((string)$productId, 7, '0', STR_PAD_LEFT);
        }
        $code = substr((string)$code, 0, 100);
        try {
            $this->pdo->prepare("UPDATE products SET zra_item_cd = ? WHERE id = ? AND (zra_item_cd IS NULL OR zra_item_cd = '')")
                ->execute([$code, $productId]);
        } catch (Throwable $e) {
            // Column may not exist yet on very old DBs; ensureSchema should have added it.
        }
        return $code;
    }

    /**
     * ZRA requires a commodity-level (level 4+) class from selectItemsClass.
     * Family codes such as 50101500 are not in the sandbox dictionary.
     */
    private function resolveUnspsc(?string $code): string
    {
        $digits = preg_replace('/\D/', '', (string)$code) ?? '';
        $match = $this->lookupValidItemClass($digits);
        if ($match) {
            return $match;
        }
        $fallback = preg_replace('/\D/', '', (string)($this->getSettings()['vsdc_default_unspsc'] ?? '')) ?? '';
        $fromSettings = $this->lookupValidItemClass($fallback);
        if ($fromSettings) {
            return $fromSettings;
        }
        return $this->firstValidItemClass() ?: '50202201';
    }

    private function lookupValidItemClass(string $digits): ?string
    {
        if ($digits === '' || !preg_match('/^\d{6,10}$/', $digits)) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                "SELECT item_cls_cd FROM zra_item_classes
                 WHERE use_yn = 'Y' AND item_cls_lvl >= 4 AND item_cls_cd = ?
                 LIMIT 1"
            );
            $stmt->execute([$digits]);
            $hit = $stmt->fetchColumn();
            if ($hit) {
                return (string)$hit;
            }
            $stem = $digits;
            while (strlen($stem) >= 4) {
                $stmt = $this->pdo->prepare(
                    "SELECT item_cls_cd FROM zra_item_classes
                     WHERE use_yn = 'Y' AND item_cls_lvl >= 4 AND item_cls_cd LIKE ?
                     ORDER BY LENGTH(item_cls_cd) DESC, item_cls_cd ASC
                     LIMIT 1"
                );
                $stmt->execute([$stem . '%']);
                $hit = $stmt->fetchColumn();
                if ($hit) {
                    return (string)$hit;
                }
                $stem = substr($stem, 0, -2);
            }
        } catch (Throwable $e) {
            return preg_match('/^\d{8,10}$/', $digits) ? $digits : null;
        }
        return null;
    }

    private function firstValidItemClass(): ?string
    {
        $preferred = ['50202201', '50201706', '50202301', '50191507', '50102515'];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT item_cls_cd FROM zra_item_classes
                 WHERE item_cls_cd = ? AND use_yn = 'Y' LIMIT 1"
            );
            foreach ($preferred as $cd) {
                $stmt->execute([$cd]);
                $hit = $stmt->fetchColumn();
                if ($hit) {
                    return (string)$hit;
                }
            }
            $hit = $this->pdo->query(
                "SELECT item_cls_cd FROM zra_item_classes
                 WHERE use_yn = 'Y' AND item_cls_lvl >= 4
                 ORDER BY item_cls_cd ASC LIMIT 1"
            )->fetchColumn();
            return $hit ? (string)$hit : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function storeItemClasses(array $list): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO zra_item_classes (item_cls_cd, item_cls_nm, item_cls_lvl, tax_ty_cd, use_yn)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE item_cls_nm = VALUES(item_cls_nm), tax_ty_cd = VALUES(tax_ty_cd), use_yn = VALUES(use_yn)"
        );
        $n = 0;
        foreach ($list as $row) {
            $cd = (string)($row['itemClsCd'] ?? '');
            if ($cd === '') {
                continue;
            }
            $stmt->execute([
                $cd,
                $row['itemClsNm'] ?? '',
                (int)($row['itemClsLvl'] ?? 0),
                $row['taxTyCd'] ?? null,
                $row['useYn'] ?? 'Y',
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * POS tax_class A/B/C/D → Smart Invoice VAT category.
     * A=Standard 16%, B=Zero-rated → C3, C=Exempt → D, D=Excise treated as A for VAT.
     */
    private function mapVatCategory(string $taxClass): string
    {
        return match (strtoupper($taxClass)) {
            'B' => 'C3',
            'C' => 'D',
            'C3' => 'C3',
            'D' => 'A',
            default => 'A',
        };
    }

    private function splitVat(float $inclusive, string $taxClass): array
    {
        $cat = $this->mapVatCategory($taxClass);
        if ($cat === 'A' || $cat === 'B') {
            $taxbl = round($inclusive / 1.16, 4);
            return ['taxbl' => $taxbl, 'tax' => round($inclusive - $taxbl, 4)];
        }
        return ['taxbl' => round($inclusive, 4), 'tax' => 0.0];
    }

    private function mapPayment(string $method): string
    {
        $p = strtolower($method);
        if (str_contains($p, 'cash')) {
            return '01';
        }
        if (str_contains($p, 'card') || str_contains($p, 'visa') || str_contains($p, 'credit')) {
            return '05';
        }
        if (str_contains($p, 'airtel') || str_contains($p, 'mtn') || str_contains($p, 'mobile') || str_contains($p, 'zamtel') || str_contains($p, 'online') || str_contains($p, 'uber')) {
            return '06';
        }
        if (str_contains($p, 'split')) {
            return '03';
        }
        return '07';
    }

    private function linesAreRefund(array $items): bool
    {
        foreach ($items as $item) {
            if ((float)$item['quantity'] < 0 || (float)$item['price'] < 0 || !empty($item['is_refund'])) {
                return true;
            }
            if (strtolower((string)($item['status'] ?? '')) === 'refunded') {
                return true;
            }
        }
        return false;
    }

    private function lookupUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, username, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'id' => (string)($row['id'] ?? 'ADMIN'),
            'name' => $row['full_name'] ?? $row['username'] ?? 'ADMIN',
        ];
    }

    private function fiscalizablePendingWhere(): string
    {
        return "payment_status = 'paid'
            AND (zra_rcpt_sign IS NULL OR zra_rcpt_sign = '')
            AND COALESCE(zra_status, 'pending') IN ('pending', 'failed')
            AND ABS(final_total) > 0
            AND EXISTS (
                SELECT 1 FROM sale_items si
                WHERE si.sale_id = sales.id AND si.status NOT IN ('voided')
            )";
    }

    private function markSaleFailed(int $saleId, string $message): void
    {
        try {
            $this->pdo->prepare("UPDATE sales SET zra_status = 'failed', zra_last_error = ? WHERE id = ?")
                ->execute([substr($message, 0, 1000), $saleId]);
        } catch (Throwable $e) {
            // ignore if columns are missing; ensureSchema will add them next request
        }
    }

    private function markSaleSkipped(int $saleId, string $message): void
    {
        try {
            $this->pdo->prepare("UPDATE sales SET zra_status = 'skipped', zra_last_error = ? WHERE id = ?")
                ->execute([substr($message, 0, 1000), $saleId]);
        } catch (Throwable $e) {
            // ignore if columns are missing
        }
    }

    private function logSync(string $entity, int $entityId, array $res): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO zra_sync_log (entity_type, entity_id, result_cd, result_msg)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $entity,
                $entityId,
                (string)($res['resultCd'] ?? ''),
                substr((string)($res['resultMsg'] ?? ''), 0, 500),
            ]);
        } catch (Throwable $e) {
            // logging is best-effort
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $this->ensureSaleColumns();
        $this->ensureProductColumns();
        $this->ensureGrvColumns();
        $this->ensureSaleItemColumns();
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS zra_standard_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code_class VARCHAR(10) NOT NULL,
                code_class_name VARCHAR(100) NOT NULL,
                code VARCHAR(20) NOT NULL,
                code_name VARCHAR(100) NOT NULL,
                user_defined_name VARCHAR(100) DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_code (code_class, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS zra_item_classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_cls_cd VARCHAR(10) NOT NULL,
                item_cls_nm VARCHAR(200) NOT NULL,
                item_cls_lvl INT DEFAULT 0,
                tax_ty_cd VARCHAR(10) DEFAULT NULL,
                use_yn CHAR(1) DEFAULT 'Y',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_cls (item_cls_cd)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS zra_sync_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(20) NOT NULL,
                entity_id INT NOT NULL,
                result_cd VARCHAR(10) DEFAULT NULL,
                result_msg VARCHAR(500) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->putSettings([
            'vsdc_sdcId' => $this->getSettings()['vsdc_sdcId'] ?? '',
            'vsdc_mrcNo' => $this->getSettings()['vsdc_mrcNo'] ?? '',
            'vsdc_taxprNm' => $this->getSettings()['vsdc_taxprNm'] ?? '',
            'vsdc_last_sale_no' => $this->getSettings()['vsdc_last_sale_no'] ?? '0',
            'vsdc_last_invc_no' => $this->getSettings()['vsdc_last_invc_no'] ?? '0',
            'vsdc_last_sar_no' => $this->getSettings()['vsdc_last_sar_no'] ?? '0',
            'vsdc_default_unspsc' => $this->getSettings()['vsdc_default_unspsc'] ?? '50202201',
        ]);
        self::$schemaReady = true;
    }

    private function ensureSaleColumns(): void
    {
        $this->addColumnIfMissing('sales', 'zra_status', "VARCHAR(20) DEFAULT 'pending'");
        $this->addColumnIfMissing('sales', 'zra_cis_invc_no', 'VARCHAR(50) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_org_invc_no', 'INT DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_last_error', 'TEXT DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_rcpt_no', 'INT DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_intrl_data', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_rcpt_sign', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_vsdc_rcpt_pbct_date', 'VARCHAR(20) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_sdc_id', 'VARCHAR(50) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_mrc_no', 'VARCHAR(50) DEFAULT NULL');
        $this->addColumnIfMissing('sales', 'zra_qr_code_url', 'TEXT DEFAULT NULL');
    }

    private function ensureProductColumns(): void
    {
        $this->addColumnIfMissing('products', 'zra_item_cd', 'VARCHAR(100) DEFAULT NULL');
        $this->addColumnIfMissing('products', 'zra_synced', 'TINYINT(1) DEFAULT 0');
        $this->addColumnIfMissing('products', 'tax_class', "VARCHAR(10) NOT NULL DEFAULT 'A'");
        $this->addColumnIfMissing('products', 'unspsc_code', 'VARCHAR(20) DEFAULT NULL');
    }

    private function ensureGrvColumns(): void
    {
        $this->addColumnIfMissing('grvs', 'zra_synced', 'TINYINT(1) DEFAULT 0');
    }

    private function ensureSaleItemColumns(): void
    {
        $this->addColumnIfMissing('sale_items', 'refund_sale_item_id', 'INT DEFAULT NULL');
        $this->addColumnIfMissing('sale_items', 'is_refund', 'TINYINT(1) DEFAULT 0');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}
