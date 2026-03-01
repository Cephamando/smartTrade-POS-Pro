<?php
require_once 'src/config.php';

try {
    // 1. Upgrade the table schema to support infinite parent-child nesting
    $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL AFTER description");
    $pdo->exec("ALTER TABLE categories ADD CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL");
} catch(Exception $e) { 
    // If it already exists, ignore and proceed
}

try {
    // 2. Ensure "Beverages" exists as the Master Category
    $stmt = $pdo->query("SELECT id FROM categories WHERE name = 'Beverages'");
    $bevId = $stmt->fetchColumn();
    
    if (!$bevId) {
        $pdo->exec("INSERT INTO categories (name, type, is_active) VALUES ('Beverages', 'drink', 1)");
        $bevId = $pdo->lastInsertId();
    }

    // 3. Bind your requested subcategories precisely under "Beverages"
    $subs = ['Whiskey bottles', 'Whiskey tots', 'Ciders', 'Wines and creams', 'Softies', 'Lagers', 'Mixers', 'Mineral Water'];
    $stmt = $pdo->prepare("UPDATE categories SET parent_id = ? WHERE name = ?");
    
    foreach($subs as $sub) {
        $stmt->execute([$bevId, $sub]);
    }
    
    echo "<h2 style='color:green;'>Success! Database schema upgraded. Categories are now truly hierarchical.</h2>";
    echo "<p>You can now close this page.</p>";
} catch(Exception $e) { 
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>"; 
}
?>
