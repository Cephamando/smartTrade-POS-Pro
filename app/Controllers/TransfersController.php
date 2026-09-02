<?php

namespace App\Controllers;

use App\Helpers\Movement;
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
                $sourceId = (int)$_POST['source_location_id'];
                $destId = (int)$_POST['dest_location_id'];
                $productId = (int)$_POST['product_id'];
                $qty = (float)$_POST['quantity'];
                $check = Movement::transfer(
                    Product::movementContext($productId),
                    Location::find($sourceId),
                    Location::find($destId)
                );
                if (!$check['ok']) {
                    $this->flash('error', $check['reason']);
                } elseif ($qty <= 0) {
                    $this->flash('error', 'Quantity must be greater than zero.');
                } else {
                    Transfer::createRequest($sourceId, $destId, $productId, $qty, $userId);
                    $this->flash('success', 'Requisition sent. Awaiting dispatch.');
                }
            }
            if (isset($_POST['dispatch_transfer'])) {
                $pending = Transfer::find((int)$_POST['transfer_id']);
                if ($pending) {
                    $check = Movement::transfer(
                        Product::movementContext((int)$pending['product_id']),
                        Location::find((int)$pending['source_location_id']),
                        Location::find((int)$pending['dest_location_id'])
                    );
                    if (!$check['ok']) {
                        $this->flash('error', $check['reason']);
                        $this->redirect('transfers');
                    }
                }
                $result = Transfer::dispatch((int)$_POST['transfer_id'], $userId);
                if ($result === 'stock') {
                    $this->flash('error', 'Insufficient stock at the source location.');
                } elseif ($result === 'ok') {
                    $this->flash('success', 'Stock dispatched.');
                }
            }
            if (isset($_POST['receive_transfer'])) {
                Transfer::receive((int)$_POST['transfer_id'], $userId);
                $this->flash('success', 'Stock received.');
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
