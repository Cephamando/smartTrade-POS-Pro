<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\ReceivingBay;

final class ReceivingBayController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        ReceivingBay::ensureSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['send_to_grv'])) {
                    $dockId = (int)($_POST['dock_id'] ?? 0);
                    $locId = (int)($_POST['location_id'] ?? 0);
                    if ($locId <= 0) {
                        throw new \Exception('Select the warehouse or kitchen this consignment will be received into.');
                    }
                    ReceivingBay::applyLineEdits($dockId, $_POST['items'] ?? []);
                    ReceivingBay::registerCustomItems($dockId, $locId);
                    $this->flash('success', 'Consignment sent to Goods Received. Review the lines and process the GRV.');
                    $this->redirect('purchasing', [
                        'tab' => 'received',
                        'dock_id' => $dockId,
                        'location_id' => $locId,
                    ]);
                }
                if (isset($_POST['cancel_dock'])) {
                    ReceivingBay::cancel((int)$_POST['dock_id']);
                    $this->flash('success', 'Consignment returned from the bay. It was not added to inventory.');
                    $this->redirect('receiving_bay');
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('receiving_bay', !empty($_POST['dock_id']) ? ['id' => (int)$_POST['dock_id']] : []);
        }

        $openId = (int)($_GET['id'] ?? 0);
        $open = $openId > 0 ? ReceivingBay::find($openId) : null;
        $this->render([
            'docks' => ReceivingBay::list(),
            'dockedCount' => ReceivingBay::dockedCount(),
            'open' => $open,
            'openItems' => $open ? ReceivingBay::items((int)$open['id']) : [],
            'locations' => Location::vendorReceiveSites(),
            'currentLocId' => $this->locationId(),
        ]);
    }
}
