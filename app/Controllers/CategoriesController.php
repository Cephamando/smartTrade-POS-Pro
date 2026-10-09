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
            if (isset($_POST['add_category']) || isset($_POST['edit_category'])) {
                $editing = isset($_POST['edit_category']);
                $id = $editing ? (int)$_POST['category_id'] : 0;
                $name = trim((string)($_POST['name'] ?? ''));
                $type = (string)($_POST['type'] ?? 'other');
                $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                if ($name === '') {
                    $error = 'Category name is required.';
                } elseif (Category::nameTaken($name, $editing ? $id : null)) {
                    $error = "A category named '$name' already exists.";
                } else {
                    $error = '';
                    if ($editing) {
                        Category::update($id, $name, $type, $parentId);
                    } else {
                        $id = Category::create($name, $type, $parentId);
                    }
                }
                if ($error !== '') {
                    if ($this->wantsAjax()) {
                        $this->jsonExit(['status' => 'error', 'msg' => $error], 400);
                    }
                    $this->flash('error', $error);
                } else {
                    $message = $editing ? 'Category updated.' : "Category '$name' created.";
                    if ($this->wantsAjax()) {
                        $row = Category::findWithParent($id);
                        if (!$row) {
                            $this->jsonExit(['status' => 'error', 'msg' => 'Saved, but the category row could not be loaded.'], 500);
                        }
                        $this->jsonExit([
                            'status' => 'success',
                            'message' => $message,
                            'id' => $id,
                            'name' => (string)$row['name'],
                            'html' => $this->renderPartial('category_row', ['c' => $row]),
                        ]);
                    }
                    $this->flash('success', $message);
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
