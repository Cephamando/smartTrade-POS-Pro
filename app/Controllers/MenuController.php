<?php

namespace App\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;

final class MenuController extends Controller
{
    public function index(): void
    {
        $role = $this->role();
        if (!in_array($role, ['admin', 'manager', 'dev', 'head_chef', 'chef'], true)) {
            die('<h1>Access Denied</h1><p>Only Chefs and Management can edit the Menu.</p>');
        }

        $types = ($role === 'chef')
            ? ['food', 'meal']
            : ['food', 'meal', 'drink', 'beverage', 'cocktail'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu_item'])) {
            try {
                $id = $_POST['item_id'] ?? '';
                Product::saveMenuItem(
                    $id ? (int)$id : null,
                    trim((string)$_POST['name']),
                    (float)$_POST['price'],
                    (float)($_POST['cost_price'] ?? 0),
                    (int)$_POST['category_id']
                );
                $this->flash('success', $id ? 'Menu item updated.' : 'Menu item created.');
            } catch (\Throwable $e) {
                $this->flash('error', 'Error: ' . $e->getMessage());
            }
            $this->redirect('menu');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recipe'])) {
            try {
                Recipe::save((int)$_POST['parent_product_id'], $_POST['ingredient_id'] ?? [], $_POST['ingredient_qty'] ?? []);
                $this->flash('success', 'Recipe saved successfully.');
            } catch (\Throwable $e) {
                $this->flash('error', 'Database Error: ' . $e->getMessage());
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
}
