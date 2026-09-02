<?php

namespace App\Controllers;

use App\Helpers\Movement;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;

final class KitchenController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'manager', 'dev', 'chef', 'head_chef'], true)) {
            $this->flash('error', 'Only kitchen staff and managers can open Meal Production.');
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produce_item'])) {
            $productId = (int)$_POST['product_id'];
            $qty = (float)$_POST['quantity'];
            $targetLocId = (int)$_POST['target_location_id'];
            if ($qty > 0 && $targetLocId > 0) {
                $check = Movement::produceTo(
                    Product::movementContext($productId),
                    Location::find($targetLocId)
                );
                if (!$check['ok']) {
                    $this->flash('error', $check['reason']);
                } else {
                    try {
                        Inventory::ensureReasonColumn();
                        Inventory::produce($productId, $qty, $targetLocId, $this->userId(), $this->locationId());
                        $this->flash('success', 'Batch sent to POS. Recipe ingredients deducted from kitchen stock.');
                    } catch (\Throwable $e) {
                        $this->flash('error', 'Unable to record production.');
                    }
                }
            }
            $this->redirect('kitchen');
        }

        $this->render([
            'meals' => Product::meals(),
            'sellableLocs' => Location::sellable(),
        ]);
    }
}
