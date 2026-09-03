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
        $clamped = Inventory::clampNegatives($this->userId());
        if ($clamped > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->flash('info', "$clamped item(s) with negative stock were reset to 0.");
        }

        $locFilter = $_GET['location_id'] ?? '';
        $catFilter = $_GET['category_id'] ?? '';
        $search = trim((string)($_GET['search'] ?? ''));
        $chefViewOnly = in_array($this->role(), ['chef', 'head_chef'], true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_stock_take'])) {
            if ($chefViewOnly) {
                $this->flash('error', 'Kitchen staff have view-only access to stock.');
                $this->redirect('inventory');
            }
            try {
                $count = Inventory::applyStockTake($_POST, $this->userId());
                $this->flash('success', "Stock take complete. $count item(s) adjusted against live stock.");
            } catch (\Throwable $e) {
                $this->flash('error', 'Unable to apply the stock take.');
            }
            $this->redirect('inventory');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
            if ($chefViewOnly) {
                $this->flash('error', 'You do not have permission to update stock.');
                $this->redirect('inventory');
            }
            try {
                Inventory::updateQtyAndPrice((int)$_POST['inventory_id'], (float)$_POST['quantity'], (float)$_POST['price']);
                $this->flash('success', 'Inventory updated.');
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
