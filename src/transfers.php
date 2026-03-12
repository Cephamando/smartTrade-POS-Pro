<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$userLoc = $_SESSION['location_id'];
$userRole = $_SESSION['role'];

// --- 📡 BULLETPROOF AJAX HANDLER FOR REAL-TIME STOCK DETECTION ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_stock_level') {
    error_reporting(0); // 1. Suppress PHP warnings from corrupting the JSON
    while (ob_get_level()) ob_end_clean(); // 2. Safely wipe any accidental whitespace
    
    header('Content-Type: application/json');
    $pid = (int)($_GET['product_id'] ?? 0);
    $locId = (int)($_GET['location_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
        $stmt->execute([$pid, $locId]);
        $qty = $stmt->fetchColumn();
        echo json_encode(['stock' => $qty !== false ? (float)$qty : 0]);
    } catch (Exception $e) {
        echo json_encode(['stock' => 0, 'error' => $e->getMessage()]);
    }
    exit; // 3. Kill the script so HTML doesn't get appended
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CREATE REQUISITION
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
            $stmt = $pdo->prepare("INSERT INTO inventory_transfers (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$sourceId, $destId, $prodId, $qty, $userId]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Requisition sent! Waiting for Dispatch.";
        }
    }

    // 2. DISPATCH (Source Manager Approves)
    if (isset($_POST['dispatch_transfer'])) {
        $tid = $_POST['transfer_id'];
        $t = $pdo->prepare("SELECT * FROM inventory_transfers WHERE id = ?"); $t->execute([$tid]);
        $transfer = $t->fetch();

        if ($transfer && $transfer['status'] === 'pending') {
            $pdo->beginTransaction();
            // Check stock
            $check = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $check->execute([$transfer['product_id'], $transfer['source_location_id']]);
            $currentQty = $check->fetchColumn() ?: 0;

            if ($currentQty >= $transfer['quantity']) {
                // Deduct
                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$transfer['quantity'], $transfer['product_id'], $transfer['source_location_id']]);
                
                // Log
                $newQty = $currentQty - $transfer['quantity'];
                $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'transfer_out', ?)")
                    ->execute([$transfer['product_id'], $transfer['source_location_id'], $userId, -$transfer['quantity'], $newQty, $tid]);

                // Update Status
                $pdo->prepare("UPDATE inventory_transfers SET status = 'in_transit', dispatched_at = NOW() WHERE id = ?")->execute([$tid]);
                
                $pdo->commit();
                $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Stock Dispatched!";
            } else {
                $pdo->rollBack();
                $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Insufficient stock at source.";
            }
        }
    }

    // 3. RECEIVE (Destination Manager Accepts)
    if (isset($_POST['receive_transfer'])) {
        $tid = $_POST['transfer_id'];
        $t = $pdo->prepare("SELECT * FROM inventory_transfers WHERE id = ?"); $t->execute([$tid]);
        $transfer = $t->fetch();

        if ($transfer && $transfer['status'] === 'in_transit') {
            $pdo->beginTransaction();
            
            // Add to Dest
            $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)")->execute([$transfer['product_id'], $transfer['dest_location_id'], $transfer['quantity']]);
            
            // Log
            $check = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $check->execute([$transfer['product_id'], $transfer['dest_location_id']]);
            $newQty = $check->fetchColumn();
            
            $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'transfer_in', ?)")
                ->execute([$transfer['product_id'], $transfer['dest_location_id'], $userId, $transfer['quantity'], $newQty, $tid]);
            
            // Update Status
            $pdo->prepare("UPDATE inventory_transfers SET status = 'completed', received_at = NOW() WHERE id = ?")->execute([$tid]);
            
            $pdo->commit();
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Stock Received!";
        }
    }

    // 4. CANCEL
    if (isset($_POST['cancel_transfer'])) {
        $tid = $_POST['transfer_id'];
        $pdo->prepare("UPDATE inventory_transfers SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$tid]);
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Request cancelled.";
    }

    header("Location: index.php?page=transfers"); exit;
}

// --- DATA FETCHING ---
// Pending Dispatches (Outgoing)
$dispatchSql = "SELECT t.*, p.name AS product_name, l1.name AS source_name, l2.name AS dest_name FROM inventory_transfers t JOIN products p ON t.product_id = p.id JOIN locations l1 ON t.source_location_id = l1.id JOIN locations l2 ON t.dest_location_id = l2.id WHERE t.status = 'pending'";
$paramsD = []; if ($userRole !== 'admin' && $userRole !== 'dev') { $dispatchSql .= " AND t.source_location_id = ?"; $paramsD[] = $userLoc; }
$pendingDispatch = $pdo->prepare($dispatchSql); $pendingDispatch->execute($paramsD); $pendingDispatch = $pendingDispatch->fetchAll();

// Incoming Stock (To Receive)
$receiveSql = "SELECT t.*, p.name AS product_name, l1.name AS source_name, l2.name AS dest_name FROM inventory_transfers t JOIN products p ON t.product_id = p.id JOIN locations l1 ON t.source_location_id = l1.id JOIN locations l2 ON t.dest_location_id = l2.id WHERE t.status = 'in_transit'";
$paramsR = []; if ($userRole !== 'admin' && $userRole !== 'dev') { $receiveSql .= " AND t.dest_location_id = ?"; $paramsR[] = $userLoc; }
$incomingStock = $pdo->prepare($receiveSql); $incomingStock->execute($paramsR); $incomingStock = $incomingStock->fetchAll();

// My Requests
$myRequestsSql = "SELECT t.*, p.name AS product_name, l1.name AS source_name, l2.name AS dest_name FROM inventory_transfers t JOIN products p ON t.product_id = p.id JOIN locations l1 ON t.source_location_id = l1.id JOIN locations l2 ON t.dest_location_id = l2.id WHERE t.status = 'pending'";
$paramsM = []; if ($userRole !== 'admin' && $userRole !== 'dev') { $myRequestsSql .= " AND t.dest_location_id = ?"; $paramsM[] = $userLoc; }
$myRequests = $pdo->prepare($myRequestsSql); $myRequests->execute($paramsM); $myRequests = $myRequests->fetchAll();

// Dropdowns
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 AND type = 'item' ORDER BY name ASC")->fetchAll();
?>
