<?php
require_once 'src/config.php';
$newCategories = ['Whiskey bottles', 'Whiskey tots', 'Ciders', 'Wines and creams', 'Softies', 'Lagers', 'Mixers', 'Mineral Water'];

try {
    $stmt = $pdo->prepare("INSERT INTO categories (name, type, is_active) SELECT ?, 'drink', 1 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = ?)");
    foreach ($newCategories as $catName) {
        $stmt->execute([$catName, $catName]);
    }
    echo "<h2 style='color: green;'>Success! Beverage categories safely added to the database.</h2>";
    echo "<p>You can now close this page and delete the 'patch_beverages_web.php' file.</p>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error: " . $e->getMessage() . "</h2>";
}
?>
