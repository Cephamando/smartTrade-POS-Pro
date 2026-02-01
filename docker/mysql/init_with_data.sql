-- Adminer 5.4.1 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

USE `pos_db`;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('food','drink','meal','ingredients','other') NOT NULL DEFAULT 'other',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `categories`;
INSERT INTO `categories` (`id`, `name`, `description`, `type`) VALUES
(1, 'Food', NULL, 'food'),
(2, 'Meal', NULL, 'meal'),
(3, 'Beverages',  NULL, 'drink'),
(4, 'Ingredient', NULL, 'ingredients');

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `description` varchar(255) NOT NULL DEFAULT 'Expense',
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `expenses`;

DROP TABLE IF EXISTS `grv_items`;
CREATE TABLE `grv_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grv_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `grv_id` (`grv_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `grv_items_ibfk_1` FOREIGN KEY (`grv_id`) REFERENCES `grvs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grv_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `grv_items`;
INSERT INTO `grv_items` (`id`, `grv_id`, `product_id`, `quantity`, `unit_cost`) VALUES
(1, 1,  1,  100.00, 130.00),
(2, 2,  2,  500.00, 750.00),
(3, 3,  1,  50.00,  130.00),
(4, 4,  3,  100.00, 130.00),
(5, 5,  5,  100.00, 50.00),
(6, 6,  4,  60.00,  35.00),
(7, 7,  6,  50.00,  90.00),
(8, 8,  6,  100.00, 90.00);

DROP TABLE IF EXISTS `grvs`;
CREATE TABLE `grvs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `location_id` int NOT NULL,
  `received_by` int NOT NULL,
  `total_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reference_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `location_id` (`location_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `grvs_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `grvs_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `grvs_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `grvs`;
INSERT INTO `grvs` (`id`, `vendor_id`, `location_id`, `received_by`, `total_cost`, `reference_no`, `created_at`) VALUES
(1, 1,  1,  1,  13000.00, 'INV-001-300126', '2026-01-30 05:35:11'),
(2, 3,  3,  5,  375000.00,  '', '2026-01-30 07:16:02'),
(3, 1,  3,  5,  6500.00,  '', '2026-01-30 08:02:25'),
(4, 1,  3,  5,  13000.00, '', '2026-01-31 12:38:16'),
(5, 2,  3,  5,  5000.00,  '', '2026-01-31 13:38:05'),
(6, 3,  3,  5,  2100.00,  '', '2026-01-31 13:38:25'),
(7, 3,  9,  1,  4500.00,  '', '2026-01-31 16:36:39'),
(8, 3,  3,  5,  9000.00,  '', '2026-01-31 16:40:11');

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `location_id` int NOT NULL,
  `quantity` int DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_stock` (`product_id`,`location_id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `inventory`;
INSERT INTO `inventory` (`id`, `product_id`, `location_id`, `quantity`, `updated_at`) VALUES
(1, 1,  1,  88, '2026-02-01 07:50:29'),
(2, 2,  3,  455,  '2026-02-01 05:31:42'),
(3, 2,  1,  54, '2026-02-01 05:31:42'),
(4, 1,  3,  45, '2026-02-01 14:53:17'),
(5, 1,  2,  0,  '2026-02-01 05:31:42'),
(6, 3,  3,  90, '2026-02-01 05:38:15'),
(7, 3,  1,  5,  '2026-02-01 05:31:42'),
(8, 5,  3,  120,  '2026-02-01 05:31:42'),
(9, 4,  3,  85, '2026-02-01 05:31:42'),
(10,  5,  1,  7,  '2026-02-01 06:02:17'),
(11,  4,  1,  8,  '2026-02-01 07:24:19'),
(12,  6,  9,  110,  '2026-02-01 17:31:59'),
(13,  6,  3,  90, '2026-02-01 15:00:17'),
(14,  6,  1,  16, '2026-02-01 06:07:13'),
(15,  1,  4,  15, '2026-02-01 14:53:24'),
(16,  4,  4,  9,  '2026-02-01 13:09:08'),
(17,  6,  7,  30, '2026-02-01 15:00:47'),
(18,  6,  4,  13, '2026-02-01 18:10:40'),
(19,  5,  4,  8,  '2026-02-01 12:58:46'),
(32,  3,  4,  14, '2026-02-01 12:43:20');

DROP TABLE IF EXISTS `inventory_logs`;
CREATE TABLE `inventory_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `change_qty` decimal(10,2) NOT NULL,
  `after_qty` decimal(10,2) NOT NULL,
  `action_type` enum('sale','grv','transfer_in','transfer_out','adjustment') NOT NULL,
  `reference_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `location_id` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `inventory_logs`;
INSERT INTO `inventory_logs` (`id`, `product_id`, `location_id`, `user_id`, `change_qty`, `after_qty`, `action_type`, `reference_id`, `created_at`) VALUES
(1, 1,  4,  12, -2.00,  1.00, 'sale', 50, '2026-02-01 12:21:59'),
(2, 1,  4,  12, -1.00,  0.00, 'sale', 51, '2026-02-01 12:43:20'),
(3, 5,  4,  12, -1.00,  9.00, 'sale', 51, '2026-02-01 12:43:20'),
(4, 4,  4,  12, -1.00,  10.00,  'sale', 51, '2026-02-01 12:43:20'),
(5, 3,  4,  12, -2.00,  14.00,  'sale', 51, '2026-02-01 12:43:20'),
(6, 1,  3,  5,  -10.00, 55.00,  'transfer_out', 17, '2026-02-01 12:53:48'),
(7, 1,  4,  15, 10.00,  10.00,  'transfer_in',  17, '2026-02-01 12:54:59'),
(8, 1,  4,  15, -1.00,  9.00, 'sale', 52, '2026-02-01 12:58:46'),
(9, 5,  4,  15, -1.00,  8.00, 'sale', 52, '2026-02-01 12:58:46'),
(10,  1,  4,  15, -1.00,  8.00, 'sale', 53, '2026-02-01 13:00:05'),
(11,  6,  4,  15, -1.00,  17.00,  'sale', 49, '2026-02-01 13:02:33'),
(12,  6,  4,  15, -1.00,  16.00,  'sale', 55, '2026-02-01 13:04:06'),
(13,  1,  4,  15, -1.00,  7.00, 'sale', 54, '2026-02-01 13:04:25'),
(14,  4,  4,  15, -1.00,  9.00, 'sale', 56, '2026-02-01 13:09:08'),
(15,  1,  4,  15, -2.00,  5.00, 'sale', 57, '2026-02-01 14:28:10'),
(16,  1,  3,  1,  -10.00, 45.00,  'transfer_out', 18, '2026-02-01 14:53:17'),
(17,  1,  4,  1,  10.00,  15.00,  'transfer_in',  18, '2026-02-01 14:53:24'),
(18,  6,  9,  1,  -50.00, 0.00, 'transfer_out', 19, '2026-02-01 14:55:17'),
(19,  6,  7,  14, 50.00,  80.00,  'transfer_in',  19, '2026-02-01 14:55:37'),
(20,  6,  3,  1,  -10.00, 140.00, 'transfer_out', 20, '2026-02-01 14:56:19'),
(21,  6,  9,  1,  10.00,  10.00,  'transfer_in',  20, '2026-02-01 14:56:30'),
(22,  6,  3,  1,  -50.00, 90.00,  'transfer_out', 22, '2026-02-01 15:00:17'),
(23,  6,  9,  1,  50.00,  60.00,  'transfer_in',  22, '2026-02-01 15:00:28'),
(24,  6,  7,  1,  -50.00, 30.00,  'transfer_out', 23, '2026-02-01 15:00:47'),
(25,  6,  4,  12, -1.00,  15.00,  'sale', 58, '2026-02-01 16:09:35'),
(26,  6,  9,  1,  50.00,  110.00, 'transfer_in',  23, '2026-02-01 17:31:59'),
(27,  6,  4,  1,  -2.00,  13.00,  'sale', 59, '2026-02-01 18:10:40');

DROP TABLE IF EXISTS `inventory_transfers`;
CREATE TABLE `inventory_transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source_location_id` int NOT NULL,
  `dest_location_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `inventory_transfers`;
INSERT INTO `inventory_transfers` (`id`, `source_location_id`, `dest_location_id`, `product_id`, `quantity`, `user_id`, `status`, `created_at`, `dispatched_at`, `received_at`) VALUES
(1, 7,  3,  2,  10.00,  5,  'cancelled',  '2026-01-30 09:54:39',  NULL, NULL),
(2, 3,  2,  1,  5.00, 8,  'completed',  '2026-01-30 10:01:12',  '2026-01-30 10:03:04',  '2026-01-30 10:04:33'),
(3, 3,  1,  2,  5.00, 4,  'completed',  '2026-01-30 10:05:13',  '2026-01-30 10:05:29',  '2026-01-30 10:07:22'),
(4, 3,  1,  3,  10.00,  4,  'completed',  '2026-01-31 14:36:15',  '2026-01-31 14:38:39',  '2026-01-31 14:39:13'),
(5, 3,  1,  5,  10.00,  4,  'completed',  '2026-01-31 15:41:05',  '2026-01-31 15:42:06',  '2026-01-31 15:42:46'),
(6, 3,  1,  4,  10.00,  4,  'completed',  '2026-01-31 15:41:18',  '2026-01-31 15:42:13',  '2026-01-31 15:42:49'),
(7, 3,  1,  6,  20.00,  3,  'completed',  '2026-01-31 18:32:40',  '2026-01-31 18:40:28',  '2026-01-31 18:40:47'),
(8, 1,  4,  1,  3.00, 12, 'completed',  '2026-01-31 18:59:12',  '2026-01-31 18:59:51',  '2026-01-31 19:02:22'),
(9, 3,  4,  4,  5.00, 12, 'completed',  '2026-01-31 18:59:37',  '2026-01-31 19:00:10',  '2026-01-31 19:02:24'),
(10,  7,  4,  6,  10.00,  4,  'completed',  '2026-02-01 06:10:34',  '2026-02-01 06:16:06',  '2026-02-01 06:16:23'),
(11,  3,  7,  6,  20.00,  14, 'completed',  '2026-02-01 06:15:07',  '2026-02-01 06:15:42',  '2026-02-01 06:16:01'),
(12,  3,  4,  5,  10.00,  15, 'completed',  '2026-02-01 06:30:14',  '2026-02-01 06:31:05',  '2026-02-01 06:31:37'),
(13,  3,  4,  4,  10.00,  15, 'completed',  '2026-02-01 06:30:33',  '2026-02-01 06:31:10',  '2026-02-01 06:31:39'),
(14,  3,  4,  1,  10.00,  15, 'completed',  '2026-02-01 06:30:44',  '2026-02-01 06:31:13',  '2026-02-01 06:31:41'),
(15,  3,  4,  6,  10.00,  15, 'completed',  '2026-02-01 07:26:25',  '2026-02-01 07:26:55',  '2026-02-01 07:27:37'),
(16,  3,  4,  3,  20.00,  15, 'completed',  '2026-02-01 07:37:54',  '2026-02-01 07:38:15',  '2026-02-01 07:38:31'),
(17,  3,  4,  1,  10.00,  12, 'completed',  '2026-02-01 14:22:03',  '2026-02-01 14:53:48',  '2026-02-01 14:54:59'),
(18,  3,  4,  1,  10.00,  15, 'completed',  '2026-02-01 14:54:16',  '2026-02-01 16:53:17',  '2026-02-01 16:53:24'),
(19,  9,  7,  6,  50.00,  14, 'completed',  '2026-02-01 16:55:00',  '2026-02-01 16:55:17',  '2026-02-01 16:55:37'),
(20,  3,  9,  6,  10.00,  1,  'completed',  '2026-02-01 16:55:55',  '2026-02-01 16:56:19',  '2026-02-01 16:56:30'),
(21,  9,  7,  6,  50.00,  14, 'cancelled',  '2026-02-01 16:57:17',  NULL, NULL),
(22,  3,  9,  6,  50.00,  1,  'completed',  '2026-02-01 17:00:03',  '2026-02-01 17:00:17',  '2026-02-01 17:00:28'),
(23,  7,  9,  6,  50.00,  1,  'completed',  '2026-02-01 17:00:43',  '2026-02-01 17:00:47',  '2026-02-01 19:31:59'),
(24,  3,  2,  1,  10.00,  8,  'pending',  '2026-02-01 19:33:08',  NULL, NULL);

DROP TABLE IF EXISTS `location_stock`;
CREATE TABLE `location_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `loc_prod_unique` (`location_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `location_stock_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `location_stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `location_stock`;
INSERT INTO `location_stock` (`id`, `location_id`, `product_id`, `quantity`) VALUES
(1, 1,  1,  92.00),
(2, 3,  2,  455.00),
(3, 1,  2,  54.00),
(4, 3,  1,  65.00),
(6, 2,  1,  0.00),
(9, 3,  3,  110.00),
(11,  1,  3,  5.00),
(12,  3,  5,  120.00),
(13,  3,  4,  85.00),
(16,  1,  5,  8.00),
(17,  1,  4,  10.00),
(18,  9,  6,  50.00),
(19,  3,  6,  150.00),
(21,  1,  6,  17.00),
(24,  4,  1,  12.00),
(25,  4,  4,  14.00),
(27,  7,  6,  30.00),
(29,  4,  6,  20.00),
(33,  4,  5,  10.00);

DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` enum('store','kitchen','bar','warehouse') NOT NULL DEFAULT 'store',
  `can_sell` tinyint(1) DEFAULT '1',
  `can_receive_from_vendor` tinyint(1) DEFAULT '0',
  `address` text,
  `phone` varchar(50) DEFAULT '555-0000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `locations`;
INSERT INTO `locations` (`id`, `name`, `type`, `can_sell`, `can_receive_from_vendor`, `address`, `phone`) VALUES
(1, 'Kitchen',  'store',  1,  0,  '', '555-0000'),
(2, 'Main Bar', 'store',  1,  0,  '', '555-0000'),
(3, 'Main Storeroom', 'warehouse',  1,  0,  '', '555-0000'),
(4, 'Restaurant Bar', 'store',  1,  0,  '', '555-0000'),
(5, 'Outside Bar',  'store',  1,  0,  '', '555-0000'),
(7, 'Mini storeroom', 'store',  1,  0,  '', '555-0000'),
(8, 'Warehouse',  'warehouse',  1,  0,  '', '555-0000'),
(9, 'Main Branch',  'warehouse',  1,  0,  '', '555-0000'),
(12,  'shop', 'store',  1,  0,  '', '555-0000');

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `points_balance` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `members`;
INSERT INTO `members` (`id`, `name`, `phone`, `email`, `points_balance`, `created_at`) VALUES
(1, 'Daliso Nindi', '0208247',  'daliso@mymail.com',  15.00,  '2026-02-01 17:44:43');

DROP TABLE IF EXISTS `pickup_notifications`;
CREATE TABLE `pickup_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `status` enum('ready','collected') DEFAULT 'ready',
  `collected_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `pickup_notifications`;
INSERT INTO `pickup_notifications` (`id`, `sale_id`, `item_name`, `status`, `collected_by`, `created_at`) VALUES
(1, 1,  'Beef Sausage', 'collected',  3,  '2026-01-30 07:00:08');

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `unit` varchar(20) DEFAULT 'unit',
  `category_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`) VALUES
(1, 'Beef Sausage', NULL, 130.00, 100.00, 'Kg', 2,  1),
(2, 'Flour',  NULL, 750.00, 500.00, 'Kg', 4,  1),
(3, 'T-Bone Steak', NULL, 120.00, 0.00, 'unit', 2,  1),
(4, 'Mosi Larger',  NULL, 35.00,  20.00,  'ml', 3,  1),
(5, 'Amarulla', NULL, 134.00, 100.00, 'ml', 3,  1),
(6, 'chicken wrap', NULL, 150.00, 0.00, 'unit', 2,  1);

DROP TABLE IF EXISTS `refund_requests`;
CREATE TABLE `refund_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `requested_by_user_id` int NOT NULL,
  `reason` text,
  `refund_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `requested_by_user_id` (`requested_by_user_id`),
  CONSTRAINT `refund_requests_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `refund_requests_ibfk_2` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `refund_requests`;

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_at_sale` decimal(10,2) NOT NULL,
  `cost_at_sale` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','cooking','ready','served') DEFAULT 'pending',
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `sale_items`;
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price_at_sale`, `cost_at_sale`, `status`, `updated_at`) VALUES
(1, 1,  1,  1,  130.00, 0.00, 'served', NULL),
(2, 2,  3,  1,  120.00, 0.00, 'served', NULL),
(3, 3,  1,  1,  130.00, 0.00, 'served', NULL),
(4, 3,  3,  1,  120.00, 0.00, 'served', NULL),
(5, 4,  3,  1,  120.00, 0.00, 'served', NULL),
(6, 5,  3,  1,  120.00, 0.00, 'served', NULL),
(7, 6,  1,  1,  130.00, 0.00, 'served', NULL),
(8, 7,  3,  3,  120.00, 0.00, 'served', NULL),
(9, 8,  4,  1,  35.00,  0.00, 'served', NULL),
(10,  8,  5,  2,  134.00, 0.00, 'served', NULL),
(11,  8,  3,  1,  120.00, 0.00, 'served', NULL),
(12,  8,  1,  1,  130.00, 0.00, 'served', NULL),
(13,  9,  5,  1,  134.00, 0.00, 'served', NULL),
(14,  10, 3,  1,  120.00, 0.00, 'served', NULL),
(15,  11, 1,  5,  130.00, 0.00, 'served', NULL),
(16,  12, 1,  5,  130.00, 0.00, 'served', NULL),
(17,  12, 3,  5,  120.00, 0.00, 'served', NULL),
(18,  13, 1,  1,  130.00, 0.00, 'served', NULL),
(19,  14, 1,  1,  130.00, 0.00, 'served', NULL),
(20,  15, 3,  1,  120.00, 0.00, 'served', NULL),
(21,  16, 1,  1,  130.00, 0.00, 'served', NULL),
(22,  17, 3,  1,  120.00, 0.00, 'served', NULL),
(23,  18, 1,  1,  130.00, 0.00, 'served', NULL),
(24,  19, 1,  1,  130.00, 0.00, 'served', NULL),
(25,  20, 3,  1,  120.00, 0.00, 'served', NULL),
(26,  21, 1,  1,  130.00, 0.00, 'served', NULL),
(27,  22, 3,  1,  120.00, 0.00, 'served', NULL),
(28,  23, 3,  1,  120.00, 0.00, 'served', NULL),
(29,  23, 1,  1,  130.00, 0.00, 'served', NULL),
(30,  24, 5,  1,  134.00, 0.00, 'served', NULL),
(31,  24, 3,  1,  120.00, 0.00, 'served', NULL),
(32,  25, 3,  1,  120.00, 0.00, 'served', NULL),
(33,  25, 5,  1,  134.00, 0.00, 'served', NULL),
(34,  26, 1,  1,  130.00, 0.00, 'served', NULL),
(35,  27, 6,  1,  150.00, 0.00, 'served', NULL),
(36,  28, 6,  1,  150.00, 0.00, 'served', NULL),
(37,  29, 6,  1,  150.00, 0.00, 'served', NULL),
(38,  30, 2,  1,  750.00, 0.00, 'served', NULL),
(39,  31, 4,  1,  35.00,  0.00, 'served', NULL),
(40,  31, 1,  1,  130.00, 0.00, 'served', NULL),
(44,  32, 6,  1,  150.00, 0.00, 'served', NULL),
(45,  33, 1,  1,  130.00, 0.00, 'served', NULL),
(48,  35, 5,  1,  134.00, 0.00, 'served', NULL),
(49,  35, 1,  1,  130.00, 0.00, 'served', '2026-02-01 06:06:53'),
(50,  36, 6,  1,  150.00, 0.00, 'served', '2026-02-01 06:08:37'),
(51,  36, 4,  1,  35.00,  0.00, 'served', NULL),
(54,  38, 3,  1,  120.00, 0.00, 'served', NULL),
(55,  34, 1,  1,  130.00, 0.00, 'served', NULL),
(56,  34, 4,  1,  35.00,  0.00, 'served', NULL),
(57,  37, 1,  1,  130.00, 0.00, 'served', NULL),
(61,  40, 4,  1,  35.00,  0.00, 'pending',  NULL),
(62,  41, 1,  1,  130.00, 0.00, 'served', NULL),
(63,  42, 1,  1,  130.00, 0.00, 'served', NULL),
(65,  44, 1,  1,  130.00, 0.00, 'served', NULL),
(67,  45, 3,  1,  120.00, 0.00, 'served', NULL),
(69,  47, 3,  1,  120.00, 0.00, 'served', NULL),
(70,  48, 3,  1,  120.00, 0.00, 'served', NULL),
(71,  48, 1,  1,  130.00, 0.00, 'served', NULL),
(72,  46, 1,  1,  130.00, 0.00, 'served', NULL),
(73,  39, 6,  1,  150.00, 0.00, 'served', NULL),
(74,  39, 1,  1,  130.00, 0.00, 'served', NULL),
(75,  39, 4,  1,  35.00,  0.00, 'pending',  NULL),
(76,  43, 1,  1,  130.00, 0.00, 'served', NULL),
(78,  50, 1,  2,  130.00, 0.00, 'served', NULL),
(83,  51, 1,  1,  130.00, 0.00, 'pending',  NULL),
(84,  51, 5,  1,  134.00, 0.00, 'pending',  NULL),
(85,  51, 4,  1,  35.00,  0.00, 'pending',  NULL),
(86,  51, 3,  2,  120.00, 0.00, 'pending',  NULL),
(89,  52, 1,  1,  130.00, 0.00, 'pending',  NULL),
(90,  52, 5,  1,  134.00, 0.00, 'pending',  NULL),
(91,  53, 1,  1,  130.00, 0.00, 'pending',  NULL),
(93,  49, 6,  1,  150.00, 0.00, 'pending',  NULL),
(94,  55, 6,  1,  150.00, 0.00, 'pending',  NULL),
(95,  54, 1,  1,  130.00, 0.00, 'pending',  NULL),
(97,  56, 4,  1,  35.00,  0.00, 'pending',  NULL),
(99,  57, 1,  2,  130.00, 0.00, 'pending',  NULL),
(101, 58, 6,  1,  150.00, 0.00, 'pending',  NULL),
(102, 59, 6,  2,  150.00, 0.00, 'pending',  NULL);

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `shift_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `total_tax` decimal(10,2) DEFAULT '0.00',
  `tip` decimal(10,2) DEFAULT '0.00',
  `final_total` decimal(10,2) DEFAULT '0.00',
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `status` enum('completed','refund_requested','refunded','partially_refunded') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `collected_by` varchar(100) DEFAULT NULL,
  `payment_status` enum('paid','pending') DEFAULT 'paid',
  `customer_name` varchar(100) DEFAULT 'Walk-in',
  `amount_tendered` decimal(10,2) DEFAULT '0.00',
  `change_due` decimal(10,2) DEFAULT '0.00',
  `member_id` int DEFAULT NULL,
  `points_earned` decimal(10,2) DEFAULT '0.00',
  `points_redeemed` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `sales`;
INSERT INTO `sales` (`id`, `location_id`, `user_id`, `shift_id`, `total_amount`, `discount`, `total_tax`, `tip`, `final_total`, `payment_method`, `status`, `created_at`, `collected_by`, `payment_status`, `customer_name`, `amount_tendered`, `change_due`, `member_id`, `points_earned`, `points_redeemed`) VALUES
(1, 1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-30 06:09:19',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(2, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:45:13',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(3, 1,  4,  2,  250.00, 0.00, 0.00, 0.00, 250.00, 'cash', 'completed',  '2026-01-30 11:46:25',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(4, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:48:08',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(5, 1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-30 11:55:21',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(6, 1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-30 11:59:26',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(7, 1,  3,  1,  360.00, 0.00, 0.00, 0.00, 360.00, 'cash', 'completed',  '2026-01-30 18:07:09',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(8, 2,  7,  5,  553.00, 0.00, 0.00, 0.00, 553.00, 'cash', 'completed',  '2026-01-31 03:56:30',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(9, 2,  7,  5,  134.00, 0.00, 0.00, 0.00, 134.00, 'cash', 'completed',  '2026-01-31 04:03:30',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(10,  2,  7,  5,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:03:39',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(11,  2,  7,  5,  650.00, 0.00, 0.00, 0.00, 650.00, 'cash', 'completed',  '2026-01-31 04:05:40',  'Mumba Bar-Manager',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(12,  2,  7,  5,  1250.00,  0.00, 0.00, 0.00, 1250.00,  'cash', 'completed',  '2026-01-31 04:06:53',  'Mumba Bar-Manager',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(13,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:41',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(14,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:45',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(15,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:07:49',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(16,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:07:53',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(17,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 04:07:56',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(18,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:08:01',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(19,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 04:08:05',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(20,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 12:40:41',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(21,  1,  3,  1,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 12:49:55',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(22,  1,  3,  1,  120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-01-31 12:53:08',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(23,  1,  3,  1,  250.00, 0.00, 0.00, 0.00, 250.00, 'cash', 'completed',  '2026-01-31 12:54:43',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(24,  1,  3,  1,  254.00, 0.00, 0.00, 0.00, 254.00, 'mobile_money', 'completed',  '2026-01-31 13:43:39',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(25,  1,  3,  1,  254.00, 0.00, 0.00, 0.00, 254.00, 'cash', 'completed',  '2026-01-31 13:46:37',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(26,  1,  3,  7,  130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-01-31 16:18:15',  'Restaurant Manager', 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(27,  1,  3,  7,  150.00, 0.00, 0.00, 0.00, 150.00, 'cash', 'completed',  '2026-01-31 16:41:19',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(28,  1,  3,  7,  150.00, 0.00, 0.00, 0.00, 150.00, 'mobile_money', 'completed',  '2026-01-31 16:43:11',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(29,  1,  3,  9,  150.00, 0.00, 0.00, 0.00, 150.00, 'cash', 'completed',  '2026-01-31 16:52:49',  'Mwale Kitchen Cashier',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(30,  1,  3,  9,  750.00, 0.00, 0.00, 0.00, 750.00, 'cash', 'completed',  '2026-01-31 16:52:59',  NULL, 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(31,  4,  12, 10, 165.00, 0.00, 0.00, 0.00, 165.00, 'cash', 'completed',  '2026-01-31 17:06:19',  'Mary sales lady',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(32,  1,  3,  NULL, 150.00, 0.00, 0.00, 0.00, 150.00, 'cash', 'completed',  '2026-02-01 03:26:50',  'Mwale Kitchen Cashier',  'paid', 'table 5',  500.00, 350.00, NULL, 0.00, 0.00),
(33,  1,  3,  NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-02-01 04:01:18',  'Restaurant Manager', 'paid', 'Walk-in',  150.00, 20.00,  NULL, 0.00, 0.00),
(34,  4,  15, NULL, 165.00, 0.00, 0.00, 0.00, 165.00, 'card', 'completed',  '2026-02-01 05:38:54',  'Mary sales lady',  'paid', 'Walk-in',  0.00, -165.00,  NULL, 0.00, 0.00),
(35,  1,  4,  NULL, 264.00, 0.00, 0.00, 0.00, 264.00, 'pending',  'completed',  '2026-02-01 06:02:17',  'Mwale Kitchen Cashier',  'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(36,  1,  4,  NULL, 185.00, 0.00, 0.00, 0.00, 185.00, 'pending',  'completed',  '2026-02-01 06:07:13',  'Mwale Kitchen Cashier',  'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(37,  4,  12, NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-02-01 06:08:22',  'Mwape Restaurant Manager', 'paid', 'Walk-in',  0.00, -130.00,  NULL, 0.00, 0.00),
(38,  4,  15, NULL, 120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-02-01 06:11:24',  'Mary sales lady',  'paid', 'Walk-in',  0.00, -120.00,  NULL, 0.00, 0.00),
(39,  4,  15, NULL, 315.00, 0.00, 0.00, 0.00, 315.00, 'cash', 'completed',  '2026-02-01 06:32:34',  'Mary sales lady',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(40,  1,  3,  NULL, 35.00,  0.00, 0.00, 0.00, 35.00,  'pending',  'completed',  '2026-02-01 07:24:19',  NULL, 'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(41,  1,  4,  NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'pending',  'completed',  '2026-02-01 07:25:48',  'Mwale Kitchen Cashier',  'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(42,  1,  4,  NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'pending',  'completed',  '2026-02-01 07:26:08',  'Mwale Kitchen Cashier',  'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(43,  4,  12, NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'card', 'completed',  '2026-02-01 07:26:44',  'Mwape Restaurant Manager', 'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(44,  1,  4,  NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'pending',  'completed',  '2026-02-01 07:50:29',  'Mwale Kitchen Cashier',  'pending',  'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(45,  4,  15, NULL, 120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-02-01 07:51:18',  'Mary sales lady',  'paid', 'Walk-in',  0.00, -120.00,  NULL, 0.00, 0.00),
(46,  4,  15, NULL, 130.00, 0.00, 0.00, 0.00, 130.00, 'mobile_money', 'completed',  '2026-02-01 09:57:13',  'Mary sales lady',  'paid', 'Walk-in',  0.00, 0.00, NULL, 0.00, 0.00),
(47,  4,  12, NULL, 120.00, 0.00, 0.00, 0.00, 120.00, 'cash', 'completed',  '2026-02-01 09:58:19',  'Mwape Restaurant Manager', 'paid', 'Walk-in',  0.00, -120.00,  NULL, 0.00, 0.00),
(48,  4,  12, NULL, 250.00, 0.00, 0.00, 0.00, 250.00, 'cash', 'completed',  '2026-02-01 09:59:18',  'Mwape Restaurant Manager', 'paid', 'Walk-in',  0.00, -250.00,  NULL, 0.00, 0.00),
(49,  4,  12, 12, 150.00, 0.00, 0.00, 0.00, 150.00, 'cash', 'completed',  '2026-02-01 10:45:15',  'Mary sales lady',  'paid', 'Walk-in',  160.00, 10.00,  NULL, 0.00, 0.00),
(50,  4,  12, 12, 260.00, 0.00, 0.00, 0.00, 260.00, 'cash', 'completed',  '2026-02-01 12:21:58',  'Mary sales lady',  'paid', 'Walk-in',  500.00, 240.00, NULL, 0.00, 0.00),
(51,  4,  12, 13, 539.00, 0.00, 0.00, 0.00, 539.00, 'mobile_money (MTN)', 'completed',  '2026-02-01 12:28:34',  'Mary sales lady',  'paid', 'Walk-in',  700.00, 161.00, NULL, 0.00, 0.00),
(52,  4,  15, 14, 264.00, 0.00, 0.00, 0.00, 264.00, 'cash', 'completed',  '2026-02-01 12:58:27',  NULL, 'paid', 'Walk-in',  400.00, 136.00, NULL, 0.00, 0.00),
(53,  4,  15, 14, 130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-02-01 13:00:05',  NULL, 'paid', 'Walk-in',  200.00, 70.00,  NULL, 0.00, 0.00),
(54,  4,  15, 14, 130.00, 0.00, 0.00, 0.00, 130.00, 'cash', 'completed',  '2026-02-01 13:02:09',  NULL, 'paid', 'Walk-in',  150.00, 20.00,  NULL, 0.00, 0.00),
(55,  4,  15, 14, 150.00, 0.00, 0.00, 0.00, 150.00, 'cash', 'completed',  '2026-02-01 13:04:06',  NULL, 'paid', 'Walk-in',  0.00, -150.00,  NULL, 0.00, 0.00),
(56,  4,  15, 14, 35.00,  0.00, 0.00, 0.00, 35.00,  'cash', 'completed',  '2026-02-01 13:08:33',  'Mwape_Res_Bar_manager',  'paid', 'Walk-in',  50.00,  15.00,  NULL, 0.00, 0.00),
(57,  4,  15, 14, 260.00, 0.00, 0.00, 0.00, 260.00, 'cash', 'completed',  '2026-02-01 14:27:36',  'Mwape_Res_Bar_manager',  'paid', 'Walk-in',  400.00, 140.00, NULL, 0.00, 0.00),
(58,  4,  15, 14, 150.00, 0.00, 0.00, 0.00, 150.00, 'mobile_money (MTN)', 'completed',  '2026-02-01 14:28:55',  'mary_sales_Res_Bar', 'paid', 'Walk-in',  150.00, 0.00, NULL, 0.00, 0.00),
(59,  4,  1,  6,  300.00, 0.00, 0.00, 0.00, 300.00, 'cash', 'completed',  '2026-02-01 18:10:40',  'odelia_admin', 'paid', 'Daliso Nindi', 300.00, 0.00, 1,  15.00,  0.00);

DROP TABLE IF EXISTS `shifts`;
CREATE TABLE `shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `location_id` int NOT NULL,
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `starting_cash` decimal(10,2) DEFAULT '0.00',
  `closing_cash` decimal(10,2) DEFAULT '0.00',
  `expected_cash` decimal(10,2) DEFAULT '0.00',
  `manager_closing_cash` decimal(10,2) DEFAULT '0.00',
  `status` enum('open','closed') DEFAULT 'open',
  `variance_reason` text,
  `handover_notes` text,
  `start_verified_by` int DEFAULT NULL,
  `start_verified_at` timestamp NULL DEFAULT NULL,
  `end_verified_by` int DEFAULT NULL,
  `end_verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `location_id` (`location_id`),
  KEY `start_verified_by` (`start_verified_by`),
  KEY `end_verified_by` (`end_verified_by`),
  CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `shifts_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `shifts_ibfk_3` FOREIGN KEY (`start_verified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `shifts_ibfk_4` FOREIGN KEY (`end_verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `shifts`;
INSERT INTO `shifts` (`id`, `user_id`, `location_id`, `start_time`, `end_time`, `starting_cash`, `closing_cash`, `expected_cash`, `manager_closing_cash`, `status`, `variance_reason`, `handover_notes`, `start_verified_by`, `start_verified_at`, `end_verified_by`, `end_verified_at`) VALUES
(1, 3,  1,  '2026-01-30 06:09:06',  '2026-01-31 16:04:44',  100.00, 2844.00,  0.00, 2844.00,  'closed', '', NULL, 1,  '2026-01-30 06:09:06',  1,  '2026-01-31 16:04:44'),
(2, 4,  1,  '2026-01-30 06:58:36',  NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 06:58:36',  NULL, NULL),
(3, 6,  1,  '2026-01-30 11:56:10',  '2026-02-01 02:41:44',  0.00, 0.00, 0.00, 0.00, 'closed', NULL, 'No issues',  1,  '2026-01-30 11:56:10',  4,  '2026-02-01 02:41:44'),
(4, 8,  2,  '2026-01-30 18:12:30',  NULL, 500.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 1,  '2026-01-30 18:12:30',  NULL, NULL),
(5, 7,  2,  '2026-01-30 18:23:48',  NULL, 100.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 8,  '2026-01-30 18:23:48',  NULL, NULL),
(6, 1,  3,  '2026-01-31 05:08:33',  NULL, 100.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 5,  '2026-01-31 05:08:33',  NULL, NULL),
(7, 3,  1,  '2026-01-31 16:17:56',  '2026-01-31 16:45:13',  100.00, 380.00, 0.00, 380.00, 'closed', '', NULL, 13, '2026-01-31 16:17:56',  4,  '2026-01-31 16:45:13'),
(8, 3,  1,  '2026-01-31 16:50:47',  '2026-01-31 16:51:09',  900.00, 900.00, 0.00, 900.00, 'closed', '', NULL, 4,  '2026-01-31 16:50:47',  4,  '2026-01-31 16:51:09'),
(9, 3,  1,  '2026-01-31 16:52:24',  '2026-02-01 03:02:16',  100.00, 1000.00,  0.00, 1000.00,  'closed', '', NULL, 4,  '2026-01-31 16:52:24',  4,  '2026-02-01 03:02:16'),
(10,  12, 12, '2026-01-31 16:56:26',  '2026-02-01 04:02:55',  0.00, 165.00, 0.00, 165.00, 'closed', '', NULL, 4,  '2026-01-31 16:56:26',  4,  '2026-02-01 04:02:55'),
(11,  3,  1,  '2026-02-01 03:19:48',  '2026-02-01 03:35:36',  300.00, 300.00, 0.00, 300.00, 'closed', '', NULL, 4,  '2026-02-01 03:19:48',  4,  '2026-02-01 03:35:36'),
(12,  12, 4,  '2026-02-01 10:39:12',  '2026-02-01 12:22:32',  100.00, 360.00, 0.00, 360.00, 'closed', '', NULL, 4,  '2026-02-01 10:39:12',  4,  '2026-02-01 12:22:32'),
(13,  12, 4,  '2026-02-01 12:22:50',  NULL, 500.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 4,  '2026-02-01 12:22:50',  NULL, NULL),
(14,  15, 4,  '2026-02-01 12:55:47',  NULL, 100.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, 4,  '2026-02-01 12:55:47',  NULL, NULL);

DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transfer_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL,
  `quantity_sent` decimal(10,2) DEFAULT '0.00',
  `quantity_received` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `stock_transfer_items`;
INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `product_id`, `quantity_requested`, `quantity_sent`, `quantity_received`) VALUES
(1, 1,  2,  50.00,  50.00,  50.00);

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `source_location_id` int NOT NULL,
  `destination_location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` enum('pending','completed','cancelled','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `source_location_id` (`source_location_id`),
  KEY `destination_location_id` (`destination_location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`source_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`destination_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `stock_transfers`;
INSERT INTO `stock_transfers` (`id`, `source_location_id`, `destination_location_id`, `user_id`, `status`, `created_at`) VALUES
(1, 3,  1,  5,  'completed',  '2026-01-30 07:16:26');

DROP TABLE IF EXISTS `taxes`;
CREATE TABLE `taxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `taxes`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','shopkeeper','manager','cashier','dev','chef','waiter','head_chef','bartender') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'cashier',
  `location_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `force_password_change` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `users`;
INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`) VALUES
(1, 'odelia_admin', 'Mando Odelia', '$2y$10$Hf3oqWOf/u3p8mVDynHZp.Fr.9bgbxm6ptvrZCiqHEmSBs5MByTz2', 'dev',  9,  '2026-01-29 12:55:36',  0),
(3, 'Restaurant_cashier', 'Mwale Kitchen Cashier',  '$2y$10$L4IYlKv4CnIjvDEXQZMtfO8yB86t.2v2fd8lmobqJkI8XadeZzS.i', 'cashier',  1,  '2026-01-29 12:55:36',  0),
(4, 'head_chef',  'Head Chef',  '$2y$10$Zx4dvmsK6tetfu/o32qwaOHgzW5aCPYyXebrPHCuJxNDP5cg133Fu', 'admin',  1,  '2026-01-30 05:37:27',  0),
(5, 'stores_manager', 'Choolwe Stores Manager', '$2y$10$FaqE3WQxADD.8GWugJw0ieRpGhIGsX9oerUqaY4bc2NNFfg9L4Ai2', 'manager',  3,  '2026-01-30 06:39:29',  0),
(6, 'chef', 'Sililo Chef',  '$2y$10$JFws5wVFa6tAdwflrThJr.xFJJpRGYOtl0PW2wxm9xOprPlgbt2oS', 'chef', 1,  '2026-01-30 06:40:05',  0),
(7, 'Main_bartender', 'Main Bar Bartender', '$2y$10$1LpLTw/HiJsIEbNjwZ44zuNm.K.DiDtFretzcA65M7RV6tliGCN86', 'bartender',  2,  '2026-01-30 06:40:57',  1),
(8, 'Bar_Manager',  'Mumba Bar-Manager',  '$2y$10$7B0j9oNHDch0mRyfbUsopuhzwbFbVJAgrdN2spsxfNDYRDzyVKn4i', 'manager',  2,  '2026-01-30 07:59:03',  0),
(10,  'Daliso', 'Daliso Nindi', '$2y$10$VlTfRDkhaOa3Mi.l7Eubd.fr/yfJ5m5fixiDVgs45nZroOUPhtYty', 'admin',  9,  '2026-01-30 18:19:15',  1),
(11,  'Admin',  'Admininistaror Account', '$2y$10$VEERI3aWoz.dgcNFAL/zseiH4q47bs3UwACwrma64ET0pECHGkFpC', 'admin',  9,  '2026-01-31 07:09:34',  0),
(12,  'mary_sales_Res_Bar', 'Mary sales lady',  '$2y$10$6X2lDe5vcBb/s1u.Vd7zruqS7p9HoP0UIbVM9ty.TU2C1RKjY163i', 'cashier',  4,  '2026-01-31 14:06:24',  0),
(13,  'Restaurant_Manager', 'Restaurant Manager', '$2y$10$JlEq5qWHd9hF47RQL7MVzO2C.lHIcrtgAeoM9U204XoGfkqOjrgXC', 'manager',  1,  '2026-01-31 16:15:59',  0),
(14,  'Mini_stores_manager',  'mini store manager', '$2y$10$4rpiSKKpnExkGbd0JUboQeOrzRpX6XkopWyt4AFsk6Jw1lXN/jfhq', 'manager',  7,  '2026-02-01 04:12:43',  0),
(15,  'Mwape_Res_Bar_manager',  'Mwape Restaurant Manager', '$2y$10$yLFmCN96bPn7JDftB7/9QOlLLHizCIPAB8/OHzK4zd9hc.2c1xiEm', 'manager',  4,  '2026-02-01 04:22:19',  0);

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `vendors`;
INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`) VALUES
(1, 'Zambeef Zambia', 'Mr. Phir', ''),
(2, 'Coca Cola Zambia', 'Mr. Daliso', ''),
(3, 'Shoprite Zambia',  'Mr. Choogo', '');

-- 2026-02-01 18:41:28 UTC