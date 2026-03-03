-- Disable foreign key checks to allow wiping linked tables
SET FOREIGN_KEY_CHECKS = 0;

-- ====================================================
-- 1. WIPE ALL SALES & FINANCIAL TRANSACTIONS
-- ====================================================
TRUNCATE TABLE `sale_items`;
TRUNCATE TABLE `sales`;
TRUNCATE TABLE `pickup_notifications`;
TRUNCATE TABLE `refund_requests`;
TRUNCATE TABLE `refunds`;
TRUNCATE TABLE `expenses`;
TRUNCATE TABLE `daily_closures`;
TRUNCATE TABLE `shifts`;

-- ====================================================
-- 2. WIPE ALL INVENTORY, TRANSFERS & RECEIVING (GRVs)
-- ====================================================
TRUNCATE TABLE `inventory`;
TRUNCATE TABLE `inventory_logs`;
TRUNCATE TABLE `location_stock`;
TRUNCATE TABLE `inventory_transfers`;
TRUNCATE TABLE `stock_transfer_items`;
TRUNCATE TABLE `stock_transfers`;
TRUNCATE TABLE `transfers`;
TRUNCATE TABLE `grv_items`;
TRUNCATE TABLE `grvs`;

-- ====================================================
-- 3. WIPE THE OLD CATALOG (PRODUCTS & RECIPES)
-- ====================================================
TRUNCATE TABLE `product_recipes`;
TRUNCATE TABLE `products`;

-- ====================================================
-- 4. WIPE OLD CATEGORIES 
-- (The CSV import will auto-build the new exact list)
-- ====================================================
TRUNCATE TABLE `categories`;

-- Re-enable foreign key security checks
SET FOREIGN_KEY_CHECKS = 1;