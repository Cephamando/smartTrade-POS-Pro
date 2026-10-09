<?php

namespace App\Controllers;

use App\Helpers\Media;
use App\Models\Category;
use App\Models\Product;

final class ProductsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $userLocId = (int)($_SESSION['location_id'] ?? 0);
        if (!$userLocId) {
            $this->flash('error', 'No location is assigned to this account.');
            $this->redirect('dashboard');
        }

        // Ensure database is ready to handle the is_sellable column
        Product::ensureSchema();

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            Product::exportCsv();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['bulk_action'])) {
                    $ids = $this->postedIds();
                    if (!$ids) {
                        throw new \Exception('Select at least one product.');
                    }
                    $active = $_POST['bulk_action'] === 'activate' ? 1 : ($_POST['bulk_action'] === 'deactivate' ? 0 : null);
                    if ($active === null) {
                        throw new \Exception('That bulk action is not available.');
                    }
                    $stmt = $this->pdo()->prepare('UPDATE products SET is_active = ? WHERE id = ?');
                    foreach ($ids as $id) {
                        $stmt->execute([$active, $id]);
                    }
                    $this->flash('success', count($ids) . ' product' . (count($ids) === 1 ? '' : 's') . ($active ? ' activated.' : ' disabled.'));
                }
                if (isset($_POST['add_category'])) {
                    $name = trim((string)$_POST['category_name']);
                    if ($name === '') {
                        throw new \Exception('Category name is required.');
                    }
                    $categoryId = Category::create($name);
                    $message = "Category '$name' created.";
                    if ($this->wantsAjax()) {
                        $this->jsonExit([
                            'status' => 'success',
                            'message' => $message,
                            'category' => ['id' => $categoryId, 'name' => $name],
                        ]);
                    }
                    $this->flash('success', $message);
                }
                if (isset($_POST['save_product'])) {
                    $name = trim((string)$_POST['name']);
                    $productId = (int)($_POST['product_id'] ?? 0);
                    if ($name === '') {
                        throw new \Exception('Product name is required.');
                    }
                    if (Product::nameExists($name, $productId > 0 ? $productId : null)) {
                        throw new \Exception('A product with this name already exists.');
                    }
                    $skuRaw = $_POST['sku'] ?? '';
                    $sku = trim((string)$skuRaw);
                    $sku = $sku === '' ? null : $sku;
                    $categoryId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
                    $price = !empty($_POST['price']) ? $_POST['price'] : 0;
                    $cost = !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0;
                    $unit = !empty($_POST['unit']) ? (string)$_POST['unit'] : 'unit';
                    $taxClass = !empty($_POST['tax_class']) ? (string)$_POST['tax_class'] : 'A';
                    $unspsc = !empty($_POST['unspsc_code']) ? trim((string)$_POST['unspsc_code']) : null;
                    $type = (string)($_POST['type'] ?? 'item');
                    $isSellable = array_key_exists('is_sellable', $_POST) ? (int)$_POST['is_sellable'] : 1;
                    
                    $savedId = $productId;
                    if ($productId > 0) {
                        $existing = Product::findWithCategory($productId);
                        if (!$existing) {
                            throw new \Exception('Product not found.');
                        }
                        $isActive = array_key_exists('is_active', $_POST)
                            ? (int)$_POST['is_active']
                            : (int)($existing['is_active'] ?? 1);
                        Product::update(
                            $productId,
                            $name,
                            $categoryId,
                            $price,
                            $cost,
                            $unit,
                            $sku,
                            $taxClass,
                            $unspsc,
                            $type,
                            $isActive,
                            $isSellable
                        );
                        $message = "Product '$name' updated.";
                    } else {
                        $savedId = Product::create(
                            $name,
                            $categoryId,
                            $price,
                            $cost,
                            $unit,
                            $sku,
                            $taxClass,
                            $unspsc,
                            $userLocId,
                            $type,
                            $isSellable
                        );
                        $message = "Product '$name' saved.";
                    }
                    if (!empty($_POST['remove_image'])) {
                        Product::setImage($savedId, null);
                    } else {
                        $image = Media::store('image', 'library');
                        if ($image !== null) {
                            Product::setImage($savedId, $image);
                        }
                    }
                    if ($this->wantsAjax()) {
                        $this->jsonCatalogRow((int)$savedId, $message);
                    }
                    $this->flash('success', $message);
                }
                if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
                    if (($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new \Exception('Upload failed. Check the file and try again.');
                    }
                    $result = Product::importCsv(
                        $_FILES['csv_file']['tmp_name'],
                        $userLocId,
                        (string)($_FILES['csv_file']['name'] ?? '')
                    );
                    $this->flash('success', "Import complete. Added {$result['added']}, updated {$result['updated']}.");
                }
            } catch (\Throwable $e) {
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to save.'], 400);
                }
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('products');
        }

        $this->render([
            'categories' => Category::all(),
            'products' => Product::allWithCategory(),
        ]);
    }

    private function jsonCatalogRow(int $id, string $message): never
    {
        $row = Product::catalogRow($id);
        if (!$row) {
            $this->jsonExit(['status' => 'error', 'msg' => 'Saved, but the catalog row could not be loaded.'], 500);
        }
        $isEnt = (defined('LICENSE_TIER') ? LICENSE_TIER : 'lite') === 'enterprise';
        $this->jsonExit([
            'status' => 'success',
            'message' => $message,
            'id' => $id,
            'html' => $this->renderPartial('product_row', ['p' => $row, 'isEnt' => $isEnt]),
        ]);
    }
}
