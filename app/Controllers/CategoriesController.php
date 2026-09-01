<?php

namespace App\Controllers;

use App\Models\Category;

final class CategoriesController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_category'])) {
                $name = trim((string)($_POST['name'] ?? ''));
                $type = (string)($_POST['type'] ?? 'other');
                $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                if ($name !== '') {
                    if (Category::nameTaken($name)) {
                        $this->flash('error', "Category '$name' already exists.");
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
                        $this->flash('error', "Category name '$name' already in use.");
                    } else {
                        Category::update($id, $name, $type, $parentId);
                        $this->flash('success', 'Category updated.');
                    }
                }
            } elseif (isset($_POST['delete_category'])) {
                $result = Category::delete((int)$_POST['category_id']);
                if ($result === 'products') {
                    $this->flash('error', 'Cannot delete: Products are linked to this category.');
                } elseif ($result === 'children') {
                    $this->flash('error', 'Cannot delete: This category contains subcategories.');
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
