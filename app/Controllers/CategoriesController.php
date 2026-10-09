<?php

namespace App\Controllers;

use App\Models\Category;

final class CategoriesController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['bulk_action'] ?? '') === 'delete') {
                $deleted = 0;
                $blocked = 0;
                foreach ($this->postedIds() as $id) {
                    $result = Category::delete($id);
                    if ($result === 'products' || $result === 'children') {
                        $blocked++;
                    } else {
                        $deleted++;
                    }
                }
                $msg = $deleted . ' categor' . ($deleted === 1 ? 'y' : 'ies') . ' deleted.';
                if ($blocked) {
                    $msg .= ' ' . $blocked . ' still have products or subcategories and were kept.';
                }
                $this->flash($blocked && !$deleted ? 'error' : 'success', $msg);
                $this->redirect('categories');
            }
            if (isset($_POST['add_category'])) {
                $name = trim((string)($_POST['name'] ?? ''));
                $type = (string)($_POST['type'] ?? 'other');
                $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                if ($name !== '') {
                    if (Category::nameTaken($name)) {
                        $this->flash('error', "A category named '$name' already exists.");
                    } else {
                        Category::create($name, $type, $parentId);
                        $this->flash('success', "Category '$name' created.");
                    }
                }
            } elseif (isset($_POST['edit_category'])) {
                $id = (int)$_POST['category_id'];
                $name = trim((string)($_POST['name'] ?? ''));
                $type = (string)($_POST['type'] ?? 'other');
                $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                if ($name !== '') {
                    if (Category::nameTaken($name, $id)) {
                        $this->flash('error', "A category named '$name' already exists.");
                    } else {
                        Category::update($id, $name, $type, $parentId);
                        $this->flash('success', 'Category updated.');
                    }
                }
            } elseif (isset($_POST['delete_category'])) {
                $result = Category::delete((int)$_POST['category_id']);
                if ($result === 'products') {
                    $this->flash('error', 'This category has products and cannot be deleted.');
                } elseif ($result === 'children') {
                    $this->flash('error', 'Remove subcategories before deleting this category.');
                } else {
                    $this->flash('success', 'Category deleted.');
                }
            }
            $this->redirect('categories');
        }

        $this->render([
            'categories' => Category::allWithParents(),
            'parents' => Category::all(),
        ]);
    }
}
