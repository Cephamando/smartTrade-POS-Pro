<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\Movement;
use App\Models\Setting;
use App\Models\Tip;
use PDO;
use RuntimeException;
use Throwable;
use ZraVsdcClient;

/**
 * ESC/POS & Browser Thermal Receipt Printer service for smartTrade-POS-Pro.
 * Optimized for the YHDAA YHD-8390 (80mm paper width, USB + LAN interfaces)
 * using native hardware Font B (9x17 dot matrix, 56/64 columns) for dense
 * Shoprite-style itemized receipts, Barman Slips, Proforma Bills, split payments,
 * Flutterwave references, and ZRA Smart Invoice (VSDC) fiscal blocks + QR codes.
 */
final class ReceiptPrinter
{
    private const INIT = "\x1B\x40";
    private const FONT_A = "\x1B\x4D\x00";              // 12x24 dots (42/48 cols on 80mm)
    private const FONT_B = "\x1B\x4D\x01";              // 9x17 dots (56/64 cols on 80mm - Shoprite style)
    private const ALIGN_LEFT = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const ALIGN_RIGHT = "\x1B\x61\x02";
    private const BOLD_ON = "\x1B\x45\x01";
    private const BOLD_OFF = "\x1B\x45\x00";
    private const DOUBLE_SIZE_ON = "\x1D\x21\x11";      // Double width + double height
    private const DOUBLE_HEIGHT_ON = "\x1B\x21\x10";    // Double height in Font A
    private const NORMAL_SIZE = "\x1D\x21\x00\x1B\x21\x00";
    private const LINE_SPACING_TIGHT = "\x1B\x33\x16";  // 22-dot line feed for compact Font B rows
    private const LINE_SPACING_DEFAULT = "\x1B\x32";    // Default 1/6-inch line feed
    private const CUT_FULL = "\x1D\x56\x41\x00";        // GS V A 0 (Feed and full cut)
    private const DRAWER_KICK = "\x1B\x70\x00\x19\xFA"; // Pulse pin 2 for cash drawer

    private PDO $pdo;
    private array $settings;
    private string $buffer = '';
    private int $fontBColumns;
    private int $fontAColumns;

    public function __construct(?PDO $pdo = null, ?array $settings = null, int $fontBColumns = 56, int $fontAColumns = 42)
    {
        $this->pdo = $pdo ?? Database::connection();
        try {
            $this->settings = $settings ?? Setting::all();
        } catch (Throwable $e) {
            $this->settings = $settings ?? [];
        }

        $configuredColsB = (int)($this->settings['printer_font_b_cols'] ?? $fontBColumns);
        $configuredColsA = (int)($this->settings['printer_font_a_cols'] ?? $fontAColumns);

        $this->fontBColumns = in_array($configuredColsB, [56, 64], true) ? $configuredColsB : 56;
        $this->fontAColumns = in_array($configuredColsA, [42, 48], true) ? $configuredColsA : 42;

        $this->initialize();
    }

    public function initialize(): self
    {
        $this->buffer = self::INIT;
        return $this;
    }

    public function setFontA(): self
    {
        $this->buffer .= self::FONT_A . self::LINE_SPACING_DEFAULT;
        return $this;
    }

    public function setFontB(): self
    {
        $this->buffer .= self::FONT_B . self::LINE_SPACING_TIGHT;
        return $this;
    }

    public function openCashDrawer(): self
    {
        $this->buffer .= self::DRAWER_KICK;
        return $this;
    }

    /**
     * Build ESC/POS payload directly from ReceiptController data (supports Official Receipt,
     * Client Proforma Bill, and Barman Slip incremental printouts).
     *
     * @param array<string,mixed> $data
     */
    public function buildFromControllerData(array $data): self
    {
        $this->initialize();

        $sale = is_array($data['sale'] ?? null) ? $data['sale'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $isBill = !empty($data['isBill']);
        $isIncremental = !empty($data['isIncremental']);
        $bizName = trim((string)($data['bizName'] ?? $this->settings['business_name'] ?? 'smartTrade POS'));
        $receiptHeader = trim((string)($data['receiptHeader'] ?? $this->settings['receipt_header'] ?? ''));
        $receiptFooter = trim((string)($data['receiptFooter'] ?? $this->settings['receipt_footer'] ?? 'Thank you'));
        $zraTpin = trim((string)($data['zraTpin'] ?? ''));
        $zraClient = ($data['zraClient'] ?? null) instanceof ZraVsdcClient
            ? $data['zraClient']
            : new ZraVsdcClient($this->pdo);
        $gateway = is_array($data['gateway'] ?? null) ? $data['gateway'] : null;

        if ($isIncremental) {
            return $this->buildBarmanSlip($sale, $items, $bizName);
        }

        $copyName = $isBill ? 'CLIENT PROFORMA' : 'OFFICIAL RECEIPT';
        $subType = $isBill ? 'PROFORMA BILL (UNPAID)' : 'FINAL BILL';

        $locName = trim((string)($sale['loc_name'] ?? $sale['location_name'] ?? ''));
        $headerLines = [];
        if ($receiptHeader !== '') {
            $headerLines[] = $receiptHeader;
        }
        if ($locName !== '') {
            $headerLines[] = $locName;
        }
        $tpinToPrint = ($zraTpin !== '' && stripos($receiptHeader, 'TPIN') === false && !str_contains($receiptHeader, $zraTpin))
            ? $zraTpin
            : '';

        $this->addHeader($bizName, $tpinToPrint, implode("\n", $headerLines));

        $this->buffer .= self::ALIGN_CENTER;
        $this->setFontB();

        if (!empty($_SESSION['training_mode'])) {
            $this->buffer .= self::BOLD_ON . "*** TRAINING SAMPLE - NOT FILED WITH ZRA ***" . self::BOLD_OFF . "\n";
        }

        $this->buffer .= self::BOLD_ON . "[ {$copyName} - {$subType} ]" . self::BOLD_OFF . "\n";
        $this->buffer .= self::ALIGN_LEFT;

        $saleId = (int)($sale['id'] ?? 0);
        $createdAt = (string)($sale['created_at'] ?? date('Y-m-d H:i:s'));
        $cashier = (string)($sale['username'] ?? $sale['cashier_name'] ?? 'Cashier');
        $customer = (string)($sale['customer_name'] ?? 'Walk-in');

        $this->buffer .= $this->twoColumnLine("Order #{$saleId}", date('d M Y h:i A', strtotime($createdAt) ?: time())) . "\n";
        $this->buffer .= $this->twoColumnLine("Cashier: " . substr($cashier, 0, 22), "Customer: " . substr($customer, 0, 20)) . "\n";
        $this->buffer .= $this->divider('-') . "\n";

        // Itemized list in Shoprite Font B
        $displayTotal = 0.0;
        $formattedItems = [];
        foreach ($items as $i) {
            $qty = (float)($i['quantity'] ?? $i['qty'] ?? 1);
            $unitPrice = (float)($i['price'] ?? 0);
            $lineTotal = $unitPrice * $qty;
            $displayTotal += $lineTotal;
            $formattedItems[] = [
                'name' => (string)($i['name'] ?? 'Item'),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'price' => $lineTotal,
                'tax_code' => strtoupper((string)($i['tax_class'] ?? 'A')),
            ];
        }

        $this->addItemizedList($formattedItems);

        // Totals calculation matching receipt.php
        $tipAmt = (float)($sale['tip_amount'] ?? 0);
        $surchargeAmt = (float)($sale['surcharge_amount'] ?? 0);
        $surchargeReason = (string)($sale['surcharge_reason'] ?? '');
        $finalTotal = (float)($sale['final_total'] ?? 0);

        $calcDisc = ($displayTotal + $tipAmt + $surchargeAmt) - $finalTotal;
        if ($calcDisc < 0.01) {
            $calcDisc = 0.0;
        }

        $vatSplit = ['excl' => 0.0, 'vat' => 0.0, 'zero' => 0.0, 'exempt' => 0.0];
        $itemIncl = max(0.0, $displayTotal);
        $scale = ($calcDisc > 0.01 && $itemIncl > 0) ? max(0.0, ($itemIncl - $calcDisc) / $itemIncl) : 1.0;
        $stdIncl = 0.0;

        foreach ($items as $i) {
            $incl = ((float)$i['price'] * (float)$i['quantity']) * $scale;
            $cls = strtoupper((string)($i['tax_class'] ?? 'A'));
            if ($cls === 'D' || $cls === '' || $cls === 'A') {
                $stdIncl += $incl;
            } elseif ($cls === 'B' || $cls === 'C3') {
                $vatSplit['zero'] += $incl;
            } else {
                $vatSplit['exempt'] += $incl;
            }
        }

        $stdIncl += $tipAmt + $surchargeAmt;
        $vatSplit['vat'] = round($stdIncl - ($stdIncl / 1.16), 2);
        $vatSplit['excl'] = round($stdIncl - $vatSplit['vat'] + $vatSplit['zero'] + $vatSplit['exempt'], 2);
        $vatSplit['zero'] = round($vatSplit['zero'], 2);
        $vatSplit['exempt'] = round($vatSplit['exempt'], 2);

        $this->setFontB();
        $this->buffer .= $this->twoColumnLine('Amount excl. VAT:', number_format($vatSplit['excl'], 2)) . "\n";
        $this->buffer .= $this->twoColumnLine('VAT 16%:', number_format($vatSplit['vat'], 2)) . "\n";

        if ($vatSplit['zero'] > 0.004) {
            $this->buffer .= $this->twoColumnLine('Incl. zero-rated:', number_format($vatSplit['zero'], 2)) . "\n";
        }
        if ($vatSplit['exempt'] > 0.004) {
            $this->buffer .= $this->twoColumnLine('Incl. exempt:', number_format($vatSplit['exempt'], 2)) . "\n";
        }
        if ($surchargeAmt > 0) {
            $lbl = 'Incl. surcharge' . ($surchargeReason !== '' ? " ({$surchargeReason})" : '') . ':';
            $this->buffer .= $this->twoColumnLine($lbl, number_format($surchargeAmt, 2)) . "\n";
        }
        if ($tipAmt > 0) {
            $this->buffer .= $this->twoColumnLine('Incl. tip/service:', number_format($tipAmt, 2)) . "\n";
        }
        if ($calcDisc > 0.01) {
            $this->buffer .= $this->twoColumnLine('Incl. discount:', '-ZMW ' . number_format($calcDisc, 2)) . "\n";
        }

        // Grand Total in Font A Bold
        $totalLabel = $isBill ? 'TAB BALANCE:' : 'TOTAL PAID:';
        $this->setFontA();
        $this->buffer .= self::BOLD_ON . self::DOUBLE_HEIGHT_ON
            . $this->twoColumnLineFontA($totalLabel, 'ZMW ' . number_format($finalTotal, 2))
            . self::NORMAL_SIZE . self::BOLD_OFF . "\n";

        $this->setFontB();
        $this->buffer .= $this->divider('-') . "\n";

        if (!$isBill) {
            $splitLegs = function_exists('saleSplitLegs') ? saleSplitLegs($sale) : [];
            if (($sale['payment_method'] ?? '') === 'Split' || $splitLegs !== []) {
                $this->buffer .= self::BOLD_ON . "Paid Via Split:\n" . self::BOLD_OFF;
                foreach ($splitLegs as $leg) {
                    $this->buffer .= $this->twoColumnLine(' - ' . (string)$leg['method'], 'ZMW ' . number_format((float)$leg['amount'], 2)) . "\n";
                }
            } else {
                $this->buffer .= $this->twoColumnLine('Paid Via:', (string)($sale['payment_method'] ?? 'Cash')) . "\n";
            }

            $tendered = (float)($sale['amount_tendered'] ?? 0);
            if ($tendered > 0 && (stripos((string)($sale['payment_method'] ?? ''), 'cash') !== false || ($sale['payment_method'] ?? '') === 'Split')) {
                if (!empty($sale['tender_currency']) && (float)($sale['tender_fx_amount'] ?? 0) > 0) {
                    $fxText = $sale['tender_currency'] . ' ' . number_format((float)$sale['tender_fx_amount'], 2)
                        . ' @ ' . number_format((float)($sale['tender_fx_rate'] ?? 0), 4);
                    $this->buffer .= $this->twoColumnLine('Foreign cash:', $fxText) . "\n";
                }
                $this->buffer .= $this->twoColumnLine('Tendered:', 'ZMW ' . number_format($tendered, 2)) . "\n";
                $this->buffer .= self::BOLD_ON . $this->twoColumnLine('Change:', 'ZMW ' . number_format((float)($sale['change_due'] ?? 0), 2)) . self::BOLD_OFF . "\n";
            }

            if (!empty($sale['stanbic_rrn'])) {
                $this->buffer .= $this->twoColumnLine('Stanbic RRN:', (string)$sale['stanbic_rrn']) . "\n";
            }
            if (!empty($sale['momo_ref'])) {
                $this->buffer .= $this->twoColumnLine('MoMo ID:', (string)$sale['momo_ref']) . "\n";
            }
            if (!empty($gateway['lines']) && is_array($gateway['lines'])) {
                foreach ($gateway['lines'] as $line) {
                    $this->buffer .= $this->twoColumnLine((string)($line['label'] ?? 'Ref') . ':', (string)($line['value'] ?? '')) . "\n";
                }
            } elseif (!empty($sale['gateway_ref'])) {
                $this->buffer .= $this->twoColumnLine('Gateway ref:', (string)$sale['gateway_ref']) . "\n";
            }

            $postTips = [];
            try {
                $postTips = Tip::forSale($saleId);
            } catch (Throwable $e) {
                $postTips = [];
            }
            $postTipTotal = array_sum(array_map(static fn($t) => (float)($t['amount'] ?? 0), $postTips));
            if ($postTipTotal > 0) {
                $this->buffer .= $this->twoColumnLine('Tip after payment:', 'ZMW ' . number_format($postTipTotal, 2)) . "\n";
            }

            if (($sale['payment_status'] ?? '') === 'pending' && $tendered > 0) {
                $this->buffer .= $this->twoColumnLine('Paid So Far:', 'ZMW ' . number_format($tendered, 2)) . "\n";
                $this->buffer .= self::BOLD_ON . $this->twoColumnLine('BALANCE DUE:', 'ZMW ' . number_format($finalTotal - $tendered, 2)) . self::BOLD_OFF . "\n";
            }

            $this->buffer .= $this->twoColumnLine('Status:', strtoupper((string)($sale['payment_status'] ?? 'PAID'))) . "\n";
            $this->buffer .= $this->divider('-') . "\n";

            if (!empty($sale['zra_rcpt_sign'])) {
                $this->addZraFiscalBlock($sale, $zraClient);
            } elseif ($zraClient->isInitialized() && ($sale['payment_status'] ?? '') === 'paid') {
                $this->buffer .= self::ALIGN_CENTER . "ZRA: pending sync\n" . self::ALIGN_LEFT;
            }
        }

        $this->addFooter($receiptFooter);
        return $this->cut();
    }

    /**
     * Build a Barman Slip for incremental tab additions in Font B + double-size Order ID.
     *
     * @param array<string,mixed> $sale
     * @param list<array<string,mixed>> $items
     */
    public function buildBarmanSlip(array $sale, array $items, string $bizName): self
    {
        $this->initialize();
        $this->buffer .= self::ALIGN_CENTER;
        $this->setFontA();
        $this->buffer .= self::BOLD_ON . $this->sanitizeAscii($bizName) . "\n";
        $this->buffer .= "[ BARMAN SLIP - ORDER SLIP ]\n";
        $this->buffer .= self::DOUBLE_SIZE_ON . "#" . (int)($sale['id'] ?? 0) . "\n" . self::NORMAL_SIZE . self::BOLD_OFF;

        $this->setFontB();
        $customer = strtoupper($this->sanitizeAscii((string)($sale['customer_name'] ?? 'Walk-in')));
        $this->buffer .= self::BOLD_ON . $customer . self::BOLD_OFF . "\n";
        $this->buffer .= $this->divider('-') . "\n";

        $this->buffer .= self::ALIGN_LEFT;
        foreach ($items as $i) {
            $qty = class_exists(Movement::class) ? Movement::formatQty((float)($i['quantity'] ?? 1)) : (string)($i['quantity'] ?? 1);
            $name = $this->sanitizeAscii((string)($i['name'] ?? 'Item'));
            $this->buffer .= self::BOLD_ON . str_pad($qty . 'x', 8) . self::BOLD_OFF . substr($name, 0, $this->fontBColumns - 8) . "\n";
        }

        $this->buffer .= $this->divider('-') . "\n";
        $this->buffer .= self::ALIGN_CENTER;
        $createdAt = (string)($sale['created_at'] ?? date('Y-m-d H:i:s'));
        $this->buffer .= date('d M Y h:i A', strtotime($createdAt) ?: time()) . "\n";
        $this->buffer .= $this->sanitizeAscii((string)($sale['username'] ?? '')) . "\n\n\n";

        return $this->cut();
    }

    /**
     * Load a sale by ID from the database and build the complete ESC/POS payload.
     */
    public function fromSaleId(int $saleId, bool $isBill = false, array $specificItems = []): self
    {
        if ($saleId <= 0) {
            throw new RuntimeException('A valid sale ID is required to build a receipt.');
        }

        $sale = \App\Models\Sale::receiptSale($saleId);
        if (!$sale) {
            throw new RuntimeException("Sale #{$saleId} was not found.");
        }

        $isIncremental = (!empty($specificItems) && $isBill);
        $items = \App\Models\Sale::receiptItems($saleId, $isIncremental ? $specificItems : []);
        $zraClient = new ZraVsdcClient($this->pdo);
        $face = Setting::receiptFace($this->settings);
        $gateway = ($gw = Flutterwave::detailsForSales($this->pdo, [$saleId]))[$saleId] ?? null;

        return $this->buildFromControllerData([
            'saleId' => $saleId,
            'isBill' => $isBill,
            'specificItems' => $specificItems,
            'isIncremental' => $isIncremental,
            'sysSettings' => $this->settings,
            'bizName' => $face['name'] ?? 'smartTrade POS',
            'receiptHeader' => $face['header'] ?? '',
            'receiptFooter' => $face['footer'] ?? 'Thank you',
            'zraTpin' => $zraClient->getConfig()['tpin'] ?? '',
            'zraClient' => $zraClient,
            'gateway' => $gateway,
            'sale' => $sale,
            'items' => $items,
        ]);
    }

    public function addHeader(string $storeName, string $tpin, string $branchInfo): self
    {
        $this->buffer .= self::ALIGN_CENTER;
        $this->setFontA();
        $this->buffer .= self::BOLD_ON . self::DOUBLE_HEIGHT_ON . $this->sanitizeAscii($storeName) . "\n" . self::NORMAL_SIZE . self::BOLD_OFF;

        $this->setFontB();
        if (trim($tpin) !== '') {
            $this->buffer .= self::BOLD_ON . "TPIN: " . $this->sanitizeAscii($tpin) . self::BOLD_OFF . "\n";
        }
        if (trim($branchInfo) !== '') {
            foreach (explode("\n", trim($branchInfo)) as $line) {
                $clean = $this->sanitizeAscii($line);
                if ($clean !== '') {
                    $this->buffer .= $clean . "\n";
                }
            }
        }
        $this->buffer .= $this->divider('-') . "\n";
        return $this;
    }

    /**
     * Render itemized lines in Shoprite-style Font B (56 or 64 columns).
     *
     * @param list<array<string,mixed>> $items
     */
    public function addItemizedList(array $items): self
    {
        $this->buffer .= self::ALIGN_LEFT;
        $this->setFontB();

        $qtyWidth = 7;
        $unitWidth = 10;
        $amtWidth = 13;
        $nameWidth = max(20, $this->fontBColumns - ($qtyWidth + $unitWidth + $amtWidth));

        $hdrName = str_pad('ITEM', $nameWidth);
        $hdrQty = str_pad('QTY', $qtyWidth, ' ', STR_PAD_LEFT);
        $hdrUnit = str_pad('PRICE', $unitWidth, ' ', STR_PAD_LEFT);
        $hdrAmt = str_pad('TOTAL', $amtWidth, ' ', STR_PAD_LEFT);

        $this->buffer .= self::BOLD_ON . "{$hdrName}{$hdrQty}{$hdrUnit}{$hdrAmt}\n" . self::BOLD_OFF;
        $this->buffer .= $this->divider('-') . "\n";

        foreach ($items as $item) {
            $rawName = trim((string)($item['name'] ?? 'Item'));
            $qtyVal = isset($item['qty']) ? (float)$item['qty'] : 1.0;
            $qtyStr = class_exists(Movement::class) ? Movement::formatQty($qtyVal) : (string)$qtyVal;

            $lineAmount = isset($item['price']) ? (float)$item['price'] : 0.0;
            $unitPrice = isset($item['unit_price'])
                ? (float)$item['unit_price']
                : ($qtyVal != 0.0 ? round($lineAmount / $qtyVal, 2) : $lineAmount);

            $nameClean = $this->sanitizeAscii($rawName);
            $nameLine1 = str_pad(substr($nameClean, 0, $nameWidth), $nameWidth);
            $qtyCol = str_pad(substr($qtyStr, 0, $qtyWidth - 1), $qtyWidth, ' ', STR_PAD_LEFT);
            $unitCol = str_pad(number_format($unitPrice, 2), $unitWidth, ' ', STR_PAD_LEFT);
            $amtCol = str_pad(number_format($lineAmount, 2), $amtWidth, ' ', STR_PAD_LEFT);

            $this->buffer .= "{$nameLine1}{$qtyCol}{$unitCol}{$amtCol}\n";

            if (strlen($nameClean) > $nameWidth) {
                $overflow = trim(substr($nameClean, $nameWidth, $nameWidth));
                if ($overflow !== '') {
                    $this->buffer .= "  " . $overflow . "\n";
                }
            }
        }

        $this->buffer .= $this->divider('-') . "\n";
        return $this;
    }

    public function addTotals(float $subtotal, float $vat, float $total): self
    {
        $this->buffer .= self::ALIGN_LEFT;
        $this->setFontB();
        $this->buffer .= $this->twoColumnLine('Amount excl. VAT:', number_format($subtotal, 2)) . "\n";
        $this->buffer .= $this->twoColumnLine('VAT 16%:', number_format($vat, 2)) . "\n";

        $this->setFontA();
        $this->buffer .= self::BOLD_ON . $this->twoColumnLineFontA('TOTAL PAID:', 'ZMW ' . number_format($total, 2)) . self::BOLD_OFF . "\n";

        $this->setFontB();
        $this->buffer .= $this->divider('-') . "\n";
        return $this;
    }

    /**
     * Render ZRA Smart Invoice (VSDC) fiscal signature, SDC details, and hardware QR code.
     *
     * @param array<string,mixed> $sale
     */
    public function addZraFiscalBlock(array $sale, ?ZraVsdcClient $vsdc = null): self
    {
        $rcptSign = trim((string)($sale['zra_rcpt_sign'] ?? ''));
        $sdcId = trim((string)($sale['zra_sdc_id'] ?? ''));
        $mrcNo = trim((string)($sale['zra_mrc_no'] ?? ''));
        $rcptNo = (string)($sale['zra_rcpt_no'] ?? '');
        $pbctDate = trim((string)($sale['zra_vsdc_rcpt_pbct_date'] ?? ''));

        if ($rcptSign === '' && $sdcId === '') {
            return $this;
        }

        $this->setFontB();
        $title = !empty($_SESSION['training_mode']) ? 'SAMPLE ZRA SMART INVOICE' : 'ZRA SMART INVOICE';
        $this->buffer .= self::ALIGN_CENTER . self::BOLD_ON . $title . self::BOLD_OFF . "\n";

        if ($sdcId !== '') {
            $this->buffer .= "SDC: {$sdcId}\n";
        }
        if ($mrcNo !== '') {
            $this->buffer .= "MRC: {$mrcNo}\n";
        }
        if ($rcptNo !== '') {
            $this->buffer .= "Rcpt No: {$rcptNo}\n";
        }
        if ($pbctDate !== '') {
            $this->buffer .= "{$pbctDate}\n";
        }
        if ($rcptSign !== '') {
            $this->buffer .= self::BOLD_ON . $rcptSign . self::BOLD_OFF . "\n";
        }

        $vsdc ??= new ZraVsdcClient($this->pdo);
        $qrUrl = $vsdc->receiptQrUrl($rcptSign, (string)($sale['zra_qr_code_url'] ?? ''));
        if ($qrUrl !== null && $qrUrl !== '') {
            $this->addQrCode($qrUrl);
        }

        $this->buffer .= self::ALIGN_LEFT . $this->divider('-') . "\n";
        return $this;
    }

    public function addQrCode(string $data, int $moduleSize = 4): self
    {
        $data = trim($data);
        if ($data === '') {
            return $this;
        }

        $moduleSize = max(3, min(8, $moduleSize));
        $len = strlen($data) + 3;
        $pL = chr($len & 0xFF);
        $pH = chr(($len >> 8) & 0xFF);

        $this->buffer .= self::ALIGN_CENTER;
        $this->buffer .= "\x1D\x28\x6B\x04\x00\x31\x41\x32\x00";
        $this->buffer .= "\x1D\x28\x6B\x03\x00\x31\x43" . chr($moduleSize);
        $this->buffer .= "\x1D\x28\x6B\x03\x00\x31\x45\x31";
        $this->buffer .= "\x1D\x28\x6B" . $pL . $pH . "\x31\x50\x30" . $data;
        $this->buffer .= "\x1D\x28\x6B\x03\x00\x31\x51\x30\n";
        $this->buffer .= self::ALIGN_LEFT;

        return $this;
    }

    public function addFooter(string $footerMessage): self
    {
        $this->buffer .= self::ALIGN_CENTER;
        $this->setFontB();
        foreach (explode("\n", trim($footerMessage)) as $line) {
            $clean = $this->sanitizeAscii($line);
            if ($clean !== '') {
                $this->buffer .= $clean . "\n";
            }
        }
        $this->buffer .= "\n\n\n";
        return $this;
    }

    public function cut(): self
    {
        $this->buffer .= self::CUT_FULL;
        return $this;
    }

    public function getRawPayload(): string
    {
        return $this->buffer;
    }

    /**
     * Dispatch the current ESC/POS buffer to the configured YHD-8390 interface (LAN or USB).
     *
     * @param array{interface?:string,ip?:string,port?:int,usb_path?:string,open_drawer?:bool} $options
     */
    public function dispatchBuffer(int $saleId = 0, array $options = []): bool
    {
        if (!empty($options['open_drawer'])) {
            $this->openCashDrawer();
        }

        $interface = strtolower(trim((string)($options['interface'] ?? $this->settings['printer_interface'] ?? 'lan')));
        if ($interface === 'usb') {
            $usbPath = trim((string)($options['usb_path'] ?? $this->settings['printer_usb_path'] ?? '/dev/usb/lp0'));
            $ok = $this->sendToUsb($usbPath);
        } else {
            $ip = trim((string)($options['ip'] ?? $this->settings['printer_lan_ip'] ?? '192.168.1.200'));
            $port = (int)($options['port'] ?? $this->settings['printer_lan_port'] ?? 9100);
            $ok = $this->sendToLan($ip, $port > 0 ? $port : 9100);
        }

        if ($ok && $saleId > 0) {
            try {
                TakeOrder::markPrinted($this->pdo, $saleId);
            } catch (Throwable $e) {
            }
            Activity::log($this->pdo, 'receipt_print', 'ok', [
                'subject' => 'Sale #' . $saleId,
                'detail' => 'Printed on YHD-8390 (' . strtoupper($interface) . ' Font B)',
            ]);
        }

        return $ok;
    }

    /**
     * @param array{interface?:string,ip?:string,port?:int,usb_path?:string,open_drawer?:bool,is_bill?:bool,items?:list<int>} $options
     */
    public function printSale(int $saleId, array $options = []): bool
    {
        $isBill = !empty($options['is_bill']);
        $specificItems = is_array($options['items'] ?? null) ? $options['items'] : [];
        $this->fromSaleId($saleId, $isBill, $specificItems);
        return $this->dispatchBuffer($saleId, $options);
    }

    public function sendToLan(string $ipAddress, int $port = 9100, int $timeout = 4): bool
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            throw new RuntimeException('Printer LAN IP address is not configured.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ipAddress, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new RuntimeException("Cannot connect to YHD-8390 LAN printer at {$ipAddress}:{$port} - {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);
        $written = @fwrite($socket, $this->buffer);
        @fclose($socket);

        if ($written === false || $written === 0) {
            throw new RuntimeException("Failed writing ESC/POS payload to YHD-8390 at {$ipAddress}:{$port}.");
        }

        return true;
    }

    public function sendToUsb(string $devicePath = '/dev/usb/lp0'): bool
    {
        $devicePath = trim($devicePath);
        if ($devicePath === '' || !file_exists($devicePath) || !is_writable($devicePath)) {
            throw new RuntimeException("USB printer device {$devicePath} is not accessible or writable.");
        }

        $bytes = @file_put_contents($devicePath, $this->buffer);
        if ($bytes === false) {
            throw new RuntimeException("Failed writing ESC/POS payload to USB device {$devicePath}.");
        }

        return true;
    }

    /**
     * Return CSS styles to enforce Shoprite-style Font B on app/Views/receipt.php.
     */
    public static function getBrowserFontBCss(): string
    {
        return "
            @page { size: 80mm auto; margin: 0mm; }
            @media print {
                html, body {
                    width: 80mm !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    color: #000000 !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
            }
            body, .receipt-section.receipt-font-b {
                width: 72mm;
                max-width: 72mm;
                margin: 0 auto;
                padding: 2mm 0;
                font-family: 'FontB11', 'FontB21', 'Lucida Console', 'Consolas', 'Liberation Mono', 'Courier New', monospace !important;
                font-size: 8.5pt !important;
                line-height: 1.15 !important;
                letter-spacing: -0.25px !important;
                color: #000000 !important;
            }
            .receipt-section.receipt-font-b p,
            .receipt-section.receipt-font-b div,
            .receipt-section.receipt-font-b span,
            .receipt-section.receipt-font-b table,
            .receipt-section.receipt-font-b th,
            .receipt-section.receipt-font-b td {
                font-family: inherit !important;
                font-size: 8.5pt !important;
                line-height: 1.15 !important;
                letter-spacing: -0.25px !important;
            }
            .receipt-section.receipt-font-b .receipt-header-title {
                font-family: 'FontA11', 'Lucida Console', 'Consolas', 'Courier New', monospace !important;
                font-size: 12.5pt !important;
                font-weight: 700 !important;
                letter-spacing: 0 !important;
                line-height: 1.2 !important;
            }
            .receipt-section.receipt-font-b .receipt-copy-badge {
                font-family: 'FontA11', 'Lucida Console', 'Consolas', 'Courier New', monospace !important;
                font-size: 10.5pt !important;
                font-weight: 700 !important;
                letter-spacing: 0 !important;
            }
            .receipt-section.receipt-font-b tr.receipt-total-row td {
                font-family: 'FontA11', 'Lucida Console', 'Consolas', 'Courier New', monospace !important;
                font-size: 11pt !important;
                font-weight: 700 !important;
                letter-spacing: 0 !important;
                padding-top: 2px !important;
            }
        ";
    }

    private function divider(string $char = '-'): string
    {
        return str_repeat(substr($char, 0, 1) ?: '-', $this->fontBColumns);
    }

    private function twoColumnLine(string $left, string $right): string
    {
        $left = $this->sanitizeAscii($left);
        $right = $this->sanitizeAscii($right);
        $space = $this->fontBColumns - strlen($left) - strlen($right);
        if ($space < 1) {
            $maxLeft = max(1, $this->fontBColumns - strlen($right) - 1);
            $left = substr($left, 0, $maxLeft);
            $space = max(1, $this->fontBColumns - strlen($left) - strlen($right));
        }
        return $left . str_repeat(' ', $space) . $right;
    }

    private function twoColumnLineFontA(string $left, string $right): string
    {
        $left = $this->sanitizeAscii($left);
        $right = $this->sanitizeAscii($right);
        $space = $this->fontAColumns - strlen($left) - strlen($right);
        if ($space < 1) {
            $maxLeft = max(1, $this->fontAColumns - strlen($right) - 1);
            $left = substr($left, 0, $maxLeft);
            $space = max(1, $this->fontAColumns - strlen($left) - strlen($right));
        }
        return $left . str_repeat(' ', $space) . $right;
    }

    private function sanitizeAscii(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
