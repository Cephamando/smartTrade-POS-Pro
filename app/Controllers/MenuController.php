<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Unspsc;

final class MenuController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev', 'head_chef', 'chef']);
        Unspsc::ensureTable();

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'unspsc_search') {
            $this->jsonExit(['results' => Unspsc::search((string)($_GET['q'] ?? ''))]);
        }

        $role = $this->role();

        $types = ($role === 'chef')
            ? ['food', 'meal']
            : ['food', 'meal', 'drink', 'beverage', 'cocktail'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu_item'])) {
            try {
                $id = $_POST['item_id'] ?? '';
                $productId = Product::saveMenuItem(
                    $id ? (int)$id : null,
                    trim((string)$_POST['name']),
                    (float)$_POST['price'],
                    (float)($_POST['cost_price'] ?? 0),
                    (int)$_POST['category_id'],
                    (string)($_POST['unspsc_code'] ?? '')
                );
                $this->flash('success', $id ? 'Menu item updated.' : 'Menu item created.');
                $this->maybeRegisterZra($productId);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to save the menu item.');
            }
            $this->redirect('menu');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recipe'])) {
            try {
                $parentId = (int)$_POST['parent_product_id'];
                Recipe::save($parentId, $_POST['ingredient_id'] ?? [], $_POST['ingredient_qty'] ?? []);
                $code = trim((string)($_POST['unspsc_code'] ?? ''));
                if ($code !== '') {
                    Unspsc::assignToProduct($parentId, $code);
                }
                $this->flash('success', 'Recipe saved.');
                $this->maybeRegisterZra($parentId);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to save the recipe.');
            }
            $this->redirect('menu');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
            Product::deactivate((int)$_POST['item_id']);
            $this->flash('success', 'Item removed from menu.');
            $this->redirect('menu');
        }

        $this->render([
            'foodCategories' => Category::byTypes($types),
            'menuItems' => Product::menuItems($types),
            'allIngredients' => Product::allIngredients(),
            'recipesGrouped' => Recipe::grouped(),
        ]);
    }

    private function maybeRegisterZra(int $productId): void
    {
        if (empty($_POST['register_zra']) || $productId <= 0) {
            return;
        }
        $tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
        if (!in_array($tier, ['enterprise'], true) && $this->role() !== 'dev') {
            return;
        }
        try {
            $product = Product::movementContext($productId);
            if (!$product) {
                return;
            }
            if (empty($product['unspsc_code'])) {
                $this->flash('error', 'Assign a UNSPSC code before registering the item with ZRA.');
                return;
            }
            $res = (new \ZraVsdcClient($this->pdo()))->saveItem($product);
            if (($res['resultCd'] ?? '') === '000') {
                $this->flash('success', 'Saved and registered with ZRA Smart Invoice.');
            } else {
                $this->flash('error', $res['resultMsg'] ?? 'ZRA registration did not complete.');
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'Saved locally, but ZRA registration failed: ' . $e->getMessage());
        }
    }
}
