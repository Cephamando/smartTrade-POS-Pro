<?php

namespace App\Controllers;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;

final class KitchenController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'manager', 'dev', 'chef', 'head_chef'], true)) {
            die('<h1>Access Denied</h1><p>Only kitchen staff and managers can access Production.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produce_item'])) {
            $productId = (int)$_POST['product_id'];
            $qty = (float)$_POST['quantity'];
            $targetLocId = (int)$_POST['target_location_id'];
            if ($qty > 0 && $targetLocId > 0) {
                try {
                    Inventory::produce($productId, $qty, $targetLocId, $this->userId());
                    $this->flash('success', 'Batch sent to POS!');
                } catch (\Throwable $e) {
                    $this->flash('error', 'Database Error: ' . $e->getMessage());
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
