<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\ReportNarrator;

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

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['explain_stock'])) {
            try {
                $locId = (string)($_POST['location_id'] ?? '');
                $catId = (string)($_POST['category_id'] ?? '');
                $term = trim((string)($_POST['search'] ?? ''));
                $rows = Inventory::filtered($locId, $catId, $term);
                $locName = '';
                foreach (Location::names() as $loc) {
                    if ((string)$loc['id'] === $locId) {
                        $locName = (string)$loc['name'];
                        break;
                    }
                }
                $catName = '';
                foreach (Category::all() as $cat) {
                    if ((string)$cat['id'] === $catId) {
                        $catName = (string)$cat['name'];
                        break;
                    }
                }
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->stockBrief($rows, [
                    'location' => $locName,
                    'category' => $catName,
                    'search' => $term,
                ]));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_stock_take'])) {
            if ($chefViewOnly) {
                $this->stockJsonOrRedirect('error', 'Kitchen staff have view-only access to stock.');
            }
            try {
                $result = Inventory::applyStockTake($_POST, $this->userId());
                $message = 'Stock take complete. ' . $result['count'] . ' item(s) adjusted against live stock.';
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'success', 'message' => $message, 'lines' => $result['lines']]);
                }
                $this->flash('success', $message);
            } catch (\Throwable $e) {
                $msg = $e instanceof \PDOException || $e->getMessage() === '' ? 'Unable to apply the stock take.' : $e->getMessage();
                $this->stockJsonOrRedirect('error', $msg);
            }
            $this->redirect('inventory');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
            if ($chefViewOnly) {
                $this->stockJsonOrRedirect('error', 'You do not have permission to update stock.');
            }
            try {
                $inventoryId = (int)$_POST['inventory_id'];
                Inventory::updateQtyAndPrice($inventoryId, (float)$_POST['quantity'], (float)$_POST['price']);
                $line = Inventory::findLine($inventoryId);
                if (!$line) {
                    throw new \RuntimeException('That stock row could not be found.');
                }
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'success', 'message' => 'Inventory updated.', 'line' => $line]);
                }
                $this->flash('success', 'Inventory updated.');
            } catch (\Throwable $e) {
                $msg = $e instanceof \PDOException || $e->getMessage() === '' ? 'Unable to update stock.' : $e->getMessage();
                $this->stockJsonOrRedirect('error', $msg);
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
            'explainReady' => Guide::ready(Setting::all()),
        ]);
    }

    private function stockJsonOrRedirect(string $status, string $message): never
    {
        if ($this->wantsAjax()) {
            $this->jsonExit(['status' => $status, 'msg' => $message], $status === 'success' ? 200 : 400);
        }
        $this->flash($status, $message);
        $this->redirect('inventory');
    }
}
