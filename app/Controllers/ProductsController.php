<?php

namespace App\Controllers;

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

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            Product::exportCsv();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['add_category'])) {
                    $name = trim((string)$_POST['category_name']);
                    if ($name === '') {
                        throw new \Exception('Category name is required.');
                    }
                    Category::create($name);
                    $this->flash('success', "Category '$name' created.");
                }
                if (isset($_POST['save_product'])) {
                    $name = trim((string)$_POST['name']);
                    if (Product::nameExists($name)) {
                        throw new \Exception('A product with this name already exists.');
                    }
                    $skuRaw = $_POST['sku'] ?? '';
                    $sku = trim((string)$skuRaw);
                    $sku = $sku === '' ? null : $sku;
                    Product::create(
                        $name,
                        !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                        !empty($_POST['price']) ? $_POST['price'] : 0,
                        !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0,
                        !empty($_POST['unit']) ? (string)$_POST['unit'] : 'unit',
                        $sku,
                        !empty($_POST['tax_class']) ? (string)$_POST['tax_class'] : 'A',
                        !empty($_POST['unspsc_code']) ? trim((string)$_POST['unspsc_code']) : null,
                        $userLocId,
                        (string)($_POST['type'] ?? 'item')
                    );
                    $this->flash('success', "Product '$name' saved.");
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
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('products');
        }

        $this->render([
            'categories' => Category::all(),
            'products' => Product::allWithCategory(),
        ]);
    }
}
