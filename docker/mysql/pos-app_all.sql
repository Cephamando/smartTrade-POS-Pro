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
  `parent_id` int DEFAULT NULL,
  `type` enum('food','drink','meal','ingredients','other') NOT NULL DEFAULT 'other',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_cat_parent` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `categories`;
INSERT INTO `categories` (`id`, `name`, `description`, `parent_id`, `type`) VALUES
(1,	'CIDERS',	NULL,	NULL,	'other'),
(2,	'BEERS',	NULL,	NULL,	'other'),
(3,	'SOFTIES',	NULL,	NULL,	'other'),
(4,	'JUICES',	NULL,	NULL,	'other'),
(5,	'SODAS',	NULL,	NULL,	'other'),
(6,	'COCKTAILS',	NULL,	NULL,	'other'),
(7,	'MOCKTAILS',	NULL,	NULL,	'other'),
(8,	'MIXERS',	NULL,	NULL,	'other'),
(9,	'WHISKYS',	NULL,	NULL,	'other'),
(10,	'GINS',	NULL,	NULL,	'other'),
(11,	'COGNAC',	NULL,	NULL,	'other'),
(12,	'LIQUEURS',	NULL,	NULL,	'other'),
(13,	'BRANDY',	NULL,	NULL,	'other'),
(14,	'VODKAS',	NULL,	NULL,	'other'),
(15,	'RUM',	NULL,	NULL,	'other'),
(16,	'WINES BOTTLES RED',	NULL,	NULL,	'other'),
(17,	'WINES BOTTLES WHITE',	NULL,	NULL,	'other'),
(18,	'WINES BY GLASS',	NULL,	NULL,	'other'),
(19,	'SHOTS',	NULL,	NULL,	'other'),
(20,	'SPARKLING WINES',	NULL,	NULL,	'other'),
(21,	'CHAMPAGNES',	NULL,	NULL,	'other'),
(22,	'SHISHA',	NULL,	NULL,	'other'),
(23,	'FOOD',	NULL,	NULL,	'food'),
(24,	'MEAL',	NULL,	23,	'food'),
(25,	'SNACK',	NULL,	23,	'food');

DROP TABLE IF EXISTS `daily_closures`;
CREATE TABLE `daily_closures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `closure_date` date NOT NULL,
  `closed_by` int NOT NULL,
  `total_cash_expected` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_cash_actual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_card` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_mobile` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_tips` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_expenses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `variance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_closure` (`location_id`,`closure_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `daily_closures`;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `shift_id` int NOT NULL DEFAULT '0',
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
(1,	1,	72,	100.00,	1000.00),
(2,	2,	75,	10.00,	0.00),
(3,	3,	114,	100.00,	120.00),
(4,	4,	113,	100.00,	150.00),
(5,	5,	28,	100.00,	10.00),
(6,	6,	115,	2500.00,	200.00),
(7,	7,	6,	50.00,	0.00);

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
(1,	1,	1,	2,	100000.00,	'',	'2026-03-03 16:02:30'),
(2,	5,	1,	1,	0.00,	'',	'2026-03-09 21:33:16'),
(3,	2,	3,	2,	12000.00,	'',	'2026-03-10 16:16:50'),
(4,	4,	3,	2,	15000.00,	'',	'2026-03-10 16:17:34'),
(5,	5,	1,	2,	1000.00,	'COKEZ001',	'2026-03-12 16:46:18'),
(6,	4,	3,	2,	500000.00,	'',	'2026-03-12 17:39:14'),
(7,	1,	4,	2,	0.00,	'',	'2026-03-12 18:23:48');

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
(1,	72,	1,	6,	'2026-03-12 23:29:15'),
(2,	75,	1,	7,	'2026-03-11 08:53:28'),
(5,	110,	3,	8,	'2026-03-12 21:31:55'),
(6,	112,	3,	9,	'2026-03-10 16:30:55'),
(7,	114,	3,	100,	'2026-03-10 16:16:50'),
(8,	113,	3,	60,	'2026-03-12 17:44:51'),
(10,	28,	1,	94,	'2026-03-12 17:23:46'),
(15,	115,	3,	2499,	'2026-03-12 17:44:51'),
(17,	6,	4,	50,	'2026-03-12 18:23:48'),
(18,	114,	1,	-4,	'2026-03-12 23:27:40'),
(19,	112,	1,	-9,	'2026-03-12 23:27:40'),
(20,	113,	1,	-80,	'2026-03-12 22:54:23'),
(21,	115,	1,	-8,	'2026-03-12 22:54:23');

DROP TABLE IF EXISTS `inventory_logs`;
CREATE TABLE `inventory_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `change_qty` decimal(10,2) NOT NULL,
  `after_qty` decimal(10,2) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `reference_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `location_id` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `inventory_logs`;
INSERT INTO `inventory_logs` (`id`, `product_id`, `location_id`, `user_id`, `change_qty`, `after_qty`, `action_type`, `reference_id`, `created_at`, `reason`) VALUES
(1,	72,	1,	2,	100.00,	100.00,	'restock',	1,	'2026-03-03 16:02:30',	NULL),
(2,	72,	1,	2,	-1.00,	99.00,	'sale',	NULL,	'2026-03-03 16:02:52',	NULL),
(3,	72,	1,	2,	-2.00,	97.00,	'sale',	NULL,	'2026-03-03 16:28:26',	NULL),
(4,	72,	1,	2,	1.00,	98.00,	'void_return',	NULL,	'2026-03-03 16:31:06',	NULL),
(5,	72,	1,	2,	-10.00,	80.00,	'stock_take',	NULL,	'2026-03-03 17:07:30',	'lost to theft'),
(6,	72,	1,	2,	-1.00,	79.00,	'sale',	NULL,	'2026-03-03 18:20:11',	NULL),
(7,	72,	1,	2,	1.00,	80.00,	'void_return',	NULL,	'2026-03-04 13:30:30',	NULL),
(8,	72,	1,	13,	-1.00,	79.00,	'sale',	NULL,	'2026-03-04 15:14:10',	NULL),
(9,	72,	1,	2,	-1.00,	78.00,	'sale',	NULL,	'2026-03-04 16:44:49',	NULL),
(10,	72,	1,	2,	-1.00,	77.00,	'sale',	NULL,	'2026-03-04 17:00:34',	NULL),
(11,	72,	1,	2,	-3.00,	74.00,	'sale',	NULL,	'2026-03-04 17:28:28',	NULL),
(12,	72,	1,	2,	-1.00,	73.00,	'sale',	NULL,	'2026-03-04 17:56:16',	NULL),
(13,	72,	1,	2,	-1.00,	72.00,	'sale',	NULL,	'2026-03-04 19:00:01',	NULL),
(14,	72,	1,	2,	-1.00,	71.00,	'sale',	NULL,	'2026-03-04 19:01:15',	NULL),
(15,	72,	1,	2,	-1.00,	70.00,	'sale',	NULL,	'2026-03-04 20:06:42',	NULL),
(16,	72,	1,	2,	-1.00,	69.00,	'sale',	NULL,	'2026-03-04 20:28:57',	NULL),
(17,	72,	1,	2,	-1.00,	68.00,	'sale',	NULL,	'2026-03-04 20:29:22',	NULL),
(18,	72,	1,	2,	-1.00,	67.00,	'sale',	NULL,	'2026-03-04 20:41:39',	NULL),
(19,	72,	1,	2,	-1.00,	66.00,	'sale',	NULL,	'2026-03-04 20:43:37',	NULL),
(20,	72,	1,	2,	-1.00,	65.00,	'sale',	NULL,	'2026-03-06 01:10:34',	NULL),
(21,	72,	1,	2,	-1.00,	64.00,	'sale',	NULL,	'2026-03-06 01:11:06',	NULL),
(22,	72,	1,	2,	-1.00,	63.00,	'sale',	NULL,	'2026-03-06 01:11:35',	NULL),
(23,	72,	1,	2,	-1.00,	62.00,	'sale',	NULL,	'2026-03-06 01:41:18',	NULL),
(24,	72,	1,	2,	-1.00,	62.00,	'sale',	NULL,	'2026-03-06 07:33:40',	NULL),
(25,	72,	1,	2,	1.00,	63.00,	'void_return',	NULL,	'2026-03-06 09:18:14',	NULL),
(26,	72,	1,	2,	-1.00,	62.00,	'sale',	NULL,	'2026-03-06 18:22:45',	NULL),
(27,	72,	1,	2,	-1.00,	61.00,	'sale',	NULL,	'2026-03-06 18:23:02',	NULL),
(28,	75,	1,	1,	10.00,	10.00,	'restock',	2,	'2026-03-09 21:33:16',	NULL),
(29,	75,	1,	1,	-2.00,	8.00,	'transfer_out',	1,	'2026-03-09 21:51:46',	NULL),
(30,	75,	6,	1,	2.00,	2.00,	'transfer_in',	1,	'2026-03-09 21:51:50',	NULL),
(31,	72,	1,	1,	-4.00,	57.00,	'transfer_out',	2,	'2026-03-09 21:58:48',	NULL),
(32,	72,	6,	1,	4.00,	4.00,	'transfer_in',	2,	'2026-03-09 21:58:52',	NULL),
(33,	72,	6,	1,	-4.00,	0.00,	'stock_take',	NULL,	'2026-03-09 22:20:51',	'app update'),
(34,	75,	6,	1,	-2.00,	0.00,	'stock_take',	NULL,	'2026-03-09 22:20:51',	'Physical Count Adjustment'),
(35,	72,	1,	2,	-1.00,	52.00,	'sale',	NULL,	'2026-03-10 05:41:04',	NULL),
(36,	110,	3,	2,	10.00,	10.00,	'produce',	NULL,	'2026-03-10 16:15:54',	NULL),
(37,	112,	3,	2,	10.00,	10.00,	'produce',	NULL,	'2026-03-10 16:16:09',	NULL),
(38,	114,	3,	2,	100.00,	100.00,	'restock',	3,	'2026-03-10 16:16:50',	NULL),
(39,	113,	3,	2,	100.00,	100.00,	'restock',	4,	'2026-03-10 16:17:34',	NULL),
(40,	110,	3,	2,	-3.00,	7.00,	'transfer_out',	3,	'2026-03-10 16:27:25',	NULL),
(41,	110,	1,	2,	3.00,	3.00,	'transfer_in',	3,	'2026-03-10 16:27:27',	NULL),
(42,	113,	3,	2,	-5.00,	95.00,	'recipe_deduction',	NULL,	'2026-03-10 16:30:21',	NULL),
(43,	114,	3,	2,	-0.25,	99.75,	'recipe_deduction',	NULL,	'2026-03-10 16:30:55',	NULL),
(44,	112,	3,	2,	-1.00,	9.00,	'recipe_deduction',	NULL,	'2026-03-10 16:30:55',	NULL),
(45,	75,	1,	14,	-1.00,	7.00,	'sale',	NULL,	'2026-03-11 08:53:29',	NULL),
(46,	28,	1,	2,	100.00,	100.00,	'restock',	5,	'2026-03-12 16:46:18',	NULL),
(47,	28,	1,	2,	-2.00,	98.00,	'transfer_out',	4,	'2026-03-12 17:19:52',	NULL),
(48,	28,	3,	2,	2.00,	2.00,	'transfer_in',	4,	'2026-03-12 17:19:54',	NULL),
(49,	28,	1,	2,	-1.00,	97.00,	'sale',	NULL,	'2026-03-12 17:20:55',	NULL),
(50,	114,	1,	2,	-0.25,	-0.25,	'recipe_deduction',	NULL,	'2026-03-12 17:21:50',	NULL),
(51,	112,	1,	2,	-1.00,	-1.00,	'recipe_deduction',	NULL,	'2026-03-12 17:21:50',	NULL),
(52,	114,	1,	2,	0.25,	0.25,	'void_return',	NULL,	'2026-03-12 17:22:12',	NULL),
(53,	112,	1,	2,	1.00,	0.00,	'void_return',	NULL,	'2026-03-12 17:22:12',	NULL),
(54,	28,	1,	2,	-1.00,	96.00,	'sale',	NULL,	'2026-03-12 17:22:35',	NULL),
(55,	28,	1,	2,	-1.00,	95.00,	'sale',	NULL,	'2026-03-12 17:23:09',	NULL),
(56,	28,	1,	2,	-1.00,	94.00,	'sale',	NULL,	'2026-03-12 17:23:46',	NULL),
(57,	113,	1,	2,	-5.00,	-5.00,	'recipe_deduction',	NULL,	'2026-03-12 17:24:35',	NULL),
(58,	115,	3,	2,	2500.00,	2500.00,	'restock',	6,	'2026-03-12 17:39:14',	NULL),
(59,	113,	1,	2,	-10.00,	-15.00,	'recipe_deduction',	NULL,	'2026-03-12 17:40:07',	NULL),
(60,	115,	1,	2,	-0.50,	-0.50,	'recipe_deduction',	NULL,	'2026-03-12 17:40:07',	NULL),
(61,	113,	3,	2,	-10.00,	85.00,	'recipe_deduction',	NULL,	'2026-03-12 17:40:42',	NULL),
(62,	115,	3,	2,	-0.50,	2499.50,	'recipe_deduction',	NULL,	'2026-03-12 17:40:42',	NULL),
(63,	113,	3,	2,	-25.00,	60.00,	'recipe_deduction',	NULL,	'2026-03-12 17:44:51',	NULL),
(64,	115,	3,	2,	-1.25,	2498.75,	'recipe_deduction',	NULL,	'2026-03-12 17:44:51',	NULL),
(65,	6,	4,	2,	50.00,	50.00,	'restock',	7,	'2026-03-12 18:23:48',	NULL),
(66,	110,	3,	2,	1.00,	8.00,	'produce',	NULL,	'2026-03-12 21:31:55',	NULL),
(67,	114,	1,	1,	-0.25,	-0.25,	'online_sale',	NULL,	'2026-03-12 22:07:15',	NULL),
(68,	112,	1,	1,	-1.00,	-1.00,	'online_sale',	NULL,	'2026-03-12 22:07:15',	NULL),
(69,	114,	1,	1,	-0.50,	-0.50,	'online_sale',	NULL,	'2026-03-12 22:17:28',	NULL),
(70,	112,	1,	1,	-2.00,	-3.00,	'online_sale',	NULL,	'2026-03-12 22:17:28',	NULL),
(71,	113,	1,	1,	-10.00,	-10.00,	'online_sale',	NULL,	'2026-03-12 22:20:59',	NULL),
(72,	115,	1,	1,	-0.50,	-0.50,	'online_sale',	NULL,	'2026-03-12 22:20:59',	NULL),
(73,	113,	1,	1,	-10.00,	-20.00,	'online_sale',	NULL,	'2026-03-12 22:22:17',	NULL),
(74,	115,	1,	1,	-0.50,	-1.50,	'online_sale',	NULL,	'2026-03-12 22:22:17',	NULL),
(75,	113,	1,	1,	-10.00,	-30.00,	'online_sale',	NULL,	'2026-03-12 22:24:30',	NULL),
(76,	115,	1,	1,	-0.50,	-2.50,	'online_sale',	NULL,	'2026-03-12 22:24:30',	NULL),
(77,	113,	1,	1,	-10.00,	-40.00,	'online_sale',	NULL,	'2026-03-12 22:37:52',	NULL),
(78,	115,	1,	1,	-0.50,	-3.50,	'online_sale',	NULL,	'2026-03-12 22:37:52',	NULL),
(79,	72,	1,	1,	-2.00,	16.00,	'online_sale',	NULL,	'2026-03-12 22:40:00',	NULL),
(80,	72,	1,	1,	-2.00,	14.00,	'online_sale',	NULL,	'2026-03-12 22:41:44',	NULL),
(81,	113,	1,	1,	-10.00,	-50.00,	'online_sale',	NULL,	'2026-03-12 22:43:17',	NULL),
(82,	115,	1,	1,	-0.50,	-4.50,	'online_sale',	NULL,	'2026-03-12 22:43:17',	NULL),
(83,	113,	1,	1,	-10.00,	-60.00,	'online_sale',	NULL,	'2026-03-12 22:44:39',	NULL),
(84,	115,	1,	1,	-0.50,	-5.50,	'online_sale',	NULL,	'2026-03-12 22:44:39',	NULL),
(85,	113,	1,	1,	-10.00,	-70.00,	'online_sale',	NULL,	'2026-03-12 22:45:30',	NULL),
(86,	115,	1,	1,	-0.50,	-6.50,	'online_sale',	NULL,	'2026-03-12 22:45:30',	NULL),
(87,	72,	1,	1,	-2.00,	12.00,	'online_sale',	NULL,	'2026-03-12 22:46:41',	NULL),
(88,	72,	1,	1,	-2.00,	10.00,	'online_sale',	NULL,	'2026-03-12 22:53:20',	NULL),
(89,	113,	1,	1,	-10.00,	-80.00,	'online_sale',	NULL,	'2026-03-12 22:54:23',	NULL),
(90,	115,	1,	1,	-0.50,	-7.50,	'online_sale',	NULL,	'2026-03-12 22:54:23',	NULL),
(91,	72,	1,	1,	-2.00,	8.00,	'online_sale',	NULL,	'2026-03-12 22:59:44',	NULL),
(92,	114,	1,	1,	-0.50,	-1.50,	'online_sale',	NULL,	'2026-03-12 23:01:11',	NULL),
(93,	112,	1,	1,	-2.00,	-5.00,	'online_sale',	NULL,	'2026-03-12 23:01:11',	NULL),
(94,	114,	1,	1,	-0.50,	-2.50,	'online_sale',	NULL,	'2026-03-12 23:09:13',	NULL),
(95,	112,	1,	1,	-2.00,	-7.00,	'online_sale',	NULL,	'2026-03-12 23:09:13',	NULL),
(96,	114,	1,	1,	-0.50,	-3.50,	'online_sale',	NULL,	'2026-03-12 23:27:40',	NULL),
(97,	112,	1,	1,	-2.00,	-9.00,	'online_sale',	NULL,	'2026-03-12 23:27:40',	NULL),
(98,	72,	1,	1,	-2.00,	6.00,	'online_sale',	NULL,	'2026-03-12 23:29:15',	NULL);

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
(1,	1,	6,	75,	2.00,	1,	'completed',	'2026-03-09 23:51:41',	'2026-03-09 23:51:46',	'2026-03-09 23:51:50'),
(2,	1,	6,	72,	4.00,	1,	'completed',	'2026-03-09 23:58:44',	'2026-03-09 23:58:48',	'2026-03-09 23:58:52'),
(3,	3,	1,	110,	3.00,	2,	'completed',	'2026-03-10 18:27:20',	'2026-03-10 18:27:25',	'2026-03-10 18:27:27'),
(4,	1,	3,	28,	2.00,	2,	'completed',	'2026-03-12 19:19:45',	'2026-03-12 19:19:52',	'2026-03-12 19:19:54');

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
(1,	6,	1,	0.00),
(2,	6,	2,	0.00),
(3,	6,	3,	0.00),
(4,	6,	4,	0.00),
(5,	6,	5,	0.00),
(6,	6,	6,	0.00),
(7,	6,	7,	0.00),
(8,	6,	8,	0.00),
(9,	6,	9,	0.00),
(10,	6,	10,	0.00),
(11,	6,	11,	0.00),
(12,	6,	12,	0.00),
(13,	6,	13,	0.00),
(14,	6,	14,	0.00),
(15,	6,	15,	0.00),
(16,	6,	16,	0.00),
(17,	6,	17,	0.00),
(18,	6,	18,	0.00),
(19,	6,	19,	0.00),
(20,	6,	20,	0.00),
(21,	6,	21,	0.00),
(22,	6,	22,	0.00),
(23,	6,	23,	0.00),
(24,	6,	24,	0.00),
(25,	6,	25,	0.00),
(26,	6,	26,	0.00),
(27,	6,	27,	0.00),
(28,	6,	28,	0.00),
(29,	6,	29,	0.00),
(30,	6,	30,	0.00),
(31,	6,	31,	0.00),
(32,	6,	32,	0.00),
(33,	6,	33,	0.00),
(34,	6,	34,	0.00),
(35,	6,	35,	0.00),
(36,	6,	36,	0.00),
(37,	6,	37,	0.00),
(38,	6,	38,	0.00),
(39,	6,	39,	0.00),
(40,	6,	40,	0.00),
(41,	6,	41,	0.00),
(42,	6,	42,	0.00),
(43,	6,	43,	0.00),
(44,	6,	44,	0.00),
(45,	6,	45,	0.00),
(46,	6,	46,	0.00),
(47,	6,	47,	0.00),
(48,	6,	48,	0.00),
(49,	6,	49,	0.00),
(50,	6,	50,	0.00),
(51,	6,	51,	0.00),
(52,	6,	52,	0.00),
(53,	6,	53,	0.00),
(54,	6,	54,	0.00),
(55,	6,	55,	0.00),
(56,	6,	56,	0.00),
(57,	6,	57,	0.00),
(58,	6,	58,	0.00),
(59,	6,	59,	0.00),
(60,	6,	60,	0.00),
(61,	6,	61,	0.00),
(62,	6,	62,	0.00),
(63,	6,	63,	0.00),
(64,	6,	64,	0.00),
(65,	6,	65,	0.00),
(66,	6,	66,	0.00),
(67,	6,	67,	0.00),
(68,	6,	68,	0.00),
(69,	6,	69,	0.00),
(70,	6,	70,	0.00),
(71,	6,	71,	0.00),
(72,	6,	72,	19.00),
(73,	6,	73,	0.00),
(74,	6,	74,	0.00),
(75,	6,	75,	0.00),
(76,	6,	76,	0.00),
(77,	6,	77,	0.00),
(78,	6,	78,	0.00),
(79,	6,	79,	0.00),
(80,	6,	80,	0.00),
(81,	6,	81,	0.00),
(82,	6,	82,	0.00),
(83,	6,	83,	0.00),
(84,	6,	84,	0.00),
(85,	6,	85,	0.00),
(86,	6,	86,	0.00),
(87,	6,	87,	0.00),
(88,	6,	88,	0.00),
(89,	6,	89,	0.00),
(90,	6,	90,	0.00),
(91,	6,	91,	0.00),
(92,	6,	92,	0.00),
(93,	6,	93,	0.00),
(94,	6,	94,	0.00),
(95,	6,	95,	0.00),
(96,	6,	96,	0.00),
(97,	6,	97,	0.00),
(98,	6,	98,	0.00),
(99,	6,	99,	0.00),
(100,	6,	100,	0.00),
(101,	6,	101,	0.00),
(102,	6,	102,	0.00),
(103,	6,	103,	0.00),
(104,	6,	104,	0.00),
(105,	6,	105,	0.00),
(106,	6,	106,	0.00),
(107,	6,	107,	0.00),
(108,	6,	108,	0.00),
(109,	6,	109,	0.00),
(110,	1,	110,	0.00),
(111,	1,	111,	0.00),
(112,	1,	112,	0.00),
(113,	1,	113,	0.00),
(114,	1,	114,	0.00),
(115,	6,	115,	0.00);

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
(1,	'main bar',	'store',	1,	0,	'',	'555-0000'),
(2,	'outside bar',	'store',	1,	0,	'',	'555-0000'),
(3,	'Kitchen',	'warehouse',	1,	0,	'',	'555-0000'),
(4,	'Main Warehouse',	'warehouse',	1,	0,	'',	'555-0000'),
(5,	'mini storeroom',	'store',	1,	0,	'',	'555-0000'),
(6,	'HQ',	'warehouse',	1,	0,	'',	'555-0000');

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
(1,	26,	'Heiniken Silver',	'ready',	NULL,	'2026-03-09 21:04:48'),
(2,	43,	'POTATO SALADS',	'collected',	12,	'2026-03-10 16:31:28'),
(3,	44,	'CHICKEN WRAP',	'collected',	13,	'2026-03-10 16:31:28'),
(4,	45,	'CHICKEN WRAP',	'ready',	NULL,	'2026-03-10 16:44:42'),
(5,	47,	'CHICKEN WRAP',	'ready',	NULL,	'2026-03-11 06:36:31'),
(6,	55,	'POTATO SALADS',	'collected',	12,	'2026-03-12 17:25:01'),
(7,	56,	'POTATO SALADS',	'collected',	13,	'2026-03-12 17:41:01'),
(8,	57,	'POTATO SALADS',	'collected',	13,	'2026-03-12 17:41:02'),
(9,	58,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 21:02:41'),
(10,	66,	'CHICKEN WRAP',	'collected',	13,	'2026-03-12 22:08:03'),
(11,	67,	'CHICKEN WRAP',	'collected',	13,	'2026-03-12 22:17:50'),
(12,	68,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 22:22:53'),
(13,	69,	'POTATO SALADS',	'collected',	13,	'2026-03-12 22:22:54'),
(14,	70,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 22:25:57'),
(15,	71,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 22:38:16'),
(16,	74,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 22:43:26'),
(17,	75,	'POTATO SALADS',	'collected',	13,	'2026-03-12 22:44:48'),
(18,	76,	'POTATO SALADS',	'collected',	13,	'2026-03-12 22:45:54'),
(19,	79,	'POTATO SALADS',	'ready',	NULL,	'2026-03-12 22:54:48'),
(20,	81,	'CHICKEN WRAP',	'collected',	13,	'2026-03-12 23:01:39'),
(21,	82,	'CHICKEN WRAP',	'ready',	NULL,	'2026-03-12 23:09:26'),
(22,	83,	'CHICKEN WRAP',	'ready',	NULL,	'2026-03-12 23:28:05');

DROP TABLE IF EXISTS `product_recipes`;
CREATE TABLE `product_recipes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_product_id` int NOT NULL COMMENT 'The sellable cocktail/meal',
  `ingredient_product_id` int NOT NULL COMMENT 'The raw bottle/ingredient',
  `quantity` decimal(10,4) NOT NULL COMMENT 'Amount deducted per sale (e.g., 0.05 for 50ml)',
  PRIMARY KEY (`id`),
  KEY `parent_idx` (`parent_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `product_recipes`;
INSERT INTO `product_recipes` (`id`, `parent_product_id`, `ingredient_product_id`, `quantity`) VALUES
(2,	110,	114,	0.2500),
(3,	110,	112,	1.0000),
(4,	112,	113,	5.0000),
(5,	112,	115,	0.2500);

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
  `tax_class` varchar(10) NOT NULL DEFAULT 'A' COMMENT 'ZRA Tax Class (A=16%, B=0%, C=Exempt)',
  `unspsc_code` varchar(20) DEFAULT NULL COMMENT 'UNSPSC 8-digit Commodity Code',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`, `type`, `is_open_price`, `tax_class`, `unspsc_code`) VALUES
(1,	'Hunters Gold/Dry',	NULL,	50.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(2,	'Savanna Dry',	NULL,	55.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(3,	'Flying fish can',	NULL,	40.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(4,	'Flying fish bottle',	NULL,	30.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(5,	'Brutal fruit can',	NULL,	40.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(6,	'Brutal fruit bottle',	NULL,	30.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(7,	'Belgravia Can',	NULL,	60.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(8,	'Belgravia bottle',	NULL,	60.00,	0.00,	'',	1,	1,	'item',	0,	'A',	NULL),
(9,	'Castle lager',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(10,	'Mosi lager',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(11,	'Black label',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(12,	'Castle light can',	NULL,	40.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(13,	'Castle light bottle',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(14,	'Heiniken Malt',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(15,	'Heiniken Silver',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(16,	'Corona',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(17,	'Budweiser',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(18,	'Windhoek lager',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(19,	'Windhoek draft',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(20,	'Stella Artois',	NULL,	35.00,	0.00,	'',	2,	1,	'item',	0,	'A',	NULL),
(21,	'STILL WATER',	NULL,	10.00,	0.00,	'',	3,	1,	'item',	0,	'A',	NULL),
(22,	'Mango',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(23,	'Orange',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(24,	'Cranberry',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(25,	'Red grape',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(26,	'Apple',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(27,	'Fruitcana',	NULL,	15.00,	0.00,	'',	4,	1,	'item',	0,	'A',	NULL),
(28,	'Coke Zero',	NULL,	20.00,	10.00,	'',	5,	1,	'item',	0,	'A',	NULL),
(29,	'Coke/Fanta/Sprite bottle',	NULL,	15.00,	0.00,	'',	5,	1,	'item',	0,	'A',	NULL),
(30,	'Coke/Fanta/Sprite Disposable',	NULL,	20.00,	0.00,	'',	5,	1,	'item',	0,	'A',	NULL),
(31,	'Jameson cocktail',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(32,	'White Russian',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(33,	'Classic Daquiri',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(34,	'Strawberry Daquiri',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(35,	'Tom Collins',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(36,	'Sex on the beach',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(37,	'Whisky Sour',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(38,	'Gin Sour',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(39,	'Martini (Vodka, Gin, Espresso)',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(40,	'Margarita',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(41,	'Cosmopolitan',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(42,	'Mojito',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(43,	'Pina Colada',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(44,	'Long Island',	NULL,	200.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(45,	'Sparkling waters delight',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0,	'A',	NULL),
(46,	'Virgin Mojito',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(47,	'Virgin Daquiri',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(48,	'Sunset Island',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(49,	'Virgin Captain Colada',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(50,	'Malawi shandy',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(51,	'Rocky Shandy',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(52,	'Brothers mocktails',	NULL,	25.00,	0.00,	'',	7,	1,	'item',	0,	'A',	NULL),
(53,	'Ginger ale',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0,	'A',	NULL),
(54,	'Tonic water',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0,	'A',	NULL),
(55,	'Soda Water',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0,	'A',	NULL),
(56,	'Lemonade',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0,	'A',	NULL),
(57,	'Glenfiddich 15',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(58,	'Glenfiddich 12',	NULL,	80.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(59,	'Chivas 12',	NULL,	1000.00,	0.00,	'',	9,	1,	'item',	0,	'A',	NULL),
(60,	'J & B',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(61,	'Jameson blackbarrel',	NULL,	1200.00,	0.00,	'',	9,	1,	'item',	0,	'A',	NULL),
(62,	'Jameson Original',	NULL,	850.00,	0.00,	'',	9,	1,	'item',	0,	'A',	NULL),
(63,	'Jameson Castmate',	NULL,	1000.00,	0.00,	'',	9,	1,	'item',	0,	'A',	NULL),
(64,	'Jack Daniels',	NULL,	60.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(65,	'Southern Comfort',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(66,	'Gordons',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(67,	'Gordons Pink',	NULL,	500.00,	0.00,	'',	10,	1,	'item',	0,	'A',	NULL),
(68,	'Beefeater',	NULL,	600.00,	0.00,	'',	10,	1,	'item',	0,	'A',	NULL),
(69,	'Beefeater Pink',	NULL,	600.00,	0.00,	'',	10,	1,	'item',	0,	'A',	NULL),
(70,	'Henessy',	NULL,	1400.00,	0.00,	'',	11,	1,	'item',	0,	'A',	NULL),
(71,	'Remmy Martin',	NULL,	1950.00,	0.00,	'',	11,	1,	'item',	0,	'A',	NULL),
(72,	'Amarula',	NULL,	50.00,	1000.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(73,	'Wild Africa',	NULL,	700.00,	0.00,	'',	12,	1,	'item',	0,	'A',	NULL),
(74,	'Strawberry lips',	NULL,	550.00,	0.00,	'',	12,	1,	'item',	0,	'A',	NULL),
(75,	'Amarula small',	NULL,	250.00,	0.00,	'',	12,	1,	'item',	0,	'A',	NULL),
(76,	'Klipdrift',	NULL,	40.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(77,	'Klipdrift Premium',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(78,	'KWV5',	NULL,	600.00,	0.00,	'',	13,	1,	'item',	0,	'A',	NULL),
(79,	'Absolut Vodka',	NULL,	800.00,	0.00,	'',	14,	1,	'item',	0,	'A',	NULL),
(80,	'Grey Goose',	NULL,	1250.00,	0.00,	'',	14,	1,	'item',	0,	'A',	NULL),
(81,	'Ciroc',	NULL,	1400.00,	0.00,	'',	14,	1,	'item',	0,	'A',	NULL),
(82,	'Captain Morgan dark rum',	NULL,	700.00,	0.00,	'',	15,	1,	'item',	0,	'A',	NULL),
(83,	'Captain Morgan spiced Rum',	NULL,	700.00,	0.00,	'',	15,	1,	'item',	0,	'A',	NULL),
(84,	'Four Cousins',	NULL,	250.00,	0.00,	'',	17,	1,	'item',	0,	'A',	NULL),
(85,	'Cronier',	NULL,	450.00,	0.00,	'',	16,	1,	'item',	0,	'A',	NULL),
(86,	'Nederberg',	NULL,	400.00,	0.00,	'',	16,	1,	'item',	0,	'A',	NULL),
(87,	'KWV',	NULL,	400.00,	0.00,	'',	17,	1,	'item',	0,	'A',	NULL),
(88,	'Fat bastard Golden Reserve',	NULL,	600.00,	0.00,	'',	16,	1,	'item',	0,	'A',	NULL),
(89,	'Fat bastard Merlot',	NULL,	500.00,	0.00,	'',	16,	1,	'item',	0,	'A',	NULL),
(90,	'Sunkissed',	NULL,	300.00,	0.00,	'',	17,	1,	'item',	0,	'A',	NULL),
(91,	'Robertson',	NULL,	350.00,	0.00,	'',	16,	1,	'item',	0,	'A',	NULL),
(92,	'Four Cousins red',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0,	'A',	NULL),
(93,	'Overmeer',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0,	'A',	NULL),
(94,	'Chateau Delrei',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0,	'A',	NULL),
(95,	'Jameson black barrel',	NULL,	80.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(96,	'Jameson whisky',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(97,	'Absolute Vodka',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(98,	'Tequila',	NULL,	55.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(99,	'Jagermeister',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(100,	'Blow Job',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(101,	'Jager bomb',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(102,	'Hennessy',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(103,	'Captain Morgan',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0,	'A',	NULL),
(104,	'J.C Le Roux',	NULL,	600.00,	0.00,	'',	20,	1,	'item',	0,	'A',	NULL),
(105,	'Moet Nector',	NULL,	2600.00,	0.00,	'',	21,	1,	'item',	0,	'A',	NULL),
(106,	'Moet Brut',	NULL,	3000.00,	0.00,	'',	21,	1,	'item',	0,	'A',	NULL),
(107,	'Verve Clicquot',	NULL,	3600.00,	0.00,	'',	21,	1,	'item',	0,	'A',	NULL),
(108,	'Verve Rich',	NULL,	3800.00,	0.00,	'',	21,	1,	'item',	0,	'A',	NULL),
(109,	'All Flavours Shisha',	NULL,	150.00,	0.00,	'',	22,	1,	'item',	0,	'A',	NULL),
(110,	'CHICKEN WRAP',	'CWR001',	170.00,	100.00,	'PLATE',	24,	1,	'item',	0,	'A',	NULL),
(111,	'BEFF BURGER',	'BBGR001',	65.00,	30.00,	'PLATE',	24,	1,	'item',	0,	'A',	NULL),
(112,	'POTATO SALADS',	'PTT001',	45.00,	0.00,	'unit',	23,	1,	'item',	0,	'A',	NULL),
(113,	'POTATOES',	'PT001',	0.00,	150.00,	'unit',	23,	1,	'item',	0,	'A',	NULL),
(114,	'CHIKEN',	'CKNR001',	0.00,	120.00,	'unit',	23,	1,	'item',	0,	'A',	NULL),
(115,	'COOKING OIL',	'OIL001',	0.00,	200.00,	'Ltr',	23,	1,	'item',	0,	'A',	NULL);

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
INSERT INTO `refunds` (`id`, `sale_id`, `manager_id`, `amount_refunded`, `reason`, `created_at`) VALUES
(1,	22,	2,	50.00,	'Partial Refund',	'2026-03-06 02:05:45');

DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE `restaurant_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL DEFAULT '0',
  `zone_name` varchar(50) NOT NULL DEFAULT 'Main Dining',
  `table_name` varchar(50) NOT NULL,
  `capacity` int NOT NULL DEFAULT '4',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `restaurant_tables`;
INSERT INTO `restaurant_tables` (`id`, `location_id`, `zone_name`, `table_name`, `capacity`) VALUES
(1,	1,	'Main Dining',	'Table 1',	4),
(2,	1,	'Main Dining',	'Table 2',	4),
(3,	1,	'Main Dining',	'Table 3',	6),
(4,	1,	'Main Dining',	'Table 4',	2),
(5,	1,	'Patio / Outside',	'Patio 1',	4),
(6,	1,	'Patio / Outside',	'Patio 2',	4),
(7,	1,	'VIP Bar',	'Bar Stool A',	1),
(8,	1,	'VIP Bar',	'Bar Stool B',	1),
(9,	2,	'outsite',	'Oustside table 1',	4);

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_at_sale` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cost_at_sale` decimal(10,2) DEFAULT '0.00',
  `status` varchar(20) NOT NULL DEFAULT 'ready',
  `updated_at` timestamp NULL DEFAULT NULL,
  `fulfillment_status` varchar(20) NOT NULL DEFAULT 'collected',
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `sale_items`;
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `price_at_sale`, `cost_at_sale`, `status`, `updated_at`, `fulfillment_status`) VALUES
(1,	1,	72,	1,	50.00,	0.00,	0.00,	'voided',	NULL,	'voided'),
(2,	2,	72,	2,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(3,	3,	72,	1,	50.00,	0.00,	0.00,	'voided',	NULL,	'voided'),
(4,	4,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(5,	5,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(6,	6,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(7,	7,	72,	3,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(8,	8,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(9,	9,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(10,	10,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(11,	11,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(12,	12,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(13,	13,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(14,	14,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(15,	15,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(16,	22,	72,	1,	50.00,	0.00,	0.00,	'refunded',	NULL,	'collected'),
(17,	17,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(18,	21,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(19,	21,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(20,	23,	72,	1,	50.00,	0.00,	0.00,	'voided',	NULL,	'voided'),
(21,	25,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(22,	25,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(23,	26,	15,	2,	70.00,	70.00,	0.00,	'ready',	NULL,	'pending'),
(24,	27,	72,	1,	45.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(25,	28,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(26,	29,	72,	1,	100.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(27,	30,	72,	1,	100.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(28,	31,	72,	1,	100.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(29,	32,	72,	1,	50.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(30,	33,	72,	1,	100.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(31,	34,	72,	1,	100.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(32,	37,	72,	1,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(33,	38,	72,	1,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(34,	39,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(35,	40,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(36,	41,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(37,	42,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(38,	43,	112,	1,	45.00,	0.00,	0.00,	'served',	NULL,	'collected'),
(39,	44,	110,	1,	170.00,	0.00,	0.00,	'served',	NULL,	'collected'),
(40,	45,	110,	1,	170.00,	170.00,	0.00,	'ready',	NULL,	'collected'),
(41,	46,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(42,	47,	110,	2,	170.00,	170.00,	0.00,	'ready',	NULL,	'collected'),
(43,	51,	75,	1,	250.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(44,	49,	72,	3,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(45,	51,	28,	1,	20.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(46,	52,	110,	1,	170.00,	0.00,	0.00,	'voided',	NULL,	'voided'),
(47,	53,	28,	1,	20.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(48,	54,	28,	1,	20.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(49,	53,	28,	1,	20.00,	0.00,	0.00,	'ready',	NULL,	'collected'),
(50,	55,	112,	1,	45.00,	0.00,	0.00,	'served',	NULL,	'collected'),
(51,	56,	112,	2,	45.00,	0.00,	0.00,	'served',	NULL,	'collected'),
(52,	57,	112,	2,	45.00,	0.00,	0.00,	'served',	NULL,	'collected'),
(53,	58,	112,	5,	45.00,	0.00,	0.00,	'ready',	NULL,	'uncollected'),
(54,	59,	72,	3,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(55,	60,	72,	3,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected'),
(56,	61,	72,	3,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(57,	62,	72,	3,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(58,	63,	72,	3,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(59,	64,	72,	1,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(60,	65,	72,	1,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(61,	66,	110,	1,	170.00,	170.00,	0.00,	'served',	NULL,	'collected'),
(62,	67,	110,	2,	170.00,	170.00,	0.00,	'served',	NULL,	'collected'),
(63,	68,	112,	2,	45.00,	45.00,	0.00,	'ready',	NULL,	'collected'),
(64,	69,	112,	2,	45.00,	45.00,	0.00,	'served',	NULL,	'collected'),
(65,	70,	112,	2,	45.00,	45.00,	0.00,	'ready',	NULL,	'collected'),
(66,	71,	112,	2,	45.00,	45.00,	0.00,	'ready',	NULL,	'collected'),
(67,	72,	72,	2,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(68,	73,	72,	2,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(69,	74,	112,	2,	45.00,	45.00,	0.00,	'ready',	NULL,	'collected'),
(70,	75,	112,	2,	45.00,	45.00,	0.00,	'served',	NULL,	'collected'),
(71,	76,	112,	2,	45.00,	45.00,	0.00,	'served',	NULL,	'collected'),
(72,	77,	72,	2,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(73,	78,	72,	2,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(74,	79,	112,	2,	45.00,	45.00,	0.00,	'ready',	NULL,	'collected'),
(75,	80,	72,	2,	50.00,	50.00,	0.00,	'served',	NULL,	'collected'),
(76,	81,	110,	2,	170.00,	170.00,	0.00,	'served',	NULL,	'collected'),
(77,	82,	110,	2,	170.00,	170.00,	0.00,	'ready',	NULL,	'collected'),
(78,	83,	110,	2,	170.00,	170.00,	0.00,	'ready',	NULL,	'collected'),
(79,	84,	72,	2,	50.00,	50.00,	0.00,	'ready',	NULL,	'collected');

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL,
  `table_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `shift_id` int DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) DEFAULT '0.00',
  `total_tax` decimal(10,2) DEFAULT '0.00',
  `tip` decimal(10,2) DEFAULT '0.00',
  `final_total` decimal(10,2) DEFAULT '0.00',
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `status` enum('pending','completed','refund_requested','refunded','partially_refunded') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `collected_by` varchar(100) DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'paid',
  `customer_name` varchar(100) DEFAULT 'Walk-in',
  `amount_tendered` decimal(10,2) DEFAULT '0.00',
  `change_due` decimal(10,2) DEFAULT '0.00',
  `member_id` int DEFAULT NULL,
  `points_earned` decimal(10,2) DEFAULT '0.00',
  `points_redeemed` decimal(10,2) DEFAULT '0.00',
  `split_group_id` varchar(50) DEFAULT NULL,
  `split_type` enum('none','item','even','custom') DEFAULT 'none',
  `tip_amount` decimal(10,2) DEFAULT '0.00',
  `split_method_1` varchar(50) DEFAULT NULL,
  `split_amount_1` decimal(10,2) DEFAULT '0.00',
  `split_method_2` varchar(50) DEFAULT NULL,
  `split_amount_2` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `sales`;
INSERT INTO `sales` (`id`, `location_id`, `table_id`, `user_id`, `shift_id`, `subtotal`, `total_amount`, `discount`, `total_tax`, `tip`, `final_total`, `payment_method`, `status`, `created_at`, `collected_by`, `payment_status`, `customer_name`, `amount_tendered`, `change_due`, `member_id`, `points_earned`, `points_redeemed`, `split_group_id`, `split_type`, `tip_amount`, `split_method_1`, `split_amount_1`, `split_method_2`, `split_amount_2`) VALUES
(1,	1,	1,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-03 16:02:51',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(2,	1,	NULL,	2,	2,	100.00,	0.00,	0.00,	0.00,	0.00,	100.00,	'Split',	'completed',	'2026-03-03 16:28:26',	NULL,	'paid',	'Walk-in',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(3,	1,	1,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-03 18:20:11',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(4,	1,	NULL,	13,	4,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 15:14:10',	NULL,	'paid',	'Walk-in',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(5,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 16:44:49',	NULL,	'paid',	'Walk-in',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(6,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 17:00:34',	NULL,	'paid',	'Walk-in',	100.00,	50.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(7,	1,	1,	2,	2,	150.00,	0.00,	0.00,	0.00,	0.00,	150.00,	'Cash',	'completed',	'2026-03-04 17:28:28',	NULL,	'paid',	'Table 1',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(8,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Split',	'completed',	'2026-03-04 17:56:16',	NULL,	'paid',	'',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(9,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 19:00:01',	NULL,	'paid',	'',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(10,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 19:01:15',	NULL,	'paid',	'',	70.00,	20.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(11,	1,	1,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Split',	'completed',	'2026-03-04 20:06:42',	NULL,	'paid',	'Table 1',	70.00,	20.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	20.00,	'Cash',	50.00),
(12,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-04 20:28:57',	NULL,	'paid',	'',	100.00,	50.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(13,	1,	1,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Split',	'completed',	'2026-03-04 20:29:22',	NULL,	'paid',	'Table 1',	70.00,	20.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	20.00,	'Cash',	50.00),
(14,	1,	1,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Split',	'completed',	'2026-03-04 20:41:39',	NULL,	'paid',	'Table 1',	60.00,	10.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	40.00,	'Cash',	20.00),
(15,	1,	1,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Split',	'completed',	'2026-03-04 20:43:36',	NULL,	'paid',	'Table 1',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	30.00,	'Cash',	20.00),
(16,	1,	1,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 01:10:33',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(17,	1,	2,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-06 01:11:06',	NULL,	'paid',	'Table 2',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(18,	1,	5,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 01:41:18',	NULL,	'voided',	'Patio 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(19,	1,	6,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 01:56:39',	NULL,	'voided',	'Transferred Tab 931',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(20,	1,	5,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 01:58:25',	NULL,	'voided',	'Transferred Tab 404',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(21,	1,	2,	2,	2,	100.00,	0.00,	0.00,	0.00,	0.00,	100.00,	'Cash',	'completed',	'2026-03-06 02:03:07',	NULL,	'paid',	'Table 2',	100.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(22,	1,	7,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	-50.00,	'Split',	'completed',	'2026-03-06 02:03:57',	NULL,	'paid',	'Bar Stool A',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	25.00,	'Cash',	25.00),
(23,	1,	1,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 07:33:40',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(24,	1,	1,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-06 18:22:44',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(25,	1,	2,	2,	2,	100.00,	0.00,	0.00,	0.00,	0.00,	100.00,	'Cash',	'completed',	'2026-03-06 18:23:02',	NULL,	'paid',	'Table 2',	100.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(26,	1,	NULL,	1,	NULL,	140.00,	140.00,	0.00,	0.00,	0.00,	140.00,	'UberEats',	'completed',	'2026-03-08 17:09:22',	NULL,	'paid',	'Online: John Doe',	140.00,	0.00,	NULL,	0.00,	0.00,	'UBER-987654321',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(27,	6,	NULL,	1,	2,	0.00,	45.00,	0.00,	0.00,	0.00,	0.00,	'Online',	'completed',	'2026-03-09 22:17:55',	NULL,	'paid',	'Online: Mando',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-999',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(28,	1,	NULL,	1,	2,	0.00,	50.00,	0.00,	0.00,	0.00,	0.00,	'Online',	'completed',	'2026-03-09 22:31:24',	NULL,	'paid',	'Online: Mando',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-99',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(29,	1,	NULL,	1,	2,	0.00,	100.00,	0.00,	0.00,	0.00,	0.00,	'Online',	'completed',	'2026-03-09 23:24:51',	NULL,	'paid',	'ONLINE: Mando Online',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(30,	1,	NULL,	1,	2,	0.00,	100.00,	0.00,	0.00,	0.00,	0.00,	'Online',	'completed',	'2026-03-09 23:26:53',	NULL,	'paid',	'ONLINE: Mando Online',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(31,	1,	NULL,	1,	2,	0.00,	100.00,	0.00,	0.00,	0.00,	0.00,	'Online',	'completed',	'2026-03-10 05:36:44',	NULL,	'paid',	'ONLINE: Mando Online',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(32,	1,	NULL,	2,	2,	50.00,	0.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-10 05:41:04',	NULL,	'paid',	'Walk-in',	50.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(33,	1,	NULL,	1,	6,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Online',	'completed',	'2026-03-10 06:09:11',	NULL,	'paid',	'ONLINE: Mando Online',	0.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(34,	1,	NULL,	1,	6,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-10 08:20:19',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(35,	6,	NULL,	2,	1,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Card',	'completed',	'2026-03-10 08:21:05',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	100.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(36,	6,	NULL,	2,	1,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Card',	'completed',	'2026-03-10 08:28:03',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	100.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(37,	1,	NULL,	1,	6,	50.00,	50.00,	0.00,	0.00,	0.00,	50.00,	'Card',	'completed',	'2026-03-10 08:52:35',	NULL,	'paid',	'ONLINE: Mando Online',	50.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(38,	1,	NULL,	1,	1,	50.00,	50.00,	0.00,	0.00,	0.00,	50.00,	'Card',	'completed',	'2026-03-10 09:04:32',	NULL,	'paid',	'ONLINE: Mando Online',	50.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(39,	1,	NULL,	1,	2,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Airtel Money',	'completed',	'2026-03-10 09:11:20',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(40,	1,	NULL,	1,	2,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-10 09:16:47',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-99',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(41,	1,	NULL,	1,	2,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Split',	'completed',	'2026-03-10 09:27:49',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-100',	'none',	0.00,	'Card',	70.00,	'Cash',	30.00),
(42,	1,	NULL,	1,	2,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-10 09:41:22',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-100',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(43,	3,	NULL,	2,	7,	45.00,	0.00,	0.00,	0.00,	0.00,	45.00,	'Cash',	'completed',	'2026-03-10 16:30:21',	NULL,	'paid',	'Walk-in',	45.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(44,	3,	NULL,	2,	7,	170.00,	0.00,	0.00,	0.00,	0.00,	170.00,	'Cash',	'completed',	'2026-03-10 16:30:55',	NULL,	'paid',	'',	170.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(45,	1,	NULL,	1,	7,	170.00,	170.00,	0.00,	0.00,	0.00,	170.00,	'Card',	'completed',	'2026-03-10 16:44:21',	NULL,	'paid',	'ONLINE: Mando Foodie',	170.00,	0.00,	NULL,	0.00,	0.00,	'UBER-KDS-TEST-2',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(46,	1,	NULL,	1,	1,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-11 06:31:24',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-101',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(47,	1,	NULL,	1,	1,	340.00,	340.00,	0.00,	0.00,	0.00,	340.00,	'Card',	'completed',	'2026-03-11 06:35:58',	NULL,	'paid',	'ONLINE: Mando Online',	340.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-101',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(48,	1,	1,	14,	8,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-11 08:53:28',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(49,	1,	NULL,	1,	9,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Card',	'completed',	'2026-03-11 08:57:06',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-102',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(50,	1,	1,	1,	6,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-12 16:37:25',	NULL,	'voided',	'Table 1',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(51,	1,	2,	1,	2,	270.00,	0.00,	0.00,	0.00,	0.00,	270.00,	'Cash',	'completed',	'2026-03-12 16:37:39',	NULL,	'paid',	'Table 2',	300.00,	30.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(52,	1,	NULL,	2,	2,	0.00,	0.00,	0.00,	0.00,	0.00,	0.00,	'Pending',	'completed',	'2026-03-12 17:21:50',	NULL,	'voided',	'mando',	0.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	NULL,	0.00,	NULL,	0.00),
(53,	1,	1,	2,	2,	40.00,	0.00,	0.00,	0.00,	0.00,	40.00,	'Cash',	'completed',	'2026-03-12 17:22:35',	NULL,	'paid',	'Tab 928',	40.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(54,	1,	NULL,	2,	2,	20.00,	0.00,	0.00,	0.00,	0.00,	20.00,	'Cash',	'completed',	'2026-03-12 17:23:09',	NULL,	'paid',	'Walk-in',	20.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(55,	1,	NULL,	2,	2,	45.00,	0.00,	0.00,	0.00,	0.00,	45.00,	'Cash',	'completed',	'2026-03-12 17:24:35',	NULL,	'paid',	'',	45.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(56,	1,	NULL,	2,	12,	90.00,	0.00,	0.00,	0.00,	0.00,	90.00,	'Cash',	'completed',	'2026-03-12 17:40:07',	NULL,	'paid',	'',	90.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(57,	3,	NULL,	2,	7,	90.00,	0.00,	0.00,	0.00,	0.00,	90.00,	'Cash',	'completed',	'2026-03-12 17:40:42',	NULL,	'paid',	'',	90.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(58,	3,	NULL,	2,	7,	225.00,	0.00,	0.00,	0.00,	0.00,	225.00,	'Cash',	'completed',	'2026-03-12 17:44:51',	NULL,	'paid',	'',	225.00,	0.00,	NULL,	0.00,	0.00,	NULL,	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(59,	1,	NULL,	1,	1,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Card',	'completed',	'2026-03-12 19:08:04',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-102',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(60,	1,	NULL,	1,	1,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Card',	'completed',	'2026-03-12 21:12:03',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-103',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(61,	1,	NULL,	1,	12,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Card',	'completed',	'2026-03-12 21:16:28',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-103',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(62,	1,	NULL,	1,	12,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Card',	'completed',	'2026-03-12 21:28:40',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-103',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(63,	1,	NULL,	1,	12,	150.00,	150.00,	0.00,	0.00,	0.00,	150.00,	'Cash',	'completed',	'2026-03-12 21:42:39',	NULL,	'paid',	'ONLINE: Mando Online',	150.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-102',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(64,	1,	NULL,	1,	12,	50.00,	50.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-12 21:45:20',	NULL,	'paid',	'ONLINE: Mando Online',	50.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-102',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(65,	1,	NULL,	1,	12,	50.00,	50.00,	0.00,	0.00,	0.00,	50.00,	'Cash',	'completed',	'2026-03-12 21:53:03',	NULL,	'paid',	'ONLINE: Mando Online',	50.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-102',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(66,	1,	NULL,	1,	12,	170.00,	170.00,	0.00,	0.00,	0.00,	170.00,	'Cash',	'completed',	'2026-03-12 22:07:15',	NULL,	'paid',	'ONLINE: Mando Online',	170.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-103',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(67,	1,	NULL,	1,	12,	340.00,	340.00,	0.00,	0.00,	0.00,	340.00,	'Cash',	'completed',	'2026-03-12 22:17:28',	NULL,	'paid',	'ONLINE: Mando Online',	340.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-103',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(68,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:20:59',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-104',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(69,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:22:17',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-104',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(70,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:24:30',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-105',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(71,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:37:52',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-105',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(72,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 22:40:00',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-105',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(73,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 22:41:44',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-106',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(74,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:43:17',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-105',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(75,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:44:39',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-107',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(76,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:45:30',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-106',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(77,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 22:46:41',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-108',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(78,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 22:53:20',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-110',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(79,	1,	NULL,	1,	12,	90.00,	90.00,	0.00,	0.00,	0.00,	90.00,	'Card',	'completed',	'2026-03-12 22:54:23',	NULL,	'paid',	'ONLINE: Mando Online',	90.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-110',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(80,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 22:59:44',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-110',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(81,	1,	NULL,	1,	12,	340.00,	340.00,	0.00,	0.00,	0.00,	340.00,	'Card',	'completed',	'2026-03-12 23:01:11',	NULL,	'paid',	'ONLINE: Mando Online',	340.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-110',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(82,	1,	NULL,	1,	12,	340.00,	340.00,	0.00,	0.00,	0.00,	340.00,	'Card',	'completed',	'2026-03-12 23:09:13',	NULL,	'paid',	'ONLINE: Mando Online',	340.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-111',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(83,	1,	NULL,	1,	12,	340.00,	340.00,	0.00,	0.00,	0.00,	340.00,	'Card',	'completed',	'2026-03-12 23:27:40',	NULL,	'paid',	'ONLINE: Mando Online',	340.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-112',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00),
(84,	1,	NULL,	1,	12,	100.00,	100.00,	0.00,	0.00,	0.00,	100.00,	'Card',	'completed',	'2026-03-12 23:29:15',	NULL,	'paid',	'ONLINE: Mando Online',	100.00,	0.00,	NULL,	0.00,	0.00,	'UBER-TAB-112',	'none',	0.00,	'Card',	0.00,	'Cash',	0.00);

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `settings`;
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name',	'OdeliaPOS'),
('license_tier',	'enterprise'),
('lockout_date',	''),
('receipt_footer',	'Thank you for your business!\r\nOdelia-POS'),
('receipt_header',	'Mazabuka\r\n+260972205210 | mandochishimba@duck.com\r\nTPIN: _____________________'),
('theme_accent',	'#683e0d'),
('theme_cart',	'#3e2723'),
('theme_color',	'#230d06');

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
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `variance_reason` text,
  `handover_notes` text,
  `start_verified_by` int DEFAULT NULL,
  `start_verified_at` timestamp NULL DEFAULT NULL,
  `end_verified_by` int DEFAULT NULL,
  `end_verified_at` timestamp NULL DEFAULT NULL,
  `variance` decimal(10,2) DEFAULT '0.00',
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
INSERT INTO `shifts` (`id`, `user_id`, `location_id`, `start_time`, `end_time`, `starting_cash`, `closing_cash`, `expected_cash`, `manager_closing_cash`, `status`, `variance_reason`, `handover_notes`, `start_verified_by`, `start_verified_at`, `end_verified_by`, `end_verified_at`, `variance`) VALUES
(1,	2,	6,	'2026-03-03 12:31:11',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(2,	2,	1,	'2026-03-03 12:31:32',	'2026-03-12 17:26:15',	0.00,	1075.00,	1075.00,	0.00,	'closed',	'Auth: mando',	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(3,	13,	1,	'2026-03-04 14:48:16',	'2026-03-04 14:48:32',	0.00,	0.00,	0.00,	0.00,	'closed',	'Auth: Daliso',	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(4,	13,	1,	'2026-03-04 14:51:43',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(5,	13,	4,	'2026-03-04 14:56:05',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(6,	1,	1,	'2026-03-09 21:06:56',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(7,	2,	3,	'2026-03-10 16:29:47',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(8,	14,	1,	'2026-03-11 06:11:29',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(9,	4,	1,	'2026-03-11 08:53:59',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(10,	1,	6,	'2026-03-12 14:01:58',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(11,	8,	3,	'2026-03-12 15:27:34',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(12,	2,	1,	'2026-03-12 17:39:48',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	NULL,	NULL,	NULL,	NULL,	0.00),
(13,	5,	3,	'2026-03-12 19:10:44',	'2026-03-12 19:10:50',	0.00,	0.00,	0.00,	0.00,	'closed',	'Auth: mando',	NULL,	NULL,	NULL,	NULL,	NULL,	0.00);

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
  `role` enum('admin','shopkeeper','manager','cashier','dev','chef','waiter','head_chef','bartender') NOT NULL DEFAULT 'cashier',
  `location_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `force_password_change` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `users`;
INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`, `is_active`) VALUES
(1,	'admin',	'System Admin',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'admin',	6,	'2026-02-06 19:59:35',	0,	1),
(2,	'mando',	'mando chishimba',	'$2y$10$f7zbSGxQ7gvsH5N1dEOLauAgZS3dO1cV/8Gj.UNOmK6Z2a0JxWBbu',	'dev',	6,	'2026-02-06 19:59:35',	0,	1),
(3,	'bar_manager',	'Bar Manager',	'$2y$10$1tbaXW9yGtGeOxGuuwz8VukOzF6TBUrzyOWpnj9Fhozyxk9B6BKGC',	'manager',	1,	'2026-02-06 19:59:35',	0,	1),
(4,	'main_bar',	'Main Bar',	'$2y$10$ATxyurO3vU/efr/DK1DPju0Yty15XKQjDR30ybCLmM3h.NSpqoc76',	'bartender',	1,	'2026-02-06 19:59:35',	0,	1),
(5,	'head_chef',	'Head Chef',	'$2y$10$6PSlHSpmKoiHQdYzZpEVlendSIWsL1T9vGZEWgZDeFyUlR342P6jS',	'head_chef',	3,	'2026-02-06 19:59:35',	0,	1),
(8,	'chef',	'chef',	'$2y$10$2yNHqLeM7DJlUbn33xmGR.QrPHlGqS3zMh2mbGJWbnDHV3BV4.SWO',	'chef',	3,	'2026-02-10 08:21:50',	0,	1),
(11,	'Front_Manager',	'Front Desk Manager',	'$2y$10$F9JzTIUjAP3wP9Egyk7b3uzy7XDZzDwku4t4V8YIezc15YuY8kng6',	'manager',	1,	'2026-02-14 09:31:02',	0,	1),
(12,	'lenus',	'lenus mando',	'$2y$10$noFT259DsXE0cwtLW5jN.O3qUMM85qevrZXgHqRRnuVXRbCmQqnd6',	'dev',	6,	'2026-03-01 20:22:10',	0,	1),
(13,	'Check_Def_location',	'Check_Def_location',	'$2y$10$TtGfY0dnkCT9Pj0yO6.OKu3AT.GCgvEw.4yI5moT/P4Psb/2pzlYq',	'cashier',	4,	'2026-03-04 14:45:43',	0,	0),
(14,	'main_bar2',	'Main Bar 2',	'$2y$10$wDBuInz/q9V5olhli3ZxKuzQCmUHXc7mMSbW3.3eB18fBzNBdrnxa',	'bartender',	1,	'2026-03-10 17:12:13',	0,	1);

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `vendors`;
INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `is_active`) VALUES
(1,	'Coca Cola Zambia',	'Mr. Phiri',	NULL,	1),
(2,	'Zambeef',	'Sales Rep',	NULL,	1),
(3,	'Tiger Animal Feeds',	'Mrs. Banda',	NULL,	1),
(4,	'Shoprite',	'Maybin',	'12345678',	1),
(5,	'PRAV',	'PAVIN',	'097',	1);

-- 2026-03-12 23:41:39 UTC