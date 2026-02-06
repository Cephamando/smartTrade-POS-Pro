<?php
// Load configuration
require_once __DIR__ . '/config.php';

// Check if running from CLI or Admin Session
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dev']))) {
    die("⛔ Access Denied: Seeder can only be run via CLI or by an Admin.");
}

echo "🌱 STARTING DATABASE RESET & SEED...\n";

try {
    // 1. DISABLE FOREIGN KEY CHECKS
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. DEFINE ALL TABLES TO TRUNCATE
    $tables = [
        'users', 'locations', 'categories', 'products', 'inventory', 
        'inventory_logs', 'sales', 'sale_items', 'members', 'shifts', 
        'inventory_transfers', 'grvs', 'grv_items', 'vendors', 
        'stock_transfers', 'stock_transfer_items', 'location_stock',
        'expenses', 'taxes', 'pickup_notifications', 'refund_requests'
    ];

    // 3. TRUNCATE TABLES
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
        echo "   - Cleared table: $table\n";
    }

    // 4. SEED LOCATIONS
    // Types: store, kitchen, bar, warehouse
    $pdo->exec("
        INSERT INTO locations (id, name, type, can_sell, can_receive_from_vendor) VALUES
        (1, 'Main Kitchen', 'kitchen', 1, 0),
        (2, 'Main Bar', 'bar', 1, 0),
        (3, 'Main Warehouse', 'warehouse', 0, 1),
        (4, 'Restaurant Bar', 'bar', 1, 0),
        (5, 'Coffee Shop', 'store', 1, 0)
    ");
    echo "✅ Locations Created\n";

    // 5. SEED USERS (Default Password: 1234)
    $pass = password_hash('1234', PASSWORD_DEFAULT);
    
    // Roles: admin, manager, cashier, chef, bartender, dev
    $pdo->prepare("
        INSERT INTO users (id, username, full_name, password_hash, role, location_id, force_password_change) VALUES
        (1, 'admin', 'System Admin', ?, 'admin', 3, 0),
        (2, 'dev', 'Developer Account', ?, 'dev', 3, 0),
        (3, 'manager', 'Bar Manager', ?, 'manager', 2, 0),
        (4, 'cashier', 'Bar Cashier', ?, 'cashier', 2, 0),
        (5, 'chef', 'Head Chef', ?, 'chef', 1, 0),
        (6, 'waiter', 'Restaurant Waiter', ?, 'cashier', 4, 0),
        (7, 'bartender', 'Main Bartender', ?, 'bartender', 2, 0)
    ")->execute([$pass, $pass, $pass, $pass, $pass, $pass, $pass]);
    
    echo "✅ Users Created (Pass: 1234)\n";
    echo "   - admin (Warehouse)\n   - manager (Main Bar)\n   - cashier (Main Bar)\n   - chef (Kitchen)\n";

    // 6. SEED CATEGORIES
    $pdo->exec("
        INSERT INTO categories (id, name, type) VALUES
        (1, 'Beverages', 'drink'),
        (2, 'Meals', 'food'),
        (3, 'Ingredients', 'ingredients'),
        (4, 'Snacks', 'food')
    ");

    // 7. SEED VENDORS
    $pdo->exec("
        INSERT INTO vendors (id, name, contact_person) VALUES
        (1, 'Coca Cola Zambia', 'Mr. Phiri'),
        (2, 'Zambeef', 'Sales Rep'),
        (3, 'Tiger Animal Feeds', 'Mrs. Banda')
    ");

    // 8. SEED TAXES
    $pdo->exec("
        INSERT INTO taxes (name, rate, is_active) VALUES
        ('VAT', 16.00, 1),
        ('Service Charge', 10.00, 0)
    ");

    // 9. SEED PRODUCTS
    // Prices in ZMW
    $pdo->exec("
        INSERT INTO products (id, name, category_id, price, cost_price, unit, is_active) VALUES
        (1, 'Coca Cola 300ml', 1, 15.00, 8.00, 'btl', 1),
        (2, 'Mosi Lager', 1, 25.00, 12.00, 'btl', 1),
        (3, 'T-Bone Steak', 2, 120.00, 60.00, 'plate', 1),
        (4, 'Jameson Shot', 1, 40.00, 15.00, 'tot', 1),
        (5, 'Water 500ml', 1, 5.00, 2.00, 'btl', 1),
        (6, 'Beef Burger', 2, 65.00, 30.00, 'plate', 1),
        (7, 'Cooking Oil', 3, 0.00, 45.00, 'ltr', 1) -- Ingredient only
    ");
    echo "✅ Products Created\n";

    // 10. SEED INVENTORY (Stock Up Locations)
    // Loc 3 (Warehouse) has bulk. Loc 2 (Bar) has stock. Loc 1 (Kitchen) has ingredients.
    $pdo->exec("
        INSERT INTO inventory (product_id, location_id, quantity) VALUES
        (1, 3, 1000), (1, 2, 50),  -- Coke
        (2, 3, 500),  (2, 2, 100), -- Mosi
        (3, 1, 20),                -- T-Bone (Kitchen)
        (4, 3, 10),   (4, 2, 2),   -- Jameson (Low Stock Alert!)
        (5, 3, 200),  (5, 2, 20),  -- Water
        (6, 1, 15),                -- Burger (Kitchen)
        (7, 3, 50),   (7, 1, 5)    -- Oil (Kitchen)
    ");
    echo "✅ Inventory Stocked\n";

    // 11. SEED MEMBERS (Loyalty)
    $pdo->exec("
        INSERT INTO members (name, phone, points_balance) VALUES
        ('John Doe', '0977000000', 50.00),
        ('Jane Smith', '0966000000', 120.00)
    ");
    echo "✅ Members Created\n";

    // 12. RE-ENABLE CHECKS
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n🎉 SEEDING COMPLETE! System is reset and ready for testing.\n";
    echo "👉 Login as 'admin' / '1234' to start.\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack Trace: " . $e->getTraceAsString() . "\n";
}
?>
