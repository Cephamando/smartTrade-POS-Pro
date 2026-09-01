<?php

namespace App\Controllers;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transfer;

final class TransfersController extends Controller
{
    public function index(): void
    {
        if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_stock_level') {
            error_reporting(0);
            while (ob_get_level()) {
                ob_end_clean();
            }
            $this->jsonExit([
                'stock' => Inventory::quantity((int)($_GET['product_id'] ?? 0), (int)($_GET['location_id'] ?? 0)),
            ]);
        }

        $userId = $this->userId();
        $userLoc = (int)$_SESSION['location_id'];
        $userRole = $this->role();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['create_request'])) {
                $sourceId = $_POST['source_location_id'];
                $destId = $_POST['dest_location_id'];
                $qty = (float)$_POST['quantity'];
                if ($qty <= 0) {
                    $this->flash('error', 'Quantity must be greater than 0.');
                } elseif ($sourceId == $destId) {
                    $this->flash('error', 'Source and Destination cannot be the same.');
                } else {
                    Transfer::createRequest((int)$sourceId, (int)$destId, (int)$_POST['product_id'], $qty, $userId);
                    $this->flash('success', 'Requisition sent! Waiting for Dispatch.');
                }
            }
            if (isset($_POST['dispatch_transfer'])) {
                $result = Transfer::dispatch((int)$_POST['transfer_id'], $userId);
                if ($result === 'stock') {
                    $this->flash('error', 'Insufficient stock at source.');
                } elseif ($result === 'ok') {
                    $this->flash('success', 'Stock Dispatched!');
                }
            }
            if (isset($_POST['receive_transfer'])) {
                Transfer::receive((int)$_POST['transfer_id'], $userId);
                $this->flash('success', 'Stock Received!');
            }
            if (isset($_POST['cancel_transfer'])) {
                Transfer::cancel((int)$_POST['transfer_id']);
                $this->flash('success', 'Request cancelled.');
            }
            $this->redirect('transfers');
        }

        $this->render([
            'pendingDispatch' => Transfer::listByStatus('pending', $userRole, $userLoc, 'source_location_id'),
            'incomingStock' => Transfer::listByStatus('in_transit', $userRole, $userLoc, 'dest_location_id'),
            'myRequests' => Transfer::listByStatus('pending', $userRole, $userLoc, 'dest_location_id'),
            'locations' => Location::all(),
            'products' => Product::activeItems(),
        ]);
    }
}
