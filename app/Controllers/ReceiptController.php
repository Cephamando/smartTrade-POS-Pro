<?php

namespace App\Controllers;

use App\Models\Sale;
use App\Services\ReceiptPrinter;

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
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
                'business_name', 'receipt_name', 'receipt_header', 'receipt_footer',
                'receipt_logo_path', 'logo_path', 'vsdc_sdcId', 'vsdc_mrcNo',
                'printer_interface', 'printer_lan_ip', 'printer_lan_port',
                'printer_usb_path', 'printer_font_b_cols', 'printer_font_a_cols'
            )"
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
        $items = Sale::receiptItems($saleId, $isIncremental ? $specificItems : []);
        $gateway = ($gw = \App\Services\Flutterwave::detailsForSales($pdo, [$saleId]))[$saleId] ?? null;

        $viewPayload = [
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
            'gateway' => $gateway,
            'sale' => $sale,
            'items' => $items,
            'fontBCss' => ReceiptPrinter::getBrowserFontBCss(),
            'printerInterface' => strtolower(trim((string)($sysSettings['printer_interface'] ?? 'browser'))),
        ];

        // AJAX / Direct ESC/POS Hardware Print Trigger (?direct_print=1)
        if (!empty($_GET['direct_print']) || !empty($_POST['direct_print'])) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $printer = new ReceiptPrinter($pdo, $sysSettings);
                $printer->buildFromControllerData($viewPayload);
                $iface = strtolower(trim((string)($_GET['interface'] ?? $_POST['interface'] ?? $sysSettings['printer_interface'] ?? 'lan')));
                if ($iface === 'browser') {
                    $iface = 'lan';
                }
                $printer->dispatchBuffer($saleId, ['interface' => $iface]);
                echo json_encode([
                    'status' => 'ok',
                    'sale_id' => $saleId,
                    'interface' => $iface,
                    'message' => "Printed #{$saleId} on YHD-8390 in Font B.",
                ]);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            }
            exit;
        }

        $this->render($viewPayload);
    }
}
