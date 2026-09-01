<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;

final class InventoryController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev', 'chef', 'head_chef']);
        Inventory::ensureReasonColumn();

        $locFilter = $_GET['location_id'] ?? '';
        $catFilter = $_GET['category_id'] ?? '';
        $search = trim((string)($_GET['search'] ?? ''));
        $chefViewOnly = in_array($this->role(), ['chef', 'head_chef'], true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_stock_take'])) {
            if ($chefViewOnly) {
                $this->flash('error', 'Access Denied: Chefs have View-Only access to stock levels.');
                $this->redirect('inventory');
            }
            try {
                $count = Inventory::applyStockTake($_POST, $this->userId());
                $this->flash('success', "Stock Take Complete! $count items updated.");
            } catch (\Throwable $e) {
                $this->flash('error', 'Database Error: ' . $e->getMessage());
            }
            $this->redirect('inventory');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
            if ($chefViewOnly) {
                $this->flash('error', 'Access Denied.');
                $this->redirect('inventory');
            }
            try {
                Inventory::updateQtyAndPrice((int)$_POST['inventory_id'], (float)$_POST['quantity'], (float)$_POST['price']);
                $this->flash('success', 'Inventory updated successfully.');
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('inventory');
        }

        $inventory = Inventory::filtered($locFilter, $catFilter, $search);
        $totalValue = 0.0;
        foreach ($inventory as $item) {
            $totalValue += (float)$item['stock_value'];
        }

        $this->render([
            'locFilter' => $locFilter,
            'catFilter' => $catFilter,
            'search' => $search,
            'locations' => Location::names(),
            'categories' => Category::all(),
            'inventory' => $inventory,
            'totalValue' => $totalValue,
        ]);
    }
}
