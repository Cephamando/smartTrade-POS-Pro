<?php
// SECURITY: Only Managers/Admins/Devs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// Ensure we have a location to attach stock to
$userLocId = $_SESSION['location_id'] ?? null;
if (!$userLocId) {
    die("Error: User location not set.");
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
            $catId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $price = !empty($_POST['price']) ? $_POST['price'] : 0;
            $cost = !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0;
            $unit = !empty($_POST['unit']) ? $_POST['unit'] : 'unit';
            $taxClass = !empty($_POST['tax_class']) ? $_POST['tax_class'] : 'A';
            $unspscCode = !empty($_POST['unspsc_code']) ? trim($_POST['unspsc_code']) : null;
            
            // Safe SKU handling
            $skuRaw = $_POST['sku'] ?? '';
            $sku = trim($skuRaw);
            $sku = $sku === '' ? null : $sku; // Convert empty string to NULL for DB unique constraint
            
            // Basic Duplicate Check on Name
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) throw new Exception("A product with this name already exists.");

            // Use Transaction for Data Integrity
            $pdo->beginTransaction();

            // A. Insert Product
            $sql = "INSERT INTO products (name, category_id, price, cost_price, unit, sku, tax_class, unspsc_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $catId, $price, $cost, $unit, $sku, $taxClass, $unspscCode]);
            
            $newProdId = $pdo->lastInsertId();

            // B. Initialize Stock for Current Location (Crucial step!)
            $stockSql = "INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)";
            $stockStmt = $pdo->prepare($stockSql);
            $stockStmt->execute([$userLocId, $newProdId]);

            $pdo->commit();

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product '$name' saved successfully.";
        }

        // 3. IMPORT CSV
        if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
            if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['csv_file']['tmp_name'];
                if (($handle = fopen($tmpName, "r")) !== FALSE) {
                    
                    // Skip the header row
                    $headers = fgetcsv($handle, 1000, ",");
                    
                    $added = 0;
                    $updated = 0;
                    
                    $pdo->beginTransaction();
                    try {
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            
                            // Map columns based on our expected format
                            $name = trim($data[0] ?? '');
                            if (empty($name)) continue; // Skip blank rows
                            
                            $categoryName = trim($data[1] ?? '');
                            $skuRaw = trim($data[2] ?? '');
                            $sku = $skuRaw === '' ? null : $skuRaw;
                            $unit = trim($data[3] ?? 'unit');
                            $costPrice = (float)($data[4] ?? 0);
                            $price = (float)($data[5] ?? 0);
                            $taxClass = trim($data[6] ?? 'A');
                            if (!in_array($taxClass, ['A', 'B', 'C', 'D'])) $taxClass = 'A';
                            $unspscRaw = trim($data[7] ?? '');
                            $unspscCode = $unspscRaw === '' ? null : $unspscRaw;
                            
                            // 1. Resolve Category ID (Auto-create if missing)
                            $catId = null;
                            if (!empty($categoryName)) {
                                $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                                $catStmt->execute([$categoryName]);
                                if ($catStmt->rowCount() > 0) {
                                    $catId = $catStmt->fetchColumn();
                                } else {
                                    $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$categoryName]);
                                    $catId = $pdo->lastInsertId();
                                }
                            }
                            
                            // 2. Check for existing product (By SKU first, then Name)
                            $prodId = null;
                            if ($sku) {
                                $checkSku = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
                                $checkSku->execute([$sku]);
                                $prodId = $checkSku->fetchColumn();
                            }
                            if (!$prodId) {
                                $checkName = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                                $checkName->execute([$name]);
                                $prodId = $checkName->fetchColumn();
                            }
                            
                            // 3. Update or Insert
                            if ($prodId) {
                                // Exists: Update the details
                                $updateSql = "UPDATE products SET category_id=?, cost_price=?, price=?, unit=?, sku=?, tax_class=?, unspsc_code=? WHERE id=?";
                                $pdo->prepare($updateSql)->execute([$catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode, $prodId]);
                                $updated++;
                            } else {
                                // New: Insert product
                                $insertSql = "INSERT INTO products (name, category_id, cost_price, price, unit, sku, tax_class, unspsc_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                                $pdo->prepare($insertSql)->execute([$name, $catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode]);
                                $newProdId = $pdo->lastInsertId();
                                
                                // Securely attach it to the current location's stock inventory
                                $stockSql = "INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)";
                                $pdo->prepare($stockSql)->execute([$userLocId, $newProdId]);
                                $added++;
                            }
                        }
                        $pdo->commit();
                        $_SESSION['swal_type'] = 'success';
                        $_SESSION['swal_msg'] = "Import complete! Added: $added, Updated: $updated.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw new Exception("Import Failed: " . $e->getMessage());
                    }
                    fclose($handle);
                } else {
                    throw new Exception("Failed to read the uploaded CSV file.");
                }
            } else {
                throw new Exception("Upload error. Please check your file and try again.");
            }
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // PRG Redirect
    header("Location: index.php?page=products");
    exit;
}

// FETCH DATA FOR VIEW
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch products with their category names
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC";
$products = $pdo->query($sql)->fetchAll();
?>
