### products.php
```php
<?php
// SECURITY: Only Managers/Admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// HANDLE POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. ADD CATEGORY
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name']);
            if (empty($name)) throw new Exception("Category Name is required");

            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Category '$name' created!";
        }

        // 2. ADD/EDIT PRODUCT
        if (isset($_POST['save_product'])) {
            $name = trim($_POST['name']);
            $catId = $_POST['category_id'] ?: null;
            $price = $_POST['price'] ?: 0;
            $cost = $_POST['cost_price'] ?: 0;
            $unit = $_POST['unit'] ?: 'unit';
            $sku  = trim($_POST['sku']) ?: null; // Optional SKU
            
            // Basic Duplicate Check on Name
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) throw new Exception("A product with this name already exists.");

            $sql = "INSERT INTO products (name, category_id, price, cost_price, unit, sku) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $catId, $price, $cost, $unit, $sku]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product '$name' saved successfully.";
        }

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // PRG Redirect
    header("Location: index.php?page=products");
    exit;
}

// FETCH DATA FOR VIEW
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC";
$products = $pdo->query($sql)->fetchAll();
?>
```

### receipt.php
```php
<?php
// LOGIC ONLY - No HTML Output
if (!isset($_GET['sale_id'])) die("Sale ID required");

$saleId = $_GET['sale_id'];

// 1. Fetch Sale Details (Includes Cashier Name & Collected By)
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as cashier_name, l.name as location_name, l.address, l.phone 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    JOIN locations l ON s.location_id = l.id 
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) die("Sale not found");

// 2. Fetch Items
$items = $pdo->prepare("
    SELECT si.*, p.name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$items->execute([$saleId]);
$lineItems = $items->fetchAll();

// 3. Determine Collection Status Logic
$collectionStatus = "NOT COLLECTED";
$statusColor = "red";

if (!empty($sale['collected_by'])) {
    $collectionStatus = "COLLECTED BY: " . strtoupper($sale['collected_by']);
    $statusColor = "black";
}
?>

```

### receive.php
```php
<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$userId = $_SESSION['user_id'];
$locId  = $_SESSION['location_id']; // Receives into CURRENT location

// --- HANDLE POST: PROCESS GRV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_grv'])) {
    try {
        $vendorId = $_POST['vendor_id'];
        $refNo = trim($_POST['reference_no']);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $costs = $_POST['costs'] ?? [];

        if (empty($vendorId) || empty($productIds)) {
            throw new Exception("Please select a vendor and at least one product.");
        }

        $pdo->beginTransaction();

        // 1. Create GRV Record
        // Calculate Total Cost
        $totalCost = 0;
        foreach ($productIds as $k => $pid) {
            $qty = floatval($quantities[$k]);
            $unitCost = floatval($costs[$k]);
            $totalCost += ($qty * $unitCost);
        }

        $stmt = $pdo->prepare("INSERT INTO grvs (vendor_id, location_id, received_by, total_cost, reference_no, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$vendorId, $locId, $userId, $totalCost, $refNo]);
        $grvId = $pdo->lastInsertId();

        // 2. Process Items & Update Stock
        $itemStmt = $pdo->prepare("INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
        
        // Upsert Stock (Insert or Update if exists)
        $stockStmt = $pdo->prepare("INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");

        foreach ($productIds as $k => $pid) {
            $qty = floatval($quantities[$k]);
            $unitCost = floatval($costs[$k]);

            if ($qty > 0) {
                // Record Item
                $itemStmt->execute([$grvId, $pid, $qty, $unitCost]);

                // Update Stock Level
                $stockStmt->execute([$locId, $pid, $qty, $qty]);
            }
        }

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Stock Received Successfully! GRV #$grvId created.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }

    header("Location: index.php?page=receive");
    exit;
}

// FETCH DATA
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, unit FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>
```

### reports.php
```php
<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// 1. DEFAULT FILTERS (Current Month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$locationId = $_GET['location_id'] ?? '';

// 2. BUILD QUERY CONDITIONS
$where = "WHERE s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if (!empty($locationId)) {
    $where .= " AND s.location_id = ?";
    $params[] = $locationId;
}

// 3. FETCH KPI METRICS
$kpiSql = "
    SELECT 
        COUNT(id) as total_txns,
        SUM(final_total) as total_revenue,
        AVG(final_total) as avg_basket
    FROM sales s
    $where
";
$stmt = $pdo->prepare($kpiSql);
$stmt->execute($params);
$kpi = $stmt->fetch();

// 4. FETCH PAYMENT BREAKDOWN
$paySql = "
    SELECT payment_method, SUM(final_total) as total
    FROM sales s
    $where
    GROUP BY payment_method
";
$stmt = $pdo->prepare($paySql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['cash' => 1000, 'card' => 500]

// 5. FETCH TOP 5 PRODUCTS
$topSql = "
    SELECT p.name, SUM(si.quantity) as qty_sold, SUM(si.quantity * si.price_at_sale) as revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    $where
    GROUP BY p.id
    ORDER BY qty_sold DESC
    LIMIT 5
";
$stmt = $pdo->prepare($topSql);
$stmt->execute($params);
$topProducts = $stmt->fetchAll();

// 6. FETCH RECENT TRANSACTIONS
$txnSql = "
    SELECT s.*, u.username, l.name as loc_name
    FROM sales s
    JOIN users u ON s.user_id = u.id
    JOIN locations l ON s.location_id = l.id
    $where
    ORDER BY s.created_at DESC
    LIMIT 100
";
$stmt = $pdo->prepare($txnSql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch Locations for Filter Dropdown
$locs = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>

```


### shifts.php
```php
<?php
// SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php?page=login"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role']; 
$locId  = $_SESSION['location_id'];

// Define roles that handle money
$moneyRoles = ['cashier', 'manager', 'admin', 'dev', 'bartender'];
$isMoneyRole = in_array($userRole, $moneyRoles);

// --- HELPER: Verify Manager Password ---
function verifyManager($pdo, $password, $locationId, $currentUserId) {
    // Allow Admins, Devs, or Managers of this location
    $sql = "SELECT id, password_hash FROM users 
            WHERE (role IN ('admin', 'head_chef','dev') OR (role = 'manager' AND location_id = ?))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locationId]);
    $managers = $stmt->fetchAll();

    foreach ($managers as $mgr) {
        if (password_verify($password, $mgr['password_hash'])) {
            // Prevent self-verification
            if ($mgr['id'] == $currentUserId) return 'SELF_ERROR';
            return $mgr['id'];
        }
    }
    return false;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // 1. START SHIFT
        if (isset($_POST['start_shift'])) {
            $mgrPass = $_POST['manager_password'] ?? '';
            $mgrId = verifyManager($pdo, $mgrPass, $locId, $userId);

            if ($mgrId === 'SELF_ERROR') throw new Exception("⛔ Security: You cannot verify your own shift.");
            if (!$mgrId) throw new Exception("Incorrect Manager Password.");

            // Check for existing open shift
            $check = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'");
            $check->execute([$userId]);
            if ($check->fetch()) throw new Exception("You already have an open shift.");

            $startingCash = $isMoneyRole ? ($_POST['start_amount'] ?? 0) : 0;

            $stmt = $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status, start_verified_by, start_verified_at) VALUES (?, ?, NOW(), ?, 'open', ?, NOW())");
            $stmt->execute([$userId, $locId, $startingCash, $mgrId]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'Clock-In Successful!';
        }

        // 2. END SHIFT
        if (isset($_POST['end_shift'])) {
            $shiftId = $_POST['shift_id'];
            $mgrPass = $_POST['manager_password'] ?? '';
            $mgrId = verifyManager($pdo, $mgrPass, $locId, $userId);

            if ($mgrId === 'SELF_ERROR') throw new Exception("⛔ Security: You cannot verify your own shift close.");
            if (!$mgrId) throw new Exception("Incorrect Manager Password.");

            $closingCash = 0; $notes = null; $varianceReason = null; $managerCount = 0;

            if ($isMoneyRole) {
                $closingCash = $_POST['end_amount'] ?? 0;
                $managerCount = $_POST['manager_count'] ?? $closingCash; // Optional double count
                $varianceReason = $_POST['variance_reason'] ?? null;
            } else {
                $notes = $_POST['handover_notes'] ?? '';
            }

            // Calculate Expected Cash (for DB record)
            // Note: In a real app, you might want to fetch sales totals here to save 'expected_cash' accurately.
            // For now, we trust the view passed or recalculate if needed. We will keep it simple.

            $stmt = $pdo->prepare("
                UPDATE shifts SET 
                    end_time = NOW(), 
                    closing_cash = ?, 
                    manager_closing_cash = ?,
                    variance_reason = ?, 
                    handover_notes = ?, 
                    status = 'closed', 
                    end_verified_by = ?, 
                    end_verified_at = NOW() 
                WHERE id = ?");
            
            $stmt->execute([$closingCash, $managerCount, $varianceReason, $notes, $mgrId, $shiftId]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'Shift Closed Successfully.';
        }

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // REDIRECT (PRG Pattern)
    header("Location: index.php?page=shifts");
    exit;
}

// --- FETCH VIEW DATA ---
// 1. Current Active Shift
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$userId]);
$currentShift = $stmt->fetch();

// 2. Statistics for Closing (Only if active shift exists)
$calculatedSummary = [];
if ($currentShift && $isMoneyRole) {
    // Cash Sales
    $sStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_method = 'cash' AND status != 'refunded'");
    $sStmt->execute([$currentShift['id']]);
    $cashSales = $sStmt->fetchColumn() ?: 0.00;

    // Expenses paid from till
    $eStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?");
    $eStmt->execute([$userId, $currentShift['start_time']]);
    $expenses = $eStmt->fetchColumn() ?: 0.00;

    $expected = ($currentShift['starting_cash'] + $cashSales) - $expenses;
    
    $calculatedSummary = [
        'float' => $currentShift['starting_cash'],
        'cash_sales' => $cashSales,
        'expenses' => $expenses,
        'expected' => $expected
    ];
}

// 3. History
$histStmt = $pdo->prepare("
    SELECT s.*, u1.username as start_mgr, u2.username as end_mgr 
    FROM shifts s 
    LEFT JOIN users u1 ON s.start_verified_by = u1.id
    LEFT JOIN users u2 ON s.end_verified_by = u2.id
    WHERE s.user_id = ? 
    ORDER BY s.start_time DESC LIMIT 10");
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();
?>
```


### transfers.php
```php
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
            // FIX 1: Check Source Stock in 'inventory' table
            $check = $pdo->prepare("SELECT quantity FROM inventory WHERE location_id = ? AND product_id = ?");
            $check->execute([$transfer['source_location_id'], $transfer['product_id']]);
            $stock = $check->fetchColumn() ?: 0;

            if ($stock < $transfer['quantity']) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Insufficient stock at source (Qty: $stock) to dispatch.";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // FIX 2: Deduct from 'inventory' using direct UPDATE
                    $deduct = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE location_id = ? AND product_id = ?");
                    $deduct->execute([$transfer['quantity'], $transfer['source_location_id'], $transfer['product_id']]);

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

                // FIX 3: Add to 'inventory' table
                // Uses INSERT ... ON DUPLICATE to handle cases where the destination might have 0 rows initially
                $add = $pdo->prepare("INSERT INTO inventory (location_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
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

// 3. My Recent Requests
$myRequestsSql = "
    SELECT t.*, p.name as product_name, l1.name as source_name, l2.name as dest_name 
    FROM inventory_transfers t
    JOIN products p ON t.product_id = p.id
    JOIN locations l1 ON t.source_location_id = l1.id
    JOIN locations l2 ON t.dest_location_id = l2.id
    WHERE t.dest_location_id = $userLoc AND t.status = 'pending'
    ORDER BY t.created_at DESC LIMIT 20
";
if ($userRole === 'admin' || $userRole === 'dev') {
    $myRequestsSql = str_replace("WHERE t.dest_location_id = $userLoc AND", "WHERE", $myRequestsSql);
}
$myRequests = $pdo->query($myRequestsSql)->fetchAll();

$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

```

### users.php
```php
<?php
// SECURITY: Admin, Dev, and Manager Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dev', 'manager'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$currentUserRole = $_SESSION['role'];

// --- HELPER: FETCH ENUM ROLES FROM DB ---
function getDbRoles($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $row = $stmt->fetch();
    $type = substr($row['Type'], 6, -2);
    return explode("','", $type);
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD USER
    if (isset($_POST['add_user'])) {
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $locationId = $_POST['location_id'];

        // SECURITY: Only Dev can create Dev
        if ($role === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: Only Developers can create Dev accounts.";
        } 
        elseif (empty($fullName) || empty($username) || empty($password)) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Full Name, Username, and Password are required.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Username '$username' is already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, location_id, force_password_change) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$fullName, $username, $hash, $role, $locationId]);

                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "User created successfully.";
            }
        }
    }

    // 2. EDIT USER
    if (isset($_POST['edit_user'])) {
        $id = $_POST['user_id'];
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $role = $_POST['role']; // New Role
        $locationId = $_POST['location_id'];
        
        // Fetch Current Role of the user being edited
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        // SECURITY CHECKS
        if ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot edit a Developer account.";
        }
        elseif ($role === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot promote a user to Developer.";
        }
        else {
            $passSql = "";
            $params = [$fullName, $username, $role, $locationId];
            
            if (!empty($_POST['password'])) {
                $passSql = ", password_hash = ?, force_password_change = 1";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, location_id = ? $passSql WHERE id = ?");
            $stmt->execute($params);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "User updated successfully.";
        }
    }

    // 3. DELETE USER
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        
        // Fetch Target Role
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        if ($id == $_SESSION['user_id']) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "You cannot delete yourself.";
        }
        elseif ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot delete a Developer.";
        }
        else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "User deleted.";
        }
    }
    
    // 4. RESET PASSWORD
    if (isset($_POST['reset_password_default'])) {
        $id = $_POST['user_id'];
        
        // Fetch Target Role
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        if ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot reset a Developer's password.";
        } else {
            $defaultHash = password_hash('pos123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?");
            $stmt->execute([$defaultHash, $id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Password reset to 'pos123'.";
        }
    }

    header("Location: index.php?page=users");
    exit;
}

// --- FETCH DATA ---
$users = $pdo->query("SELECT u.*, l.name as location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id ORDER BY u.role ASC, u.username ASC")->fetchAll();
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();

// Fetch Roles & FILTER OUT 'dev' if not authorized
$roles = getDbRoles($pdo);
if ($currentUserRole !== 'dev') {
    $roles = array_diff($roles, ['dev']); // Remove 'dev' from array
}
?>

```

### vendors.php
```php
<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD VENDOR
    if (isset($_POST['add_vendor'])) {
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO vendors (name, contact_person, phone) VALUES (?, ?, ?)");
            $stmt->execute([$name, $contact, $phone]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Vendor '$name' added successfully.";
        }
    }

    // 2. DELETE VENDOR
    if (isset($_POST['delete_vendor'])) {
        $id = $_POST['vendor_id'];
        
        // Check if used in GRVs
        $check = $pdo->prepare("SELECT id FROM grvs WHERE vendor_id = ? LIMIT 1");
        $check->execute([$id]);
        
        if ($check->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete vendor. They have linked GRV records.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Vendor deleted.";
        }
    }

    // Redirect
    header("Location: index.php?page=vendors");
    exit;
}

// --- FETCH DATA ---
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name ASC")->fetchAll();
?>

```