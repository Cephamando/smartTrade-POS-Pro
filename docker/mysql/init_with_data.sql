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
(1,	'Beverages',	NULL,	'drink'),
(2,	'Meals',	NULL,	'food'),
(3,	'Ingredients',	NULL,	'ingredients'),
(4,	'Snacks',	NULL,	'food'),
(5,	'Cleaning Material',	NULL,	'other');

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
(1,	1,	6,	100.00,	1200.00),
(2,	2,	8,	100.00,	0.00),
(3,	3,	9,	10.00,	0.00),
(4,	4,	3,	10.00,	1000.00);

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
(1,	2,	1,	1,	120000.00,	'',	'2026-02-07 06:45:07'),
(2,	3,	3,	1,	0.00,	'',	'2026-02-07 08:32:38'),
(3,	3,	6,	1,	0.00,	'',	'2026-02-07 08:59:27'),
(4,	2,	3,	1,	10000.00,	'',	'2026-02-07 12:16:58');

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
(1,	1,	3,	886,	'2026-02-09 06:30:43'),
(2,	1,	2,	50,	'2026-02-06 19:59:36'),
(3,	2,	3,	495,	'2026-02-07 11:59:39'),
(4,	2,	2,	100,	'2026-02-06 19:59:36'),
(5,	3,	1,	23,	'2026-02-09 07:25:24'),
(6,	4,	3,	50,	'2026-02-09 06:31:10'),
(7,	4,	2,	12,	'2026-02-06 21:15:45'),
(8,	5,	3,	200,	'2026-02-06 19:59:36'),
(9,	5,	2,	20,	'2026-02-06 19:59:36'),
(10,	6,	1,	98,	'2026-02-09 19:32:41'),
(11,	7,	3,	50,	'2026-02-06 19:59:36'),
(12,	7,	1,	4,	'2026-02-07 05:36:29'),
(13,	1,	1,	406,	'2026-02-09 19:32:41'),
(16,	1,	5,	11,	'2026-02-06 23:24:31'),
(20,	3,	5,	20,	'2026-02-07 07:07:36'),
(21,	6,	3,	101,	'2026-02-09 06:29:58'),
(22,	8,	3,	100,	'2026-02-07 08:32:39'),
(24,	6,	2,	10,	'2026-02-07 08:54:26'),
(25,	9,	6,	5,	'2026-02-07 09:00:33'),
(26,	9,	1,	5,	'2026-02-07 09:00:49'),
(27,	3,	3,	75,	'2026-02-09 07:25:12'),
(31,	9,	3,	90,	'2026-02-09 06:30:59');

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
(1,	3,	1,	1,	-2.00,	18.00,	'sale',	1,	'2026-02-06 20:30:07'),
(2,	6,	1,	1,	-1.00,	14.00,	'sale',	2,	'2026-02-06 20:59:35'),
(3,	6,	1,	1,	-1.00,	13.00,	'sale',	3,	'2026-02-06 21:05:00'),
(4,	1,	1,	1,	200.00,	200.00,	'grv',	NULL,	'2026-02-06 21:12:02'),
(5,	1,	1,	1,	-10.00,	190.00,	'transfer_out',	2,	'2026-02-06 21:13:17'),
(6,	1,	1,	1,	110.00,	300.00,	'grv',	NULL,	'2026-02-06 21:14:10'),
(7,	1,	1,	1,	110.00,	410.00,	'grv',	NULL,	'2026-02-06 21:14:28'),
(8,	1,	5,	1,	10.00,	10.00,	'transfer_in',	2,	'2026-02-06 21:14:54'),
(9,	4,	3,	1,	-10.00,	0.00,	'transfer_out',	3,	'2026-02-06 21:15:42'),
(10,	4,	2,	1,	10.00,	12.00,	'transfer_in',	3,	'2026-02-06 21:15:45'),
(11,	1,	5,	1,	-2.00,	8.00,	'sale',	4,	'2026-02-06 21:18:11'),
(12,	1,	5,	1,	-1.00,	7.00,	'sale',	4,	'2026-02-06 21:33:10'),
(13,	1,	5,	1,	-1.00,	6.00,	'sale',	4,	'2026-02-06 21:42:35'),
(14,	1,	5,	1,	-1.00,	5.00,	'sale',	4,	'2026-02-06 22:44:17'),
(15,	1,	5,	1,	-3.00,	2.00,	'sale',	5,	'2026-02-06 23:13:04'),
(16,	1,	5,	1,	-1.00,	1.00,	'sale',	6,	'2026-02-06 23:15:54'),
(17,	1,	3,	1,	-10.00,	990.00,	'transfer_out',	4,	'2026-02-06 23:24:29'),
(18,	1,	5,	1,	10.00,	11.00,	'transfer_in',	4,	'2026-02-06 23:24:31'),
(19,	7,	1,	1,	-1.00,	4.00,	'sale',	7,	'2026-02-07 05:36:29'),
(20,	1,	1,	1,	-1.00,	409.00,	'sale',	8,	'2026-02-07 05:36:55'),
(21,	6,	1,	1,	-1.00,	12.00,	'sale',	9,	'2026-02-07 05:37:53'),
(22,	6,	1,	1,	100.00,	112.00,	'grv',	1,	'2026-02-07 06:45:07'),
(23,	6,	1,	1,	-1.00,	111.00,	'sale',	10,	'2026-02-07 07:08:27'),
(24,	6,	3,	1,	100.00,	100.00,	'grv',	NULL,	'2026-02-07 08:16:48'),
(25,	1,	3,	1,	-1.00,	989.00,	'sale',	11,	'2026-02-07 08:18:05'),
(26,	1,	3,	1,	-1.00,	988.00,	'sale',	11,	'2026-02-07 08:18:39'),
(27,	2,	3,	1,	-1.00,	499.00,	'sale',	12,	'2026-02-07 08:19:45'),
(28,	1,	3,	1,	-1.00,	987.00,	'sale',	12,	'2026-02-07 08:19:45'),
(29,	6,	3,	1,	-1.00,	99.00,	'sale',	13,	'2026-02-07 08:20:07'),
(30,	6,	3,	1,	-1.00,	98.00,	'sale',	13,	'2026-02-07 08:21:05'),
(31,	2,	3,	1,	-1.00,	498.00,	'sale',	14,	'2026-02-07 08:22:06'),
(32,	2,	3,	1,	-1.00,	497.00,	'sale',	15,	'2026-02-07 08:22:44'),
(33,	8,	3,	1,	100.00,	100.00,	'grv',	2,	'2026-02-07 08:32:39'),
(34,	6,	1,	1,	-10.00,	101.00,	'transfer_out',	6,	'2026-02-07 08:54:14'),
(35,	6,	2,	1,	10.00,	10.00,	'transfer_in',	6,	'2026-02-07 08:54:26'),
(36,	9,	6,	1,	10.00,	10.00,	'grv',	3,	'2026-02-07 08:59:28'),
(37,	9,	6,	1,	-5.00,	5.00,	'transfer_out',	7,	'2026-02-07 09:00:33'),
(38,	9,	1,	1,	5.00,	5.00,	'transfer_in',	7,	'2026-02-07 09:00:49'),
(39,	6,	3,	1,	-1.00,	97.00,	'sale',	16,	'2026-02-07 11:53:20'),
(40,	2,	3,	1,	-2.00,	495.00,	'sale',	14,	'2026-02-07 11:59:39'),
(41,	1,	3,	1,	-1.00,	986.00,	'sale',	16,	'2026-02-07 12:01:49'),
(42,	6,	3,	1,	-1.00,	96.00,	'sale',	17,	'2026-02-07 12:04:46'),
(43,	3,	3,	1,	10.00,	10.00,	'grv',	4,	'2026-02-07 12:16:58'),
(44,	3,	3,	1,	-5.00,	5.00,	'transfer_out',	8,	'2026-02-07 12:18:52'),
(45,	3,	1,	1,	5.00,	13.00,	'transfer_in',	8,	'2026-02-07 12:19:28'),
(46,	6,	3,	1,	5.00,	101.00,	'grv',	NULL,	'2026-02-09 06:29:58'),
(47,	1,	3,	1,	-100.00,	886.00,	'adjustment',	NULL,	'2026-02-09 06:30:43'),
(48,	9,	3,	1,	90.00,	90.00,	'grv',	NULL,	'2026-02-09 06:30:59'),
(49,	4,	3,	1,	50.00,	50.00,	'grv',	NULL,	'2026-02-09 06:31:10'),
(50,	3,	3,	1,	80.00,	85.00,	'grv',	NULL,	'2026-02-09 06:31:17'),
(51,	3,	3,	1,	-10.00,	75.00,	'transfer_out',	9,	'2026-02-09 07:25:12'),
(52,	3,	1,	1,	10.00,	23.00,	'transfer_in',	9,	'2026-02-09 07:25:24'),
(53,	1,	1,	1,	-1.00,	408.00,	'sale',	21,	'2026-02-09 08:04:34'),
(54,	6,	1,	1,	-1.00,	100.00,	'sale',	21,	'2026-02-09 08:04:34'),
(55,	1,	1,	1,	-1.00,	407.00,	'sale',	22,	'2026-02-09 19:18:24'),
(56,	6,	1,	1,	-1.00,	99.00,	'sale',	23,	'2026-02-09 19:32:15'),
(57,	1,	1,	1,	-1.00,	406.00,	'sale',	21,	'2026-02-09 19:32:41'),
(58,	6,	1,	1,	-1.00,	98.00,	'sale',	21,	'2026-02-09 19:32:41');

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
(1,	1,	5,	3,	20.00,	1,	'completed',	'2026-02-06 23:10:32',	'2026-02-07 09:07:36',	NULL),
(2,	1,	5,	1,	10.00,	1,	'completed',	'2026-02-06 23:10:51',	'2026-02-06 23:13:17',	'2026-02-06 23:14:54'),
(3,	3,	2,	4,	10.00,	1,	'completed',	'2026-02-06 23:15:34',	'2026-02-06 23:15:42',	'2026-02-06 23:15:45'),
(4,	3,	5,	1,	10.00,	1,	'completed',	'2026-02-07 01:20:30',	'2026-02-07 01:24:29',	'2026-02-07 01:24:31'),
(5,	3,	1,	3,	10.00,	1,	'completed',	'2026-02-07 09:08:40',	'2026-02-07 10:33:05',	NULL),
(6,	1,	2,	6,	10.00,	1,	'completed',	'2026-02-07 10:53:56',	'2026-02-07 10:54:14',	'2026-02-07 10:54:26'),
(7,	6,	1,	9,	5.00,	1,	'completed',	'2026-02-07 11:00:08',	'2026-02-07 11:00:33',	'2026-02-07 11:00:49'),
(8,	3,	1,	3,	5.00,	1,	'completed',	'2026-02-07 14:14:23',	'2026-02-07 14:18:52',	'2026-02-07 14:19:28'),
(9,	3,	1,	3,	10.00,	1,	'completed',	'2026-02-09 09:24:52',	'2026-02-09 09:25:12',	'2026-02-09 09:25:24');

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
(1,	3,	1,	886.00),
(2,	2,	1,	50.00),
(3,	3,	2,	495.00),
(4,	2,	2,	100.00),
(5,	1,	3,	13.00),
(6,	3,	4,	50.00),
(7,	2,	4,	12.00),
(8,	3,	5,	200.00),
(9,	2,	5,	20.00),
(10,	1,	6,	101.00),
(11,	3,	7,	50.00),
(12,	1,	7,	4.00),
(13,	1,	1,	409.00),
(14,	5,	1,	11.00),
(15,	5,	3,	20.00),
(16,	3,	6,	101.00),
(17,	3,	8,	100.00),
(18,	2,	6,	10.00),
(19,	6,	9,	5.00),
(20,	1,	9,	5.00),
(21,	3,	3,	85.00),
(22,	3,	9,	90.00);

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
(1,	'Main Kitchen',	'kitchen',	1,	0,	NULL,	'555-0000'),
(2,	'Main Bar',	'bar',	1,	0,	NULL,	'555-0000'),
(3,	'Main Warehouse',	'warehouse',	0,	1,	NULL,	'555-0000'),
(4,	'Restaurant Bar',	'bar',	1,	0,	NULL,	'555-0000'),
(5,	'Coffee Shop',	'store',	1,	0,	NULL,	'555-0000'),
(6,	'MIni Storeroom',	'warehouse',	1,	0,	'',	'555-0000');

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
(1,	'John Doe',	'0977000000',	NULL,	50.00,	'2026-02-06 19:59:36'),
(2,	'Jane Smith',	'0966000000',	NULL,	120.00,	'2026-02-06 19:59:36');

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
(1,	1,	'T-Bone Steak',	'collected',	6,	'2026-02-06 20:31:07'),
(2,	2,	'Beef Burger',	'collected',	6,	'2026-02-06 21:00:17'),
(3,	3,	'Beef Burger',	'collected',	6,	'2026-02-06 21:05:57'),
(4,	4,	'T-Bone Steak',	'collected',	6,	'2026-02-06 21:06:26'),
(5,	9,	'Beef Burger',	'collected',	4,	'2026-02-07 06:00:16'),
(6,	10,	'Beef Burger',	'collected',	6,	'2026-02-07 07:08:46'),
(7,	13,	'Beef Burger',	'collected',	6,	'2026-02-07 08:20:24'),
(8,	13,	'Beef Burger',	'collected',	6,	'2026-02-07 09:01:37'),
(9,	16,	'Beef Burger',	'collected',	6,	'2026-02-07 11:54:39'),
(10,	17,	'Beef Burger',	'collected',	6,	'2026-02-07 12:08:42'),
(11,	21,	'Beef Burger',	'collected',	4,	'2026-02-09 08:41:38'),
(12,	23,	'Beef Burger',	'collected',	4,	'2026-02-09 19:32:55'),
(13,	21,	'Beef Burger',	'collected',	4,	'2026-02-09 19:32:56');

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
  `type` enum('item','service') DEFAULT 'item',
  `is_open_price` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`, `type`, `is_open_price`) VALUES
(1,	'Coca Cola 300ml',	NULL,	15.00,	8.00,	'btl',	1,	1,	'item',	0),
(2,	'Mosi Lager',	NULL,	25.00,	12.00,	'btl',	1,	1,	'item',	0),
(3,	'T-Bone Steak',	NULL,	120.00,	60.00,	'plate',	2,	1,	'item',	0),
(4,	'Jameson Shot',	NULL,	40.00,	15.00,	'tot',	1,	1,	'item',	0),
(5,	'Water 500ml',	NULL,	5.00,	2.00,	'btl',	1,	1,	'item',	0),
(6,	'Beef Burger',	NULL,	65.00,	30.00,	'plate',	2,	1,	'item',	0),
(7,	'Cooking Oil',	NULL,	0.00,	45.00,	'ltr',	3,	1,	'item',	0),
(8,	'Dish washer',	NULL,	0.00,	0.00,	'unit',	5,	1,	'item',	0),
(9,	'Dish Wash',	NULL,	0.00,	0.00,	'unit',	5,	1,	'item',	0),
(10,	'Free Delivery',	NULL,	0.00,	0.00,	'unit',	NULL,	0,	'service',	1),
(11,	'Free Drink',	NULL,	0.00,	0.00,	'unit',	NULL,	0,	'service',	1),
(12,	'Beef Burger',	NULL,	40.00,	0.00,	'unit',	NULL,	1,	'service',	1);

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

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `manager_id` int NOT NULL,
  `amount_refunded` decimal(10,2) NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `manager_id` (`manager_id`),
  CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `refunds`;

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
(1,	1,	3,	2,	120.00,	0.00,	'ready',	NULL),
(2,	2,	6,	1,	65.00,	0.00,	'ready',	NULL),
(3,	3,	6,	1,	65.00,	0.00,	'ready',	NULL),
(8,	4,	1,	1,	15.00,	0.00,	'pending',	NULL),
(9,	5,	1,	3,	15.00,	0.00,	'pending',	NULL),
(10,	6,	1,	1,	15.00,	0.00,	'pending',	NULL),
(11,	7,	7,	1,	0.00,	0.00,	'pending',	NULL),
(12,	8,	1,	1,	15.00,	0.00,	'pending',	NULL),
(13,	9,	6,	1,	65.00,	0.00,	'ready',	NULL),
(14,	10,	6,	1,	65.00,	0.00,	'ready',	NULL),
(16,	11,	1,	1,	15.00,	0.00,	'pending',	NULL),
(17,	12,	2,	1,	25.00,	0.00,	'pending',	NULL),
(18,	12,	1,	1,	15.00,	0.00,	'pending',	NULL),
(20,	13,	6,	1,	65.00,	0.00,	'ready',	NULL),
(22,	15,	2,	1,	25.00,	0.00,	'pending',	NULL),
(24,	14,	2,	2,	25.00,	0.00,	'pending',	NULL),
(25,	16,	1,	1,	15.00,	0.00,	'pending',	NULL),
(26,	17,	6,	1,	65.00,	0.00,	'ready',	NULL),
(27,	18,	2,	1,	25.00,	0.00,	'pending',	NULL),
(32,	22,	1,	1,	15.00,	0.00,	'pending',	NULL),
(33,	22,	12,	1,	40.00,	0.00,	'pending',	NULL),
(34,	23,	6,	1,	65.00,	0.00,	'ready',	NULL),
(35,	23,	12,	1,	10.00,	0.00,	'pending',	NULL),
(36,	21,	1,	1,	15.00,	0.00,	'pending',	NULL),
(37,	21,	6,	1,	65.00,	0.00,	'ready',	NULL);

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
(1,	1,	1,	1,	240.00,	0.00,	0.00,	0.00,	240.00,	'Cash & Cash',	'completed',	'2026-02-06 20:30:07',	'6',	'paid',	'Walk-in',	240.00,	0.00,	NULL,	0.00,	0.00),
(2,	1,	1,	1,	65.00,	0.00,	0.00,	0.00,	65.00,	'Cash',	'completed',	'2026-02-06 20:59:35',	'6',	'paid',	'Walk-in',	65.00,	0.00,	NULL,	0.00,	0.00),
(3,	1,	1,	1,	65.00,	0.00,	0.00,	0.00,	65.00,	'Cash',	'completed',	'2026-02-06 21:05:00',	'6',	'paid',	'Walk-in',	65.00,	0.00,	NULL,	0.00,	0.00),
(4,	1,	1,	1,	120.00,	0.00,	0.00,	0.00,	15.00,	'Cash & Card',	'completed',	'2026-02-06 21:05:40',	'6',	'paid',	'Walk-in',	17.00,	2.00,	NULL,	0.00,	0.00),
(5,	5,	1,	1,	45.00,	0.00,	0.00,	0.00,	40.50,	'Cash',	'completed',	'2026-02-06 23:13:04',	NULL,	'paid',	'Jane Smith',	40.50,	0.00,	2,	0.00,	0.00),
(6,	5,	1,	1,	15.00,	0.00,	0.00,	0.00,	13.50,	'Cash',	'completed',	'2026-02-06 23:15:54',	NULL,	'paid',	'Jane Smith',	13.50,	0.00,	2,	0.00,	0.00),
(7,	1,	1,	1,	0.00,	0.00,	0.00,	0.00,	0.00,	'Cash',	'completed',	'2026-02-07 05:36:29',	NULL,	'paid',	'Jane Smith',	0.00,	0.00,	2,	0.00,	0.00),
(8,	1,	1,	1,	15.00,	0.00,	0.00,	0.00,	13.50,	'Cash',	'completed',	'2026-02-07 05:36:55',	NULL,	'paid',	'Jane Smith',	13.50,	0.00,	2,	0.00,	0.00),
(9,	1,	1,	1,	65.00,	0.00,	0.00,	0.00,	58.50,	'Cash',	'completed',	'2026-02-07 05:37:53',	'4',	'paid',	'John Doe',	58.50,	0.00,	1,	0.00,	0.00),
(10,	1,	1,	1,	65.00,	0.00,	0.00,	0.00,	65.00,	'Cash & MTN Money',	'completed',	'2026-02-07 07:08:27',	'6',	'paid',	'Walk-in',	70.00,	5.00,	NULL,	0.00,	0.00),
(11,	3,	1,	1,	15.00,	0.00,	0.00,	0.00,	15.00,	'Cash',	'completed',	'2026-02-07 08:18:05',	NULL,	'paid',	'Walk-in',	25.00,	10.00,	NULL,	0.00,	0.00),
(12,	3,	1,	1,	40.00,	0.00,	0.00,	0.00,	36.00,	'Cash',	'completed',	'2026-02-07 08:19:45',	NULL,	'paid',	'John Doe',	36.00,	0.00,	1,	0.00,	0.00),
(13,	3,	1,	1,	65.00,	0.00,	0.00,	0.00,	65.00,	'Cash',	'completed',	'2026-02-07 08:20:07',	'6',	'paid',	'Walk-in',	130.00,	65.00,	NULL,	0.00,	0.00),
(14,	3,	1,	1,	50.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-02-07 08:22:06',	NULL,	'paid',	'Walk-in',	50.00,	0.00,	NULL,	0.00,	0.00),
(15,	3,	1,	1,	25.00,	0.00,	0.00,	0.00,	25.00,	'Cash',	'completed',	'2026-02-07 08:22:44',	NULL,	'paid',	'daliso',	25.00,	0.00,	NULL,	0.00,	0.00),
(16,	3,	1,	3,	15.00,	0.00,	0.00,	0.00,	13.50,	'Cash',	'completed',	'2026-02-07 11:53:19',	'6',	'paid',	'Jane Smith',	20.00,	6.50,	2,	0.00,	0.00),
(17,	3,	1,	3,	65.00,	0.00,	0.00,	0.00,	65.00,	'Cash',	'completed',	'2026-02-07 12:04:46',	'6',	'paid',	'Walk-in',	65.00,	0.00,	NULL,	0.00,	0.00),
(18,	2,	7,	4,	25.00,	0.00,	4.00,	0.00,	29.00,	'card',	'completed',	'2026-02-09 06:52:07',	NULL,	'paid',	'Walk-in',	0.00,	0.00,	NULL,	0.00,	0.00),
(21,	1,	1,	5,	80.00,	0.00,	0.00,	0.00,	80.00,	'Cash',	'completed',	'2026-02-09 08:04:34',	'4',	'paid',	'Walk-in',	80.00,	0.00,	NULL,	0.00,	0.00),
(22,	1,	1,	17,	55.00,	0.00,	0.00,	0.00,	55.00,	'Card',	'completed',	'2026-02-09 19:18:24',	NULL,	'paid',	'Walk-in',	55.00,	0.00,	NULL,	0.00,	0.00),
(23,	1,	1,	17,	75.00,	0.00,	0.00,	0.00,	75.00,	'Card & Cash',	'completed',	'2026-02-09 19:32:15',	'4',	'paid',	'Walk-in',	100.00,	25.00,	NULL,	0.00,	0.00);

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
  `status` enum('pending_approval','open','closed') DEFAULT 'pending_approval',
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
(1,	1,	5,	'2026-02-06 20:03:34',	'2026-02-09 06:21:02',	700.00,	1467.00,	1467.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-06 20:03:38',	1,	'2026-02-09 06:21:02'),
(2,	1,	1,	'2026-02-07 08:43:17',	'2026-02-09 06:20:48',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-07 08:43:22',	1,	'2026-02-09 06:20:48'),
(3,	1,	2,	'2026-02-07 08:46:59',	'2026-02-07 12:42:49',	0.00,	50.00,	78.50,	0.00,	'closed',	'lost',	NULL,	1,	'2026-02-07 08:47:03',	1,	'2026-02-07 12:42:49'),
(4,	7,	2,	'2026-02-09 06:51:18',	'2026-02-09 15:10:09',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 06:51:18',	1,	'2026-02-09 15:10:09'),
(5,	1,	1,	'2026-02-09 07:39:19',	'2026-02-09 09:02:37',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 07:39:19',	1,	'2026-02-09 09:02:37'),
(6,	1,	1,	'2026-02-09 09:02:48',	'2026-02-09 09:02:59',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 09:02:53',	1,	'2026-02-09 09:02:59'),
(7,	1,	4,	'2026-02-09 15:08:22',	'2026-02-09 15:08:39',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:08:24',	1,	'2026-02-09 15:08:39'),
(8,	7,	4,	'2026-02-09 15:10:37',	'2026-02-09 15:10:48',	0.00,	0.00,	0.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:10:41',	1,	'2026-02-09 15:10:48'),
(9,	1,	4,	'2026-02-09 15:20:24',	'2026-02-09 15:20:40',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:20:30',	1,	'2026-02-09 15:20:40'),
(10,	1,	4,	'2026-02-09 15:21:04',	'2026-02-09 15:39:29',	5.00,	5.00,	5.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:21:07',	1,	'2026-02-09 15:39:29'),
(11,	7,	4,	'2026-02-09 15:22:52',	'2026-02-09 16:02:41',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:22:56',	1,	'2026-02-09 16:02:41'),
(12,	1,	4,	'2026-02-09 15:39:37',	'2026-02-09 15:39:52',	5.00,	5.00,	5.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:39:39',	1,	'2026-02-09 15:39:52'),
(13,	1,	4,	'2026-02-09 15:54:25',	'2026-02-09 16:04:23',	300.00,	300.00,	300.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 15:54:28',	1,	'2026-02-09 16:04:23'),
(14,	7,	4,	'2026-02-09 16:02:52',	'2026-02-09 16:03:58',	6.00,	6.00,	6.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 16:02:56',	1,	'2026-02-09 16:03:58'),
(15,	1,	4,	'2026-02-09 16:04:44',	'2026-02-09 16:06:59',	0.00,	0.00,	0.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 16:04:47',	1,	'2026-02-09 16:06:59'),
(16,	7,	4,	'2026-02-09 16:07:32',	'2026-02-09 16:11:16',	5.00,	5.00,	5.00,	0.00,	'closed',	'',	NULL,	1,	'2026-02-09 16:07:36',	1,	'2026-02-09 16:11:16'),
(17,	1,	1,	'2026-02-09 16:11:38',	NULL,	500.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	1,	'2026-02-09 16:11:41',	NULL,	NULL);

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

DROP TABLE IF EXISTS `taxes`;
CREATE TABLE `taxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `taxes`;
INSERT INTO `taxes` (`id`, `name`, `rate`, `is_active`) VALUES
(1,	'VAT',	16.00,	1),
(2,	'Service Charge',	10.00,	0);

DROP TABLE IF EXISTS `transfers`;
CREATE TABLE `transfers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `from_location_id` int NOT NULL,
  `to_location_id` int NOT NULL,
  `quantity` int NOT NULL,
  `status` enum('pending','completed') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `from_location_id` (`from_location_id`),
  KEY `to_location_id` (`to_location_id`),
  CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `transfers`;

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
(1,	'admin',	'System Admin',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'admin',	3,	'2026-02-06 19:59:35',	0),
(2,	'dev',	'Developer Account',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'dev',	3,	'2026-02-06 19:59:35',	0),
(3,	'manager',	'Bar Manager',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'manager',	2,	'2026-02-06 19:59:35',	0),
(4,	'cashier',	'Bar Cashier',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'cashier',	2,	'2026-02-06 19:59:35',	0),
(5,	'chef',	'Head Chef',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'chef',	1,	'2026-02-06 19:59:35',	0),
(6,	'waiter',	'Restaurant Waiter',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'cashier',	4,	'2026-02-06 19:59:35',	0),
(7,	'bartender',	'Main Bartender',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'bartender',	2,	'2026-02-06 19:59:35',	0);

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
(1,	'Coca Cola Zambia',	'Mr. Phiri',	NULL),
(2,	'Zambeef',	'Sales Rep',	NULL),
(3,	'Tiger Animal Feeds',	'Mrs. Banda',	NULL);

-- 2026-02-09 19:45:17 UTC
