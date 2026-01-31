-- Adminer 5.4.1 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

USE `pos_db`;

SET NAMES utf8mb4;

TRUNCATE `categories`;
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Food', NULL),
(2, 'Meal', NULL),
(3, 'Beverages',  NULL),
(4, 'Ingredient', NULL);

TRUNCATE `expenses`;

TRUNCATE `grv_items`;
INSERT INTO `grv_items` (`id`, `grv_id`, `product_id`, `quantity`, `unit_cost`) VALUES
(1, 1,  1,  100.00, 130.00),
(2, 2,  2,  500.00, 750.00),
(3, 3,  1,  50.00,  130.00);

TRUNCATE `grvs`;
INSERT INTO `grvs` (`id`, `vendor_id`, `location_id`, `received_by`, `total_cost`, `reference_no`, `created_at`) VALUES
(1, 1,  1,  1,  13000.00, 'INV-001-300126', '2026-01-30 05:35:11'),
(2, 3,  3,  5,  375000.00,  '', '2026-01-30 07:16:02'),
(3, 1,  3,  5,  6500.00,  '', '2026-01-30 08:02:25');

TRUNCATE `inventory_transfers`;
INSERT INTO `inventory_transfers` (`id`, `source_location_id`, `dest_location_id`, `product_id`, `quantity`, `user_id`, `status`, `created_at`, `dispatched_at`, `received_at`) VALUES
(1, 7,  3,  2,  10.00,  5,  'cancelled',  '2026-01-30 09:54:39',  NULL, NULL),
(2, 3,  2,  1,  5.00, 8,  'completed',  '2026-01-30 10:01:12',  '2026-01-30 10:03:04',  '2026-01-30 10:04:33'),
(3, 3,  1,  2,  5.00, 4,  'completed',  '2026-01-30 10:05:13',  '2026-01-30 10:05:29',  '2026-01-30 10:07:22');

TRUNCATE `location_stock`;
INSERT INTO `location_stock` (`id`, `location_id`, `product_id`, `quantity`) VALUES
(1, 1,  1,  92.00),
(2, 3,  2,  455.00),
(3, 1,  2,  55.00),
(4, 3,  1,  55.00),
(6, 2,  1,  -6.00);

TRUNCATE `locations`;
INSERT INTO `locations` (`id`, `name`, `type`, `can_sell`, `can_receive_from_vendor`, `address`) VALUES
(1, 'Kitchen',  'store',  1,  0,  ''),
(2, 'Main Bar', 'store',  1,  0,  ''),
(3, 'Main Storeroom', 'warehouse',  1,  0,  ''),
(4, 'Restaurant Bar', 'store',  1,  0,  ''),
(5, 'Outside Bar',  'store',  1,  0,  ''),
(7, 'Mini storeroom', 'store',  1,  0,  '');

TRUNCATE `pickup_notifications`;
INSERT INTO `pickup_notifications` (`id`, `sale_id`, `item_name`, `status`, `collected_by`, `created_at`) VALUES
(1, 1,  'Beef Sausage', 'collected',  3,  '2026-01-30 07:00:08');

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`) VALUES
(1, 'Beef Sausage', NULL, 130.00, 100.00, 'Kg', 2,  1),
(2, 'Flour',  NULL, 750.00, 500.00, 'Kg', 4,  1),
(3, 'T-Bone Steak', NULL, 120.00, 0.00, 'unit', 2,  1),
(4, 'Mosi Larger',  NULL, 35.00,  20.00,  'ml', 3,  1),
(5, 'Amarulla', NULL, 134.00, 100.00, 'ml', 3,  1);

TRUNCATE `refund_requests`;

TRUNCATE `sale_items`;
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price_at_sale`, `cost_at_sale`, `status`) VALUES
(1, 1,  1,  1,  130.00, 0.00, 'served'),
(2, 2,  3,  1,  120.00, 0.00, 'served'),
(3, 3,  1,  1,  130.00, 0.00, 'served'),
(4, 3,  3,  1,  120.00, 0.00, 'served'),
(5, 4,  3,  1,  120.00, 0.00, 'served'),
(6, 5,  3,  1,  120.00, 0.00, 'served'),
(7, 6,  1,  1,  130.00, 0.00, 'served'),
(8, 7,  3,  3,  120.00, 0.00, 'served'),
(9, 8,  4,  1,  35.00,  0.00, 'served'),
(10,  8,  5,  2,  134.00, 0.00, 'served'),
(11,  8,  3,  1,  120.00, 0.00, 'served'),
(12,  8,  1,  1,  130.00, 0.00, 'served'),
(13,  9,  5,  1,  134.00, 0.00, 'served'),
(14,  10, 3,  1,  120.00, 0.00, 'served'),
(15,  11, 1,  5,  130.00, 0.00, 'pending'),
(16,  12, 1,  5,  130.00, 0.00, 'pending'),
(17,  12, 3,  5,  120.00, 0.00, 'pending'),
(18,  13, 1,  1,  130.00, 0.00, 'pending'),
(19,  14, 1,  1,  130.00, 0.00, 'pending'),
(20,  15, 3,  1,  120.00, 0.00, 'pending'),
(21,  16, 1,  1,  130.00, 0.00, 'pending'),
(22,  17, 3,  1,  120.00, 0.00, 'pending'),
(23,  18, 1,  1,  130.00, 0.00, 'pending'),
(24,  19, 1,  1,  130.00, 0.00, 'pending');

TRUNCATE `sales`;
INSERT INTO `sales` (`id`, `location_id`, `user_id`, `shift_id`, `total_amount`, `discount`, `total_tax`, `tip`, `final_total`, `payment_method`, `status`, `created_at`) VALUES
(1, 1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-30 06:09:19'),
(2, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:45:13'),
(3, 1,  4,  2,  250.00, 0.00, 0.00, 0.00, 250.00, 'cash', 'completed',  '2026-01-30 11:46:25'),
(4, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:48:08'),
(5, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:55:21'),
(6, 1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-30 11:59:26'),
(7, 1,  3,  1,  360.00, 0.00, 0.00, 0.00, 360.00, 'cash', 'completed',  '2026-01-30 18:07:09'),
(8, 2,  7,  5,  553.00, 0.00, 0.00, 0.00, 553.00, 'cash', 'completed',  '2026-01-31 03:56:30'),
(9, 2,  7,  5,  134.00, 0.00, 0.00, 0.00, 134.00, 'cash', 'completed',  '2026-01-31 04:03:30'),
(10,  2,  7,  5,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:03:39'),
(11,  2,  7,  5,  650.00, 0.00, 0.00, 0.00, 650.00, 'cash', 'completed',  '2026-01-31 04:05:40'),
(12,  2,  7,  5,  1250.00,  0.00, 0.00, 0.00, 1250.00,  'cash', 'completed',  '2026-01-31 04:06:53'),
(13,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:41'),
(14,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:45'),
(15,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:07:49'),
(16,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:53'),
(17,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:07:56'),
(18,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:08:01'),
(19,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:08:05');

TRUNCATE `shifts`;
INSERT INTO `shifts` (`id`, `user_id`, `location_id`, `start_time`, `end_time`, `starting_cash`, `closing_cash`, `expected_cash`, `manager_closing_cash`, `status`, `variance_reason`, `handover_notes`, `start_verified_by`, `start_verified_at`, `end_verified_by`, `end_verified_at`) VALUES
(1, 3,  1,  '2026-01-30 06:09:06',  NULL, 100.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 06:09:06',  NULL, NULL),
(2, 4,  1,  '2026-01-30 06:58:36',  NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 06:58:36',  NULL, NULL),
(3, 6,  1,  '2026-01-30 11:56:10',  NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 11:56:10',  NULL, NULL),
(4, 8,  2,  '2026-01-30 18:12:30',  NULL, 500.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 18:12:30',  NULL, NULL),
(5, 7,  2,  '2026-01-30 18:23:48',  NULL, 100.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 8,  '2026-01-30 18:23:48',  NULL, NULL);

TRUNCATE `stock_transfer_items`;
INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `product_id`, `quantity_requested`, `quantity_sent`, `quantity_received`) VALUES
(1, 1,  2,  50.00,  50.00,  50.00);

TRUNCATE `stock_transfers`;
INSERT INTO `stock_transfers` (`id`, `source_location_id`, `destination_location_id`, `user_id`, `status`, `created_at`) VALUES
(1, 3,  1,  5,  'completed',  '2026-01-30 07:16:26');

TRUNCATE `taxes`;

TRUNCATE `users`;
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`) VALUES
(1, 'odelia_admin', '$2y$10$Hf3oqWOf/u3p8mVDynHZp.Fr.9bgbxm6ptvrZCiqHEmSBs5MByTz2', 'dev',  3,  '2026-01-29 12:55:36',  0),
(3, 'cashier',  '$2y$10$Hf3oqWOf/u3p8mVDynHZp.Fr.9bgbxm6ptvrZCiqHEmSBs5MByTz2', 'cashier',  1,  '2026-01-29 12:55:36',  0),
(4, 'head_chef',  '$2y$10$LiKgNH0oFUoSHcZ.HcIcSOPBqGxJMmOCi4.NuhuCuPVnzrzJtr4W2', 'head_chef',  1,  '2026-01-30 05:37:27',  0),
(5, 'stores_manager', '$2y$10$Ag0AEaK7JwiDJqHQhzsMD.LYv9jXfJC0NyTHOaU/gPCZHBVwAC7Ze', 'manager',  3,  '2026-01-30 06:39:29',  0),
(6, 'chef', '$2y$10$ebU1RxFhof8h9wcL6KkWE.zyjD/1Fzs2hGOQsur/zwwk7l9hmKVLa', 'chef', 1,  '2026-01-30 06:40:05',  0),
(7, 'Main_bartender', '$2y$10$PA0I9uOWrx7OFbt9MN8nJesCb/zLwryHuZzZuW.AtX5H2rBaBKw1O', 'bartender',  2,  '2026-01-30 06:40:57',  0),
(8, 'Bar_Manager',  '$2y$10$nZHpDBrnEPQTQW/qnvOoeOIT2V97h9hF0JyXkc9KHgHQejL682/Cq', 'manager',  2,  '2026-01-30 07:59:03',  0),
(10,  'Daliso', '$2y$10$5q2OGn9TRgeDLgv2lfJ.jOf32Cx6T7/ysTn.i99Mqv8lIIWQujVGW', 'admin',  3,  '2026-01-30 18:19:15',  1);

TRUNCATE `vendors`;
INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`) VALUES
(1, 'Zambeef Zambia', 'Mr. Phir', ''),
(2, 'Coca Cola Zambia', 'Mr. Daliso', ''),
(3, 'Shoprite Zambia',  'Mr. Choogo', '');

-- 2026-01-31 04:20:22 UTC