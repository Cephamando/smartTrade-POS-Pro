<?php

namespace App\Controllers;

use App\Models\Sale;

final class ReceiptController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        if (!isset($_GET['sale_id'])) {
            die('Invalid Sale ID');
        }
        $saleId = (int)$_GET['sale_id'];
        $isBill = isset($_GET['is_bill']) && $_GET['is_bill'] == '1';
        $specificItems = isset($_GET['items']) && $_GET['items'] !== ''
            ? array_filter(array_map('intval', explode(',', (string)$_GET['items'])))
            : [];
        $isIncremental = (!empty($specificItems) && $isBill);

        $pdo = $this->pdo();
        $settingsStmt = $pdo->query(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_name', 'receipt_name', 'receipt_header', 'receipt_footer', 'receipt_logo_path', 'logo_path', 'vsdc_sdcId', 'vsdc_mrcNo')"
        );
        $sysSettings = [];
        while ($sRow = $settingsStmt->fetch()) {
            $sysSettings[$sRow['setting_key']] = $sRow['setting_value'];
        }

        $sale = Sale::receiptSale($saleId);
        if (!$sale) {
            die('Sale not found.');
        }

        $zraClient = new \ZraVsdcClient($pdo);
        $face = \App\Models\Setting::receiptFace($sysSettings);
        $this->render([
            'saleId' => $saleId,
            'isBill' => $isBill,
            'specificItems' => $specificItems,
            'isIncremental' => $isIncremental,
            'sysSettings' => $sysSettings,
            'bizName' => $face['name'],
            'logoUrl' => \App\Helpers\Media::url($face['logo']),
            'receiptHeader' => $face['header'],
            'receiptFooter' => $face['footer'],
            'zraTpin' => $zraClient->getConfig()['tpin'],
            'zraClient' => $zraClient,
            'gateway' => ($gw = \App\Services\Flutterwave::detailsForSales($pdo, [$saleId]))[$saleId] ?? null,
            'sale' => $sale,
            'items' => Sale::receiptItems($saleId, $isIncremental ? $specificItems : []),
        ]);
    }
}
