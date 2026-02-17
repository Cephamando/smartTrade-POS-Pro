<?php
// ACTION: PARTIAL REFUND
// This script splits a sale_item row to allow refunding specific quantities.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_item_id'])) {
    $itemId = $_POST['refund_item_id'];
    $refundQty = intval($_POST['refund_qty']);
    $managerId = $_SESSION['user_id']; // Assuming manager logged in
    
    $pdo->beginTransaction();
    try {
        // 1. Get current item details
        $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if (!$item) throw new Exception("Item not found");
        if ($refundQty > $item['quantity']) throw new Exception("Cannot refund more than sold");
        
        // 2. Logic: Split or Update
        if ($refundQty == $item['quantity']) {
            // Full Refund of this line
            $pdo->prepare("UPDATE sale_items SET status = 'refunded' WHERE id = ?")->execute([$itemId]);
        } else {
            // Partial: Reduce original, Create new 'refunded' row
            $newQty = $item['quantity'] - $refundQty;
            $pdo->prepare("UPDATE sale_items SET quantity = ? WHERE id = ?")->execute([$newQty, $itemId]);
            
            // Insert Refunded Part
            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, 'refunded', 'collected')")
                ->execute([$item['sale_id'], $item['product_id'], $refundQty, $item['price_at_sale']]);
        }
        
        // 3. Update Sales Totals
        $refundAmount = $refundQty * $item['price_at_sale'];
        $pdo->prepare("UPDATE sales SET total_amount = total_amount - ?, final_total = final_total - ? WHERE id = ?")
            ->execute([$refundAmount, $refundAmount, $item['sale_id']]);

        // 4. Return to Stock (Optional, usually yes)
        $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?")
            ->execute([$refundQty, $item['product_id'], $_SESSION['pos_location_id']]);
            
        // 5. Log Refund
        $pdo->prepare("INSERT INTO refunds (sale_id, manager_id, amount_refunded, reason, created_at) VALUES (?, ?, ?, 'Partial Refund', NOW())")
            ->execute([$item['sale_id'], $managerId, $refundAmount]);

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Refund processed successfully.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }
    
    header("Location: index.php?page=reports"); // Or wherever you triggered it
    exit;
}
?>
