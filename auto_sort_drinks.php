<?php
require_once 'src/config.php';

// Fetch the IDs of the new drink categories
$stmt = $pdo->query("SELECT id, name FROM categories WHERE type = 'drink' OR name IN ('Whiskey bottles', 'Whiskey tots', 'Ciders', 'Wines and creams', 'Softies', 'Lagers', 'Mixers', 'Mineral Water')");
$drinkCats = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $drinkCats[strtolower(trim($row['name']))] = $row['id'];
}

// Keyword mapping: If a product name contains these words, it moves to that category
$rules = [
    'lagers' => ['mosi', 'castle', 'black label', 'carling', 'heineken', 'amstel', 'windhoek', 'budweiser', 'stella', 'lager'],
    'ciders' => ['hunters', 'savanna', 'flying fish', 'bernini', 'strongbow', 'cider'],
    'softies' => ['coke', 'coca-cola', 'sprite', 'fanta', 'pepsi', 'mirinda', 'appletiser', 'grapetiser', 'juice'],
    'mixers' => ['tonic', 'soda water', 'ginger ale', 'lemonade', 'red bull', 'energy'],
    'mineral water' => ['water', 'manzi', 'aquasavanna', 'purified'],
    'wines and creams' => ['wine', 'merlot', 'shiraz', 'cabernet', 'sauvignon', 'chardonnay', 'amarula', 'baileys', 'cream', 'rose'],
    'whiskey bottles' => ['bottle'],
    'whiskey tots' => ['tot']
];

$count = 0;
try {
    foreach($rules as $catName => $keywords) {
        if(isset($drinkCats[$catName])) {
            $catId = $drinkCats[$catName];
            foreach($keywords as $kw) {
                // Update products that match the keyword
                $sql = "UPDATE products SET category_id = ? WHERE name LIKE ? AND type = 'item'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$kw%"]);
                $count += $stmt->rowCount();
            }
        }
    }
    echo "<h2 style='color:green;'>Success! $count items were automatically categorized.</h2>";
    echo "<p>You can delete 'auto_sort_drinks.php' from your folder now.</p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
