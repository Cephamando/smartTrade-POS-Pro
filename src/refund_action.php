<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id = $_POST['sale_id'];
    $manager_pass = $_POST['manager_password'];
    $reason = $_POST['reason'] ?? 'Customer Return';

    try {
        // 1. Verify Manager Credentials
        $stmt = $pdo->prepare("SELECT id, password FROM users WHERE role IN ('admin', 'manager', 'dev') AND status = 'active'");
        $stmt->execute();
        $managers = $stmt->fetchAll();

        $authorized_id = null;
        foreach ($managers as $m) {
            if (password_verify($manager_pass, $m['password'])) {
                $authorized_id = $m['id'];
                break;
            }
        }

        if (!$authorized_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid Manager Password']);
            exit;
        }

        $pdo->beginTransaction();

        // 2. Get Sale Details
        $saleStmt = $pdo->prepare("SELECT final_total, location_id FROM sales WHERE id = ?");
        $saleStmt->execute([$sale_id]);
        $sale = $saleStmt->fetch();

        // 3. Restore Stock
        $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $itemsStmt->execute([$sale_id]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            $updateStock = $pdo->prepare("UPDATE inventory SET stock_level = stock_level + ? WHERE product_id = ? AND location_id = ?");
            $updateStock->execute([$item['quantity'], $item['product_id'], $sale['location_id']]);
        }

        // 4. Record Refund and Update Sale Status
        $refundStmt = $pdo->prepare("INSERT INTO refunds (sale_id, manager_id, amount_refunded, reason) VALUES (?, ?, ?, ?)");
        $refundStmt->execute([$sale_id, $authorized_id, $sale['final_total'], $reason]);

        $updateSale = $pdo->prepare("UPDATE sales SET status = 'refunded' WHERE id = ?");
        $updateSale->execute([$sale_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
