<?php
// SECURITY: Chefs, Head Chefs, Admins Only
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

require_once 'config.php';

// Allow Manager/Admin/Dev/Chef to view KDS
if (!in_array(strtolower($_SESSION['role'] ?? ''), ['chef', 'head_chef', 'kitchen', 'admin', 'dev', 'manager'])) {
    die("Access Denied: Kitchen Staff Only.");
}

// 1. HANDLE ACTIONS (Start Cooking / Mark Ready)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'];
    $newStatus = $_POST['status']; 

    // Update Item Status
    $stmt = $pdo->prepare("UPDATE sale_items SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $itemId]);

    // IF READY: Push to Pickup Screen AND Send API Feedback
    if ($newStatus === 'ready') {
        $info = $pdo->prepare("
            SELECT si.sale_id, p.name, s.split_group_id, s.payment_method 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            JOIN sales s ON si.sale_id = s.id
            WHERE si.id = ?
        ");
        $info->execute([$itemId]);
        $item = $info->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // Internal Pickup Notification
            $check = $pdo->prepare("SELECT id FROM pickup_notifications WHERE sale_id = ? AND item_name = ? AND status = 'ready'");
            $check->execute([$item['sale_id'], $item['name']]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO pickup_notifications (sale_id, item_name, status) VALUES (?, ?, 'ready')")
                    ->execute([$item['sale_id'], $item['name']]);
            }

            // EXTERNAL API FEEDBACK (The "Smart" part)
            // If the sale has a split_group_id (External ID) and was an Online order
            if (!empty($item['split_group_id']) && $item['payment_method'] === 'Online Delivery') {
                
                // Check if ALL items in this sale are now 'ready' before notifying
                $checkAllReady = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status != 'ready'");
                $checkAllReady->execute([$item['sale_id']]);
                $remaining = $checkAllReady->fetchColumn();

                if ($remaining == 0) {
                    // Send Webhook to External Platform
                    $webhookUrl = "https://webhook.site/8f7d9a2b-test-ready-notif"; // REPLACE WITH ACTUAL ENDPOINT
                    $payload = json_encode([
                        "order_id" => $item['split_group_id'],
                        "status" => "ready_for_collection",
                        "store_id" => "MAIN_BAR_001",
                        "timestamp" => date('c')
                    ]);

                    $ch = curl_init($webhookUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Don't let the chef wait if the internet is slow
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }
    }
    exit;
}

// 2. FETCH ACTIVE ORDERS
$sql = "
    SELECT s.id as sale_id, s.created_at, u.username as server,
           si.id as item_id, si.quantity, si.status, p.name as product_name, 
           COALESCE(c.name, 'Uncategorized') as category
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE si.status IN ('pending', 'cooking')
    ORDER BY si.status DESC, s.created_at ASC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Group by Ticket (Sale ID)
$orders = [];
foreach ($rows as $r) {
    $sid = $r['sale_id'];
    if (!isset($orders[$sid])) {
        $orders[$sid] = [
            'id' => $sid,
            'time' => $r['created_at'],
            'waiter' => ucfirst($r['server'] ?? 'Online'),
            'items' => []
        ];
    }
    $orders[$sid]['items'][] = [
        'id' => $r['item_id'],
        'name' => $r['product_name'],
        'qty' => (int)$r['quantity'],
        'status' => $r['status']
    ];
}

// 3. RETURN JSON FOR POLLING
if (isset($_GET['ajax_poll'])) {
    header('Content-Type: application/json');
    echo json_encode(array_values($orders));
    exit;
}
?>
