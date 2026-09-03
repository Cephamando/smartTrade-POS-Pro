<?php

namespace App\Controllers;

use App\Models\Kitchen;

final class KdsController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['chef', 'head_chef', 'kitchen', 'admin', 'dev', 'manager'], true)) {
            $this->flash('error', 'Only kitchen staff can open the kitchen display.');
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Kitchen::updateItemStatus((int)$_POST['item_id'], (string)$_POST['status']);
            exit;
        }

        $orders = Kitchen::kdsOrders();
        if (isset($_GET['ajax_poll'])) {
            $this->jsonExit(array_values($orders));
        }

        $this->render(['orders' => $orders]);
    }
}
