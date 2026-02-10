<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Fetch pending orders (adjust logic if you have specific 'kitchen' status)
// For now, we assume 'paid' or 'pending' orders that haven't been marked 'served'
// This is a placeholder query - tailor to your specific workflow
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count 
        FROM sales s 
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        ORDER BY s.created_at DESC";
$orders = $pdo->query($sql)->fetchAll();
?>
