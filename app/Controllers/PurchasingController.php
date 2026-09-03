<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\ReceivingBay;
use App\Models\Vendor;

final class PurchasingController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        Purchase::ensureSchema();
        $tab = (string)($_GET['tab'] ?? 'inquiry');
        if (!in_array($tab, ['inquiry', 'orders', 'received'], true)) {
            $tab = 'inquiry';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['save_inquiry'])) {
                    $id = Purchase::saveInquiry(
                        !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null,
                        !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                        (int)$_POST['location_id'],
                        $this->userId(),
                        (string)($_POST['status'] ?? 'draft'),
                        (string)($_POST['needed_by'] ?? ''),
                        trim((string)($_POST['notes'] ?? '')),
                        $_POST['items'] ?? []
                    );
                    $this->flash('success', 'Purchase inquiry #' . $id . ' saved.');
                }
                if (isset($_POST['convert_inquiry'])) {
                    $orderId = Purchase::convertInquiry((int)$_POST['inquiry_id'], $this->userId());
                    $this->flash('success', 'Inquiry converted to purchase order #' . $orderId . '.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['cancel_inquiry'])) {
                    Purchase::setInquiryStatus((int)$_POST['inquiry_id'], 'cancelled');
                    $this->flash('success', 'Inquiry cancelled.');
                }
                if (isset($_POST['save_order'])) {
                    $id = Purchase::saveOrder(
                        !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null,
                        !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null,
                        (int)($_POST['vendor_id'] ?? 0),
                        (int)$_POST['location_id'],
                        $this->userId(),
                        trim((string)($_POST['reference_no'] ?? '')),
                        trim((string)($_POST['notes'] ?? '')),
                        $_POST['items'] ?? []
                    );
                    $this->flash('success', 'Purchase order #' . $id . ' saved.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['cancel_order'])) {
                    Purchase::cancelOrder((int)$_POST['order_id']);
                    $this->flash('success', 'Purchase order cancelled.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['process_grv'])) {
                    $items = $_POST['items'] ?? [];
                    $locId = (int)($_POST['location_id'] ?? 0);
                    $vendorId = (int)($_POST['vendor_id'] ?? 0);
                    $orderId = !empty($_POST['purchase_order_id']) ? (int)$_POST['purchase_order_id'] : null;
                    if ($locId <= 0 || $vendorId <= 0) {
                        throw new \Exception('Select a vendor and receiving location.');
                    }
                    $grvId = Purchase::receive(
                        $vendorId,
                        $locId,
                        $this->userId(),
                        (string)($_POST['ref_number'] ?? ''),
                        $items,
                        $orderId
                    );
                    $dockId = (int)($_POST['dock_id'] ?? 0);
                    if ($dockId > 0) {
                        ReceivingBay::markReceived($dockId, $grvId, $this->userId(), $locId);
                    }
                    $msg = 'Goods received (GRV #' . $grvId . ').';
                    try {
                        $zraRes = (new \ZraVsdcClient($this->pdo()))->syncGrv($grvId);
                        if (($zraRes['resultCd'] ?? '') === '000') {
                            $msg .= ' Posted to ZRA.';
                        }
                    } catch (\Throwable $e) {
                    }
                    $this->flash('success', $msg);
                    $this->redirect('purchasing', ['tab' => 'received']);
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $q = ['tab' => $tab];
            if (isset($_POST['process_grv']) && !empty($_POST['dock_id'])) {
                $q['dock_id'] = (int)$_POST['dock_id'];
                if (!empty($_POST['location_id'])) {
                    $q['location_id'] = (int)$_POST['location_id'];
                }
            }
            $this->redirect('purchasing', $q);
        }

        $editInquiry = !empty($_GET['inquiry_id']) ? Purchase::inquiry((int)$_GET['inquiry_id']) : null;
        $editOrder = !empty($_GET['order_id']) ? Purchase::order((int)$_GET['order_id']) : null;
        $receiveOrder = !empty($_GET['receive_order']) ? Purchase::order((int)$_GET['receive_order']) : $editOrder;

        $bayPrefill = null;
        $dockId = (int)($_GET['dock_id'] ?? 0);
        if ($tab === 'received' && $dockId > 0) {
            $dock = ReceivingBay::find($dockId);
            if ($dock && ($dock['status'] ?? '') === 'docked') {
                $locId = (int)($_GET['location_id'] ?? $dock['intended_location_id'] ?? 0);
                try {
                    if ($locId > 0) {
                        ReceivingBay::registerCustomItems($dockId, $locId);
                    }
                    $bayPrefill = ReceivingBay::grvPrefill($dockId, $locId ?: null);
                } catch (\Throwable $e) {
                    $this->flash('error', $e->getMessage());
                }
            }
        }

        $this->render([
            'tab' => $tab,
            'inquiries' => Purchase::inquiries(),
            'orders' => Purchase::orders(),
            'openOrders' => Purchase::openOrders(),
            'editInquiry' => $editInquiry,
            'editOrder' => $editOrder,
            'receiveOrder' => $receiveOrder,
            'bayPrefill' => $bayPrefill,
            'vendors' => Vendor::active(),
            'locations' => Location::all(),
            'products' => Product::activeItems(),
            'currentLocId' => $this->locationId(),
        ]);
    }
}
