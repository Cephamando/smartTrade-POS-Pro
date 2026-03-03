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
(22,	'SHISHA',	NULL,	NULL,	'other');

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
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `location_id` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `inventory_logs`;

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
(72,	6,	72,	0.00),
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
(109,	6,	109,	0.00);

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
(1,	'Hunters Gold/Dry',	NULL,	50.00,	0.00,	'',	1,	1,	'item',	0),
(2,	'Savanna Dry',	NULL,	55.00,	0.00,	'',	1,	1,	'item',	0),
(3,	'Flying fish can',	NULL,	40.00,	0.00,	'',	1,	1,	'item',	0),
(4,	'Flying fish bottle',	NULL,	30.00,	0.00,	'',	1,	1,	'item',	0),
(5,	'Brutal fruit can',	NULL,	40.00,	0.00,	'',	1,	1,	'item',	0),
(6,	'Brutal fruit bottle',	NULL,	30.00,	0.00,	'',	1,	1,	'item',	0),
(7,	'Belgravia Can',	NULL,	60.00,	0.00,	'',	1,	1,	'item',	0),
(8,	'Belgravia bottle',	NULL,	60.00,	0.00,	'',	1,	1,	'item',	0),
(9,	'Castle lager',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0),
(10,	'Mosi lager',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0),
(11,	'Black label',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0),
(12,	'Castle light can',	NULL,	40.00,	0.00,	'',	2,	1,	'item',	0),
(13,	'Castle light bottle',	NULL,	30.00,	0.00,	'',	2,	1,	'item',	0),
(14,	'Heiniken Malt',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(15,	'Heiniken Silver',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(16,	'Corona',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(17,	'Budweiser',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(18,	'Windhoek lager',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(19,	'Windhoek draft',	NULL,	45.00,	0.00,	'',	2,	1,	'item',	0),
(20,	'Stella Artois',	NULL,	35.00,	0.00,	'',	2,	1,	'item',	0),
(21,	'STILL WATER',	NULL,	10.00,	0.00,	'',	3,	1,	'item',	0),
(22,	'Mango',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0),
(23,	'Orange',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0),
(24,	'Cranberry',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0),
(25,	'Red grape',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0),
(26,	'Apple',	NULL,	50.00,	0.00,	'',	4,	1,	'item',	0),
(27,	'Fruitcana',	NULL,	15.00,	0.00,	'',	4,	1,	'item',	0),
(28,	'Coke Zero',	NULL,	20.00,	0.00,	'',	5,	1,	'item',	0),
(29,	'Coke/Fanta/Sprite bottle',	NULL,	15.00,	0.00,	'',	5,	1,	'item',	0),
(30,	'Coke/Fanta/Sprite Disposable',	NULL,	20.00,	0.00,	'',	5,	1,	'item',	0),
(31,	'Jameson cocktail',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(32,	'White Russian',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(33,	'Classic Daquiri',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(34,	'Strawberry Daquiri',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(35,	'Tom Collins',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(36,	'Sex on the beach',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(37,	'Whisky Sour',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(38,	'Gin Sour',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(39,	'Martini (Vodka, Gin, Espresso)',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(40,	'Margarita',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(41,	'Cosmopolitan',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(42,	'Mojito',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(43,	'Pina Colada',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(44,	'Long Island',	NULL,	200.00,	0.00,	'',	6,	1,	'item',	0),
(45,	'Sparkling waters delight',	NULL,	150.00,	0.00,	'',	6,	1,	'item',	0),
(46,	'Virgin Mojito',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(47,	'Virgin Daquiri',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(48,	'Sunset Island',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(49,	'Virgin Captain Colada',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(50,	'Malawi shandy',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(51,	'Rocky Shandy',	NULL,	100.00,	0.00,	'',	7,	1,	'item',	0),
(52,	'Brothers mocktails',	NULL,	25.00,	0.00,	'',	7,	1,	'item',	0),
(53,	'Ginger ale',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0),
(54,	'Tonic water',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0),
(55,	'Soda Water',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0),
(56,	'Lemonade',	NULL,	25.00,	0.00,	'',	8,	1,	'item',	0),
(57,	'Glenfiddich 15',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0),
(58,	'Glenfiddich 12',	NULL,	80.00,	0.00,	'',	19,	1,	'item',	0),
(59,	'Chivas 12',	NULL,	1000.00,	0.00,	'',	9,	1,	'item',	0),
(60,	'J & B',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(61,	'Jameson blackbarrel',	NULL,	1200.00,	0.00,	'',	9,	1,	'item',	0),
(62,	'Jameson Original',	NULL,	850.00,	0.00,	'',	9,	1,	'item',	0),
(63,	'Jameson Castmate',	NULL,	1000.00,	0.00,	'',	9,	1,	'item',	0),
(64,	'Jack Daniels',	NULL,	60.00,	0.00,	'',	19,	1,	'item',	0),
(65,	'Southern Comfort',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(66,	'Gordons',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(67,	'Gordons Pink',	NULL,	500.00,	0.00,	'',	10,	1,	'item',	0),
(68,	'Beefeater',	NULL,	600.00,	0.00,	'',	10,	1,	'item',	0),
(69,	'Beefeater Pink',	NULL,	600.00,	0.00,	'',	10,	1,	'item',	0),
(70,	'Henessy',	NULL,	1400.00,	0.00,	'',	11,	1,	'item',	0),
(71,	'Remmy Martin',	NULL,	1950.00,	0.00,	'',	11,	1,	'item',	0),
(72,	'Amarula',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(73,	'Wild Africa',	NULL,	700.00,	0.00,	'',	12,	1,	'item',	0),
(74,	'Strawberry lips',	NULL,	550.00,	0.00,	'',	12,	1,	'item',	0),
(75,	'Amarula small',	NULL,	250.00,	0.00,	'',	12,	1,	'item',	0),
(76,	'Klipdrift',	NULL,	40.00,	0.00,	'',	19,	1,	'item',	0),
(77,	'Klipdrift Premium',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(78,	'KWV5',	NULL,	600.00,	0.00,	'',	13,	1,	'item',	0),
(79,	'Absolut Vodka',	NULL,	800.00,	0.00,	'',	14,	1,	'item',	0),
(80,	'Grey Goose',	NULL,	1250.00,	0.00,	'',	14,	1,	'item',	0),
(81,	'Ciroc',	NULL,	1400.00,	0.00,	'',	14,	1,	'item',	0),
(82,	'Captain Morgan dark rum',	NULL,	700.00,	0.00,	'',	15,	1,	'item',	0),
(83,	'Captain Morgan spiced Rum',	NULL,	700.00,	0.00,	'',	15,	1,	'item',	0),
(84,	'Four Cousins',	NULL,	250.00,	0.00,	'',	17,	1,	'item',	0),
(85,	'Cronier',	NULL,	450.00,	0.00,	'',	16,	1,	'item',	0),
(86,	'Nederberg',	NULL,	400.00,	0.00,	'',	16,	1,	'item',	0),
(87,	'KWV',	NULL,	400.00,	0.00,	'',	17,	1,	'item',	0),
(88,	'Fat bastard Golden Reserve',	NULL,	600.00,	0.00,	'',	16,	1,	'item',	0),
(89,	'Fat bastard Merlot',	NULL,	500.00,	0.00,	'',	16,	1,	'item',	0),
(90,	'Sunkissed',	NULL,	300.00,	0.00,	'',	17,	1,	'item',	0),
(91,	'Robertson',	NULL,	350.00,	0.00,	'',	16,	1,	'item',	0),
(92,	'Four Cousins red',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0),
(93,	'Overmeer',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0),
(94,	'Chateau Delrei',	NULL,	50.00,	0.00,	'',	18,	1,	'item',	0),
(95,	'Jameson black barrel',	NULL,	80.00,	0.00,	'',	19,	1,	'item',	0),
(96,	'Jameson whisky',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(97,	'Absolute Vodka',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(98,	'Tequila',	NULL,	55.00,	0.00,	'',	19,	1,	'item',	0),
(99,	'Jagermeister',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(100,	'Blow Job',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0),
(101,	'Jager bomb',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0),
(102,	'Hennessy',	NULL,	100.00,	0.00,	'',	19,	1,	'item',	0),
(103,	'Captain Morgan',	NULL,	50.00,	0.00,	'',	19,	1,	'item',	0),
(104,	'J.C Le Roux',	NULL,	600.00,	0.00,	'',	20,	1,	'item',	0),
(105,	'Moet Nector',	NULL,	2600.00,	0.00,	'',	21,	1,	'item',	0),
(106,	'Moet Brut',	NULL,	3000.00,	0.00,	'',	21,	1,	'item',	0),
(107,	'Verve Clicquot',	NULL,	3600.00,	0.00,	'',	21,	1,	'item',	0),
(108,	'Verve Rich',	NULL,	3800.00,	0.00,	'',	21,	1,	'item',	0),
(109,	'All Flavours Shisha',	NULL,	150.00,	0.00,	'',	22,	1,	'item',	0);

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
  `status` enum('completed','refund_requested','refunded','partially_refunded') DEFAULT 'completed',
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
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `sales`;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `settings`;
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name',	'Sparkling Waters Lodge'),
('license_tier',	'hospitality'),
('lockout_date',	''),
('receipt_footer',	'Thank you for your business!\r\nTake-POS'),
('receipt_header',	'Cha Cha Cha Road, Mazabuka\r\n+260572551356 | sparklingwaterslodges2020@gmail.com\r\nTPIN: 1004018977'),
('theme_accent',	'#f08c19'),
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
(4,	'main_bar',	'Main Bar',	'$2y$10$OGQErlyrQHeT4BYGduJTLekGCoaH.KWCFDFeeHMbEri25IhG5gB72',	'bartender',	1,	'2026-02-06 19:59:35',	0,	1),
(5,	'head_chef',	'Head Chef',	'$2y$10$6PSlHSpmKoiHQdYzZpEVlendSIWsL1T9vGZEWgZDeFyUlR342P6jS',	'head_chef',	3,	'2026-02-06 19:59:35',	0,	1),
(6,	'waiter',	'Restaurant Waiter',	'$2y$10$EGK83.eKGrOChYOMm.IWg.2IUjJNFusgrA35equMDe18uAaxY8fl2',	'waiter',	3,	'2026-02-06 19:59:35',	0,	1),
(7,	'Out_bartender',	'Out Bartender',	'$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu',	'bartender',	2,	'2026-02-06 19:59:35',	0,	0),
(8,	'chef',	'chef',	'$2y$10$2yNHqLeM7DJlUbn33xmGR.QrPHlGqS3zMh2mbGJWbnDHV3BV4.SWO',	'chef',	3,	'2026-02-10 08:21:50',	0,	1),
(10,	'daliso',	'Daliso Nindi',	'$2y$10$0Cmqbbba.ipH/7x1fh9QOuQ8z4FayAfR1TuvXhzShncyprrq27Z4.',	'admin',	6,	'2026-02-10 10:20:55',	0,	1),
(11,	'Front_Manager',	'Front Desk Manager',	'$2y$10$F9JzTIUjAP3wP9Egyk7b3uzy7XDZzDwku4t4V8YIezc15YuY8kng6',	'manager',	1,	'2026-02-14 09:31:02',	0,	1),
(12,	'lenus',	'lenus mando',	'$2y$10$noFT259DsXE0cwtLW5jN.O3qUMM85qevrZXgHqRRnuVXRbCmQqnd6',	'admin',	6,	'2026-03-01 20:22:10',	0,	1);

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

-- 2026-03-03 00:21:41 UTC
