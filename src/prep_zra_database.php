<?php
require_once 'config.php'; 

try {
    echo "<h3>ZRA Database Preparation</h3>";
    
    // 1. Upgrade the Products table
    $pdo->exec("ALTER TABLE products ADD COLUMN zra_tax_code VARCHAR(10) DEFAULT 'A'");
    $pdo->exec("ALTER TABLE products ADD COLUMN zra_unspsc_code VARCHAR(20) DEFAULT NULL");
    echo "<p style='color:green;'>✅ Products table upgraded successfully.</p>";

    // 2. Upgrade the Sales table
    $pdo->exec("ALTER TABLE sales ADD COLUMN zra_receipt_number VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE sales ADD COLUMN zra_status ENUM('pending', 'synced', 'failed', 'exempt') DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE sales ADD COLUMN zra_qr_code TEXT DEFAULT NULL");
    echo "<p style='color:green;'>✅ Sales table upgraded successfully.</p>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color:blue;'>ℹ️ Notice: Columns already exist. The database is already prepared!</p>";
    } else {
        echo "<p style='color:red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    }
}
?>
