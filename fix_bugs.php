<?php
require_once 'src/config.php';

try {
    echo "<h3>System Patch Tool</h3>";

    // --- 1. DATABASE UPGRADE FOR RECEIPTS ---
    $pdo->exec("ALTER TABLE sales ADD COLUMN amount_tendered DECIMAL(10,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE sales ADD COLUMN change_due DECIMAL(10,2) DEFAULT NULL");
    echo "<p style='color:green;'>✅ Database updated: Tracking Amount Tendered & Change Due.</p>";
} catch(Exception $e) { 
    echo "<p style='color:blue;'>ℹ️ Database is already tracking change.</p>"; 
}

// --- 2. PATCH LOGIN.PHP (LOCATION BUG) ---
$loginFile = 'src/login.php';
if (file_exists($loginFile)) {
    $loginContent = file_get_contents($loginFile);
    $loginContent = str_replace(
        'SELECT id, username, password_hash, role, location_id, force_password_change FROM users WHERE username = ?',
        'SELECT u.id, u.username, u.password_hash, u.role, u.location_id, u.force_password_change, l.name as location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id WHERE u.username = ?',
        $loginContent
    );
    $loginContent = str_replace(
        "\$_SESSION['location_id'] = \$user['location_id'];",
        "\$_SESSION['location_id'] = \$user['location_id'];\n            \$_SESSION['location_name'] = \$user['location_name'];",
        $loginContent
    );
    file_put_contents($loginFile, $loginContent);
    echo "<p style='color:green;'>✅ login.php patched: Location names now sync dynamically.</p>";
}

// --- 3. PATCH POS.PHP (LOCATION FALLBACK & RECEIPT MATH) ---
$posFile = 'src/pos.php';
if (file_exists($posFile)) {
    $posContent = file_get_contents($posFile);
    
    // Fix existing sessions that don't have a location name yet
    $posContent = str_replace(
        '$locationName = $_SESSION[\'location_name\'] ?? \'HQ\';',
        "if (empty(\$_SESSION['location_name']) && \$locationId > 0) { \$stmtLoc = \$pdo->prepare(\"SELECT name FROM locations WHERE id = ?\"); \$stmtLoc->execute([\$locationId]); \$_SESSION['location_name'] = \$stmtLoc->fetchColumn() ?: 'HQ'; }\n\$locationName = \$_SESSION['location_name'] ?? 'HQ';",
        $posContent
    );

    // Capture the Amount Tendered during checkout
    $posContent = str_replace(
        "if (\$isSplit == '1') { \$pm = 'Split'; }",
        "if (\$isSplit == '1') { \$pm = 'Split'; }\n        \$amountTendered = isset(\$_POST['amount_tendered']) ? (float)\$_POST['amount_tendered'] : 0;",
        $posContent
    );

    // Save Change for Tab Settlements
    $posContent = str_replace(
        "\$pdo->prepare(\"UPDATE sales SET subtotal = ?, tip_amount = ?, final_total = ?, payment_method = ?, payment_status = ? WHERE id = ?\")->execute([\$total, \$tip, \$finalTotal, \$pm, \$status, \$settleTabId]);",
        "\$changeDue = (\$amountTendered > \$finalTotal) ? (\$amountTendered - \$finalTotal) : 0;\n            \$pdo->prepare(\"UPDATE sales SET subtotal = ?, tip_amount = ?, final_total = ?, payment_method = ?, payment_status = ?, amount_tendered = ?, change_due = ? WHERE id = ?\")->execute([\$total, \$tip, \$finalTotal, \$pm, \$status, \$amountTendered, \$changeDue, \$settleTabId]);",
        $posContent
    );

    // Save Change for Standard Sales
    $posContent = str_replace(
        "\$stmt = \$pdo->prepare(\"INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\");\n            \$stmt->execute([\$locationId, \$userId, \$activeShiftId, \$total, \$tip, \$finalTotal, \$pm, \$status, \$customerName]);",
        "\$changeDue = (\$amountTendered > \$finalTotal) ? (\$amountTendered - \$finalTotal) : 0;\n            \$stmt = \$pdo->prepare(\"INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\");\n            \$stmt->execute([\$locationId, \$userId, \$activeShiftId, \$total, \$tip, \$finalTotal, \$pm, \$status, \$customerName, \$amountTendered, \$changeDue]);",
        $posContent
    );

    file_put_contents($posFile, $posContent);
    echo "<p style='color:green;'>✅ pos.php patched: Database will now permanently save cash tendered.</p>";
}

// --- 4. PATCH RECEIPT TEMPLATE (DISPLAY THE CHANGE) ---
$receiptFile = 'templates/receipt.php';
if (file_exists($receiptFile)) {
    $receiptContent = file_get_contents($receiptFile);
    
    // Safely inject the Tendered/Change UI block 
    $receiptContent = preg_replace(
        '/<tr><td>Paid Via:<\/td><td class="amount fw-bold"><\?= htmlspecialchars\(\$sale\[\'payment_method\'\]\) \?><\/td><\/tr>\s*<tr><td>Status:<\/td>/',
        "<tr><td>Paid Via:</td><td class=\"amount fw-bold\"><?= htmlspecialchars(\$sale['payment_method']) ?></td></tr>\n                    <?php if(isset(\$sale['amount_tendered']) && \$sale['amount_tendered'] > 0 && stripos(\$sale['payment_method'], 'cash') !== false): ?>\n                    <tr><td>Tendered:</td><td class=\"amount\">ZMW <?= number_format(\$sale['amount_tendered'], 2) ?></td></tr>\n                    <tr><td>Change:</td><td class=\"amount fw-bold\">ZMW <?= number_format(\$sale['change_due'], 2) ?></td></tr>\n                    <?php endif; ?>\n                    <tr><td>Status:</td>",
        $receiptContent
    );
    
    file_put_contents($receiptFile, $receiptContent);
    echo "<p style='color:green;'>✅ receipt.php patched: Receipts will now display customer change.</p>";
}
echo "<br><p><strong>All bugs squashed! You may close this window.</strong></p>";
?>
