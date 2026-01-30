<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$userRole = $_SESSION['role'];
$userLoc = $_SESSION['location_id'];

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CREATE REQUISITION (Request Stock)
    if (isset($_POST['create_request'])) {
        $sourceId = $_POST['source_location_id'];
        $destId = $_POST['dest_location_id'];
        $prodId = $_POST['product_id'];
        $qty = floatval($_POST['quantity']);

        if ($qty <= 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Quantity must be greater than 0.";
        } elseif ($sourceId == $destId) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Source and Destination cannot be the same.";
        } else {
            // Create Pending Transfer
            $stmt = $pdo->prepare("INSERT INTO inventory_transfers (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$sourceId, $destId, $prodId, $qty, $_SESSION['user_id']]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Requisition sent! Waiting for Dispatch.";
        }
    }

    // 2. DISPATCH (Source Manager Approves)
    if (isset($_POST['dispatch_transfer'])) {
        $transferId = $_POST['transfer_id'];
        
        // Fetch Transfer Details
        $t = $pdo->prepare("SELECT * FROM inventory_transfers WHERE id = ?");
        $t->execute([$transferId]);
        $transfer = $t->fetch();

        if ($transfer && $transfer['status'] === 'pending') {
            // Check Source Stock
            $check = $pdo->prepare("SELECT quantity FROM location_stock WHERE location_id = ? AND product_id = ?");
            $check->execute([$transfer['source_location_id'], $transfer['product_id']]);
            $stock = $check->fetchColumn() ?: 0;

            if ($stock < $transfer['quantity']) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Insufficient stock at source to dispatch.";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Deduct from Source NOW
                    $deduct = $pdo->prepare("INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, -?) ON DUPLICATE KEY UPDATE quantity = quantity - VALUES(quantity)");
                    $deduct->execute([$transfer['source_location_id'], $transfer['product_id'], $transfer['quantity']]);

                    // Update Status
                    $update = $pdo->prepare("UPDATE inventory_transfers SET status = 'in_transit', dispatched_at = NOW() WHERE id = ?");
                    $update->execute([$transferId]);

                    $pdo->commit();
                    $_SESSION['swal_type'] = 'success';
                    $_SESSION['swal_msg'] = "Stock Dispatched! It is now in transit.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['swal_type'] = 'error';
                    $_SESSION['swal_msg'] = "Error dispatching stock.";
                }
            }
        }
    }

    // 3. RECEIVE (Destination Manager Accepts)
    if (isset($_POST['receive_transfer'])) {
        $transferId = $_POST['transfer_id'];

        $t = $pdo->prepare("SELECT * FROM inventory_transfers WHERE id = ?");
        $t->execute([$transferId]);
        $transfer = $t->fetch();

        if ($transfer && $transfer['status'] === 'in_transit') {
            try {
                $pdo->beginTransaction();

                // Add to Destination NOW
                $add = $pdo->prepare("INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
                $add->execute([$transfer['dest_location_id'], $transfer['product_id'], $transfer['quantity']]);

                // Finalize Status
                $update = $pdo->prepare("UPDATE inventory_transfers SET status = 'completed', received_at = NOW() WHERE id = ?");
                $update->execute([$transferId]);

                $pdo->commit();
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "Stock Received successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Error receiving stock.";
            }
        }
    }

    // 4. CANCEL (Cleanup)
    if (isset($_POST['cancel_transfer'])) {
        $transferId = $_POST['transfer_id'];
        // Only allow cancel if pending (no stock moved yet)
        $pdo->prepare("UPDATE inventory_transfers SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$transferId]);
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Requisition cancelled.";
    }

    header("Location: index.php?page=transfers");
    exit;
}

// --- FETCH DATA ---

// 1. Pending Dispatches (Outgoing from MY location)
// If Admin, show ALL pending. If Manager, show pending where Source = My Location
$dispatchSql = "
    SELECT t.*, p.name as product_name, l1.name as source_name, l2.name as dest_name 
    FROM inventory_transfers t
    JOIN products p ON t.product_id = p.id
    JOIN locations l1 ON t.source_location_id = l1.id
    JOIN locations l2 ON t.dest_location_id = l2.id
    WHERE t.status = 'pending'
";
if ($userRole !== 'admin' && $userRole !== 'dev') {
    $dispatchSql .= " AND t.source_location_id = $userLoc";
}
$pendingDispatch = $pdo->query($dispatchSql)->fetchAll();

// 2. Pending Reception (Incoming to MY location)
// If Admin, show ALL in_transit. If Manager, show in_transit where Dest = My Location
$receiveSql = "
    SELECT t.*, p.name as product_name, l1.name as source_name, l2.name as dest_name 
    FROM inventory_transfers t
    JOIN products p ON t.product_id = p.id
    JOIN locations l1 ON t.source_location_id = l1.id
    JOIN locations l2 ON t.dest_location_id = l2.id
    WHERE t.status = 'in_transit'
";
if ($userRole !== 'admin' && $userRole !== 'dev') {
    $receiveSql .= " AND t.dest_location_id = $userLoc";
}
$incomingStock = $pdo->query($receiveSql)->fetchAll();

// 3. My Recent Requests (To track what I asked for)
$myRequestsSql = "
    SELECT t.*, p.name as product_name, l1.name as source_name, l2.name as dest_name 
    FROM inventory_transfers t
    JOIN products p ON t.product_id = p.id
    JOIN locations l1 ON t.source_location_id = l1.id
    JOIN locations l2 ON t.dest_location_id = l2.id
    WHERE t.dest_location_id = $userLoc AND t.status = 'pending'
    ORDER BY t.created_at DESC LIMIT 20
";
// Admins see everything
if ($userRole === 'admin' || $userRole === 'dev') {
    $myRequestsSql = str_replace("WHERE t.dest_location_id = $userLoc AND", "WHERE", $myRequestsSql);
}
$myRequests = $pdo->query($myRequestsSql)->fetchAll();


$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>
