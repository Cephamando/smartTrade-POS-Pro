<?php

namespace App\Controllers;

use App\Models\Grv;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;

final class ReceiveStockController extends Controller
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_grv'])) {
            $locId = $_POST['location_id'] ?? null;
            $vendorId = $_POST['vendor_id'] ?? null;
            $items = $_POST['items'] ?? [];
            if (empty($items) || empty($locId)) {
                $this->flash('warning', 'Please select a location and add at least one item.');
            } elseif (empty($vendorId)) {
                $this->flash('warning', 'Please select a Vendor.');
            } else {
                try {
                    $grvId = Grv::process((int)$vendorId, (int)$locId, $this->userId(), (string)($_POST['ref_number'] ?? ''), $items);
                    $this->flash('success', "Stock Received Successfully (GRV #$grvId).");
                    try {
                        $zraRes = (new \ZraVsdcClient($this->pdo()))->syncGrv($grvId);
                        if (($zraRes['resultCd'] ?? '') === '000') {
                            $_SESSION['swal_msg'] .= ' Stock posted to ZRA.';
                        } elseif (($zraRes['resultCd'] ?? '') !== 'skipped') {
                            $_SESSION['swal_msg'] .= ' ZRA stock pending: ' . ($zraRes['resultMsg'] ?? 'VSDC unavailable');
                        }
                    } catch (\Throwable $e) {
                        $_SESSION['swal_msg'] .= ' ZRA stock pending: ' . $e->getMessage();
                    }
                } catch (\Throwable $e) {
                    $this->flash('error', 'Database Error: ' . $e->getMessage());
                }
            }
        }

        $this->render([
            'locations' => Location::all(),
            'vendors' => Vendor::active(),
            'products' => Product::activeSellable(),
            'currentLocId' => $this->locationId(),
        ]);
    }
}
