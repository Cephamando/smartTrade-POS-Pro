<?php
// Load configuration
require_once __DIR__ . '/config.php';

echo "🌱 STARTING DATABASE SEED...\n";

try {
    // 1. DISABLE FOREIGN KEY CHECKS
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. DEFINE TABLES TO TRUNCATE
    $tables = [
        'users', 'locations', 'categories', 'products', 'inventory', 
        'inventory_logs', 'sales', 'sale_items', 'members', 'shifts', 
        'inventory_transfers', 'grvs', 'grv_items', 'vendors', 
        'stock_transfers', 'stock_transfer_items', 'location_stock'
    ];

    // 3. TRUNCATE TABLES
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
        echo "   - Cleared table: $table\n";
    }

    // 4. SEED LOCATIONS
    $pdo->exec("
        INSERT INTO locations (id, name, type, can_sell, can_receive_from_vendor) VALUES
        (1, 'Main Kitchen', 'kitchen', 1, 0),
        (2, 'Main Bar', 'bar', 1, 0),
        (3, 'Main Warehouse', 'warehouse', 0, 1),
        (4, 'Restaurant Bar', 'bar', 1, 0)
    ");
    echo "✅ Locations Created\n";

    // 5. SEED USERS (Password: 1234)
    $pass = password_hash('1234', PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO users (username, full_name, password_hash, role, location_id) VALUES
        ('admin', 'System Admin', ?, 'admin', 3),
        ('manager', 'Bar Manager', ?, 'manager', 2),
        ('cashier', 'Bar Cashier', ?, 'cashier', 2),
        ('chef', 'Head Chef', ?, 'chef', 1)
    ")->execute([$pass, $pass, $pass, $pass]);
    echo "✅ Users Created (admin, manager, cashier, chef - Pass: 1234)\n";

    // 6. SEED CATEGORIES
    $pdo->exec("
        INSERT INTO categories (id, name, type) VALUES
        (1, 'Drinks', 'drink'),
        (2, 'Food', 'food'),
        (3, 'Ingredients', 'ingredients')
    ");

    // 7. SEED VENDORS (Required for GRVs)
    $pdo->exec("
        INSERT INTO vendors (id, name, contact_person) VALUES
        (1, 'Coca Cola Zambia', 'Mr. Phiri'),
        (2, 'Zambeef', 'Sales Rep')
    ");

    // 8. SEED PRODUCTS
    $pdo->exec("
        INSERT INTO products (id, name, category_id, price, cost_price, unit, is_active) VALUES
        (1, 'Coca Cola 300ml', 1, 15.00, 8.00, 'btl', 1),
        (2, 'Mosi Lager', 1, 25.00, 12.00, 'btl', 1),
        (3, 'Beef Burger', 2, 60.00, 35.00, 'plate', 1),
        (4, 'Jameson Shot', 1, 40.00, 15.00, 'tot', 1)
    ");
    echo "✅ Products Created\n";

    // 9. SEED INVENTORY (Main Warehouse & Bar)
    // Note: Jameson (ID 4) set to 2 to trigger LOW STOCK ALERT
    $pdo->exec("
        INSERT INTO inventory (product_id, location_id, quantity) VALUES
        (1, 3, 1000), (1, 2, 50),  -- Coke: 1000 Warehouse, 50 Bar
        (2, 3, 1000), (2, 2, 100), -- Mosi
        (3, 3, 500),  (3, 2, 20),  -- Burger
        (4, 3, 10),   (4, 2, 2)    -- Jameson (Low Stock)
    ");
    echo "✅ Inventory Stocked\n";

    // 10. RE-ENABLE CHECKS
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n🎉 SEEDING COMPLETE! Ready for UAT.\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
