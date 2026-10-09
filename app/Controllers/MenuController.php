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
                $notice = $this->maybeRegisterZra($productId);
                $message = $notice['message'] ?? ($id ? 'Menu item updated.' : 'Menu item created.');
                $type = $notice['type'] ?? 'success';
                if ($this->wantsAjax()) {
                    $this->respondMenu($productId, $message, $type === 'error' ? 'warning' : 'success');
                }
                $this->flash($type, $message);
            } catch (\Throwable $e) {
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to save the menu item.'], 400);
                }
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
                $notice = $this->maybeRegisterZra($parentId);
                $message = $notice['message'] ?? 'Recipe saved.';
                $type = $notice['type'] ?? 'success';
                if ($this->wantsAjax()) {
                    $this->respondMenu($parentId, $message, $type === 'error' ? 'warning' : 'success', Recipe::forParent($parentId));
                }
                $this->flash($type, $message);
            } catch (\Throwable $e) {
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to save the recipe.'], 400);
                }
                $this->flash('error', $e->getMessage() ?: 'Unable to save the recipe.');
            }
            $this->redirect('menu');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['bulk_action'] ?? '') === 'remove')) {
            $ids = $this->postedIds();
            foreach ($ids as $id) {
                Product::deactivate($id);
            }
            $this->flash('success', count($ids) . ' item' . (count($ids) === 1 ? '' : 's') . ' removed from the menu.');
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

    private function maybeRegisterZra(int $productId): ?array
    {
        if (empty($_POST['register_zra']) || $productId <= 0) {
            return null;
        }
        $tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
        if (!in_array($tier, ['enterprise'], true) && $this->role() !== 'dev') {
            return null;
        }
        try {
            $product = Product::movementContext($productId);
            if (!$product) {
                return null;
            }
            if (empty($product['unspsc_code'])) {
                return ['type' => 'error', 'message' => 'Assign a UNSPSC code before registering the item with ZRA.'];
            }
            $res = (new \ZraVsdcClient($this->pdo()))->saveItem($product);
            if (($res['resultCd'] ?? '') === '000') {
                return ['type' => 'success', 'message' => 'Saved and registered with ZRA Smart Invoice.'];
            }
            return ['type' => 'error', 'message' => $res['resultMsg'] ?? 'ZRA registration did not complete.'];
        } catch (\Throwable $e) {
            return ['type' => 'error', 'message' => 'Saved locally, but ZRA registration failed: ' . $e->getMessage()];
        }
    }

    private function respondMenu(int $id, string $message, string $icon, ?array $recipes = null): never
    {
        $row = Product::menuRow($id);
        if (!$row) {
            $this->jsonExit(['status' => 'error', 'msg' => 'Saved, but the menu row could not be loaded.'], 500);
        }
        $payload = [
            'status' => 'success',
            'icon' => $icon,
            'message' => $message,
            'id' => $id,
            'html' => $this->renderPartial('menu_row', [
                'm' => $row,
                'hasRecipe' => ((int)($row['recipe_count'] ?? 0)) > 0,
            ]),
        ];
        if ($recipes !== null) {
            $payload['recipes'] = $recipes;
        }
        $this->jsonExit($payload);
    }
}
