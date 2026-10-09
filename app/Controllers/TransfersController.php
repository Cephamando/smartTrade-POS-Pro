<?php

namespace App\Controllers;

use App\Helpers\Movement;
use App\Helpers\Vsias;
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
                $sourceId = (int)($_POST['source_location_id'] ?? 0);
                $destId = (int)($_POST['dest_location_id'] ?? 0);
                $productId = (int)($_POST['product_id'] ?? 0);
                $qty = (float)($_POST['quantity'] ?? 0);
                if ($sourceId <= 0 || $destId <= 0 || $productId <= 0) {
                    $this->flash('error', 'Choose the product, where it comes from, and where it is going.');
                    $this->redirect('transfers');
                }
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
            $vsiasOn = Vsias::enabled();
            $bulk = (string)($_POST['bulk_action'] ?? '');
            if (!$vsiasOn && (isset($_POST['dispatch_transfer']) || $bulk === 'dispatch')) {
                $ids = isset($_POST['dispatch_transfer']) ? [(int)$_POST['transfer_id']] : $this->postedIds();
                $ok = 0;
                $blocked = 0;
                foreach ($ids as $id) {
                    $result = Transfer::dispatch($id, $userId);
                    if ($result === 'ok') {
                        $ok++;
                    } else {
                        $blocked++;
                    }
                }
                $this->flash($ok ? 'success' : 'error', $ok . ' dispatched.' . ($blocked ? ' ' . $blocked . ' could not leave (missing or not enough stock).' : ''));
            } elseif (!$vsiasOn && (isset($_POST['receive_transfer']) || $bulk === 'receive')) {
                $ids = isset($_POST['receive_transfer']) ? [(int)$_POST['transfer_id']] : $this->postedIds();
                foreach ($ids as $id) {
                    Transfer::receive($id, $userId);
                }
                $this->flash('success', count($ids) . ' transfer' . (count($ids) === 1 ? '' : 's') . ' accepted into stock.');
            } elseif ($vsiasOn && (isset($_POST['dispatch_transfer']) || isset($_POST['receive_transfer']) || $bulk === 'dispatch' || $bulk === 'receive')) {
                $this->flash('error', 'Dispatch and receive must go through V-SIAS inspection. Stock moves only after store approval.');
            }
            if (isset($_POST['cancel_transfer']) || $bulk === 'cancel') {
                $ids = isset($_POST['cancel_transfer']) ? [(int)$_POST['transfer_id']] : $this->postedIds();
                foreach ($ids as $id) {
                    Transfer::cancel($id);
                }
                $this->flash('success', count($ids) . ' request' . (count($ids) === 1 ? '' : 's') . ' cancelled.');
            }
            $this->redirect('transfers');
        }

        $this->render([
            'pendingDispatch' => Transfer::listByStatus('pending', $userRole, $userLoc, 'source_location_id'),
            'incomingStock' => Transfer::listByStatus('in_transit', $userRole, $userLoc, 'dest_location_id'),
            'myRequests' => Transfer::listByStatus('pending', $userRole, $userLoc, 'dest_location_id'),
            'locations' => Location::all(),
            'products' => Product::activeItems(),
            'transferTab' => in_array((string)($_GET['tab'] ?? ''), ['incoming', 'outgoing', 'requests'], true)
                ? (string)$_GET['tab']
                : 'incoming',
        ]);
    }
}
