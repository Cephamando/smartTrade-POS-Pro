<?php

namespace App\Controllers;

use App\Models\Kitchen;
use App\Models\User;

final class PickupController extends Controller
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
            try {
                $collectedBy = $_POST['collected_by'] ?? $this->userId();
                Kitchen::collectOrder((int)$_POST['sale_id'], $collectedBy);
                echo json_encode(['status' => 'success', 'sale_id' => $_POST['sale_id']]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error']);
            }
            exit;
        }

        $this->render([
            'readyOrders' => Kitchen::readyPickupOrders($this->locationId()),
            'waiters' => User::waiters(),
        ]);
    }
}
