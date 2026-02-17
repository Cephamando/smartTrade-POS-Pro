<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$saleId = $_GET['sale_id'] ?? 0;
$error = '';
$success = '';

// FETCH SALE ITEMS
$sale = $pdo->query("SELECT * FROM sales WHERE id = $saleId")->fetch();
$items = $pdo->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $saleId AND si.status != 'refunded'")->fetchAll();

// PROCESS REFUND
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $refunds = $_POST['refund_qty'] ?? [];
    $totalRefunded = 0;
    $managerId = $_SESSION['user_id']; // Logged in user

    $pdo->beginTransaction();
    try {
        foreach ($refunds as $itemId => $qty) {
            $qty = intval($qty);
            if ($qty <= 0) continue;

            $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if ($qty > $item['quantity']) throw new Exception("Cannot refund more than sold qty for " . $item['name']);

            // LOGIC: If Partial, reduce qty. If Full, mark status.
            if ($qty == $item['quantity']) {
                $pdo->prepare("UPDATE sale_items SET status = 'refunded' WHERE id = ?")->execute([$itemId]);
            } else {
                $newQty = $item['quantity'] - $qty;
                $pdo->prepare("UPDATE sale_items SET quantity = ? WHERE id = ?")->execute([$newQty, $itemId]);
                // Insert Refunded Line for record
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, 'refunded', 'collected')")
                    ->execute([$saleId, $item['product_id'], $qty, $item['price_at_sale']]);
            }

            // Update Stock
            $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?")
                ->execute([$qty, $item['product_id'], $sale['location_id']]);

            // Calc Refund Value
            $refundAmount = $qty * $item['price_at_sale'];
            $totalRefunded += $refundAmount;
        }

        // Update Sales Header
        $pdo->prepare("UPDATE sales SET total_amount = total_amount - ?, final_total = final_total - ? WHERE id = ?")
            ->execute([$totalRefunded, $totalRefunded, $saleId]);

        // Log Refund
        $pdo->prepare("INSERT INTO refunds (sale_id, manager_id, amount_refunded, reason, created_at) VALUES (?, ?, ?, 'Partial Refund', NOW())")
            ->execute([$saleId, $managerId, $totalRefunded]);

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Refund processed successfully.";
        header("Location: index.php?page=reports");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
