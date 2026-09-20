-- Adminer 5.5.1 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

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


DROP TABLE IF EXISTS `grvs`;
CREATE TABLE `grvs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `purchase_order_id` int DEFAULT NULL,
  `location_id` int NOT NULL,
  `received_by` int NOT NULL,
  `total_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reference_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `zra_synced` tinyint(1) DEFAULT '0' COMMENT '1 if pushed to ZRA stock',
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `location_id` (`location_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `grvs_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `grvs_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `grvs_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `location_id` int NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_stock` (`product_id`,`location_id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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


DROP TABLE IF EXISTS `product_recipes`;
CREATE TABLE `product_recipes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_product_id` int NOT NULL COMMENT 'The sellable cocktail/meal',
  `ingredient_product_id` int NOT NULL COMMENT 'The raw bottle/ingredient',
  `quantity` decimal(10,4) NOT NULL COMMENT 'Amount deducted per sale (e.g., 0.05 for 50ml)',
  PRIMARY KEY (`id`),
  KEY `parent_idx` (`parent_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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
  `zra_synced` tinyint(1) DEFAULT '0' COMMENT '1 if synced to ZRA',
  `zra_item_cd` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`, `type`, `is_open_price`, `tax_class`, `unspsc_code`, `zra_synced`, `zra_item_cd`) VALUES
(1,	'Hunters Gold/Dry',	NULL,	50.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000001'),
(2,	'Savanna Dry',	NULL,	55.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000002'),
(3,	'Flying fish can',	NULL,	40.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000003'),
(4,	'Flying fish bottle',	NULL,	30.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000004'),
(5,	'Brutal fruit can',	NULL,	40.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000005'),
(6,	'Brutal fruit bottle',	NULL,	30.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000006'),
(7,	'Belgravia Can',	NULL,	60.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000007'),
(8,	'Belgravia bottle',	NULL,	60.00,	0.00,	'EA',	1,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000008'),
(9,	'Castle lager',	NULL,	30.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000009'),
(10,	'Mosi lager',	NULL,	30.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000010'),
(11,	'Black label',	NULL,	30.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000011'),
(12,	'Castle light can',	NULL,	40.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000012'),
(13,	'Castle light bottle',	NULL,	30.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000013'),
(14,	'Heiniken Malt',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000014'),
(15,	'Heiniken Silver',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000015'),
(16,	'Corona',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000016'),
(17,	'Budweiser',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000017'),
(18,	'Windhoek lager',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000018'),
(19,	'Windhoek draft',	NULL,	45.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000019'),
(20,	'Stella Artois',	NULL,	35.00,	0.00,	'EA',	2,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000020'),
(21,	'STILL WATER',	NULL,	10.00,	0.00,	'EA',	3,	1,	'item',	0,	'A',	'50202301',	1,	'STP0000021'),
(22,	'Mango',	NULL,	50.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000022'),
(23,	'Orange',	NULL,	50.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000023'),
(24,	'Cranberry',	NULL,	50.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000024'),
(25,	'Red grape',	NULL,	50.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000025'),
(26,	'Apple',	NULL,	50.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000026'),
(27,	'Fruitcana',	NULL,	15.00,	0.00,	'EA',	4,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000027'),
(28,	'Coke Zero',	NULL,	20.00,	10.00,	'EA',	5,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000028'),
(29,	'Coke/Fanta/Sprite bottle',	NULL,	15.00,	0.00,	'EA',	5,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000029'),
(30,	'Coke/Fanta/Sprite Disposable',	NULL,	20.00,	0.00,	'EA',	5,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000030'),
(31,	'Jameson cocktail',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000031'),
(32,	'White Russian',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000032'),
(33,	'Classic Daquiri',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000033'),
(34,	'Strawberry Daquiri',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000034'),
(35,	'Tom Collins',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000035'),
(36,	'Sex on the beach',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000036'),
(37,	'Whisky Sour',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000037'),
(38,	'Gin Sour',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000038'),
(39,	'Martini (Vodka, Gin, Espresso)',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000039'),
(40,	'Margarita',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000040'),
(41,	'Cosmopolitan',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000041'),
(42,	'Mojito',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000042'),
(43,	'Pina Colada',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000043'),
(44,	'Long Island',	NULL,	200.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000044'),
(45,	'Sparkling waters delight',	NULL,	150.00,	0.00,	'EA',	6,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000045'),
(46,	'Virgin Mojito',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000046'),
(47,	'Virgin Daquiri',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000047'),
(48,	'Sunset Island',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000048'),
(49,	'Virgin Captain Colada',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000049'),
(50,	'Malawi shandy',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000050'),
(51,	'Rocky Shandy',	NULL,	100.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000051'),
(52,	'Brothers mocktails',	NULL,	25.00,	0.00,	'EA',	7,	1,	'item',	0,	'A',	'50202308',	1,	'STP0000052'),
(53,	'Ginger ale',	NULL,	25.00,	0.00,	'EA',	8,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000053'),
(54,	'Tonic water',	NULL,	25.00,	0.00,	'EA',	8,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000054'),
(55,	'Soda Water',	NULL,	25.00,	0.00,	'EA',	8,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000055'),
(56,	'Lemonade',	NULL,	25.00,	0.00,	'EA',	8,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000056'),
(57,	'Glenfiddich 15',	NULL,	100.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000057'),
(58,	'Glenfiddich 12',	NULL,	80.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000058'),
(59,	'Chivas 12',	NULL,	1000.00,	0.00,	'EA',	9,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000059'),
(60,	'J & B',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000060'),
(61,	'Jameson blackbarrel',	NULL,	1200.00,	0.00,	'EA',	9,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000061'),
(62,	'Jameson Original',	NULL,	850.00,	0.00,	'EA',	9,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000062'),
(63,	'Jameson Castmate',	NULL,	1000.00,	0.00,	'EA',	9,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000063'),
(64,	'Jack Daniels',	NULL,	60.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000064'),
(65,	'Southern Comfort',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000065'),
(66,	'Gordons',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000066'),
(67,	'Gordons Pink',	NULL,	500.00,	0.00,	'EA',	10,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000067'),
(68,	'Beefeater',	NULL,	600.00,	0.00,	'EA',	10,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000068'),
(69,	'Beefeater Pink',	NULL,	600.00,	0.00,	'EA',	10,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000069'),
(70,	'Henessy',	NULL,	1400.00,	0.00,	'EA',	11,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000070'),
(71,	'Remmy Martin',	NULL,	1950.00,	0.00,	'EA',	11,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000071'),
(72,	'Amarula',	NULL,	50.00,	1000.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000072'),
(73,	'Wild Africa',	NULL,	700.00,	0.00,	'EA',	12,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000073'),
(74,	'Strawberry lips',	NULL,	550.00,	0.00,	'EA',	12,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000074'),
(75,	'Amarula small',	NULL,	250.00,	0.00,	'EA',	12,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000075'),
(76,	'Klipdrift',	NULL,	40.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000076'),
(77,	'Klipdrift Premium',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000077'),
(78,	'KWV5',	NULL,	600.00,	0.00,	'EA',	13,	1,	'item',	0,	'A',	'50202203',	1,	'STP0000078'),
(79,	'Absolut Vodka',	NULL,	800.00,	0.00,	'EA',	14,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000079'),
(80,	'Grey Goose',	NULL,	1250.00,	0.00,	'EA',	14,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000080'),
(81,	'Ciroc',	NULL,	1400.00,	0.00,	'EA',	14,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000081'),
(82,	'Captain Morgan dark rum',	NULL,	700.00,	0.00,	'EA',	15,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000082'),
(83,	'Captain Morgan spiced Rum',	NULL,	700.00,	0.00,	'EA',	15,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000083'),
(84,	'Four Cousins',	NULL,	250.00,	0.00,	'EA',	17,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000084'),
(85,	'Cronier',	NULL,	450.00,	0.00,	'EA',	16,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000085'),
(86,	'Nederberg',	NULL,	400.00,	0.00,	'EA',	16,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000086'),
(87,	'KWV',	NULL,	400.00,	0.00,	'EA',	17,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000087'),
(88,	'Fat bastard Golden Reserve',	NULL,	600.00,	0.00,	'EA',	16,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000088'),
(89,	'Fat bastard Merlot',	NULL,	500.00,	0.00,	'EA',	16,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000089'),
(90,	'Sunkissed',	NULL,	300.00,	0.00,	'EA',	17,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000090'),
(91,	'Robertson',	NULL,	350.00,	0.00,	'EA',	16,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000091'),
(92,	'Four Cousins red',	NULL,	50.00,	0.00,	'EA',	18,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000092'),
(93,	'Overmeer',	NULL,	50.00,	0.00,	'EA',	18,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000093'),
(94,	'Chateau Delrei',	NULL,	50.00,	0.00,	'EA',	18,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000094'),
(95,	'Jameson black barrel',	NULL,	80.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000095'),
(96,	'Jameson whisky',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000096'),
(97,	'Absolute Vodka',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000097'),
(98,	'Tequila',	NULL,	55.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000098'),
(99,	'Jagermeister',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000099'),
(100,	'Blow Job',	NULL,	100.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000100'),
(101,	'Jager bomb',	NULL,	100.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000101'),
(102,	'Hennessy',	NULL,	100.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000102'),
(103,	'Captain Morgan',	NULL,	50.00,	0.00,	'EA',	19,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000103'),
(104,	'J.C Le Roux',	NULL,	600.00,	0.00,	'EA',	20,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000104'),
(105,	'Moet Nector',	NULL,	2600.00,	0.00,	'EA',	21,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000105'),
(106,	'Moet Brut',	NULL,	3000.00,	0.00,	'EA',	21,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000106'),
(107,	'Verve Clicquot',	NULL,	3600.00,	0.00,	'EA',	21,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000107'),
(108,	'Verve Rich',	NULL,	3800.00,	0.00,	'EA',	21,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000108'),
(109,	'All Flavours Shisha',	NULL,	150.00,	0.00,	'EA',	22,	1,	'item',	0,	'A',	'50202201',	1,	'STP0000109'),
(110,	'CHICKEN WRAP',	'CWR001',	170.00,	100.00,	'EA',	24,	1,	'item',	0,	'A',	'50112011',	1,	'CWR001'),
(111,	'BEFF BURGER',	'BBGR001',	65.00,	30.00,	'EA',	24,	1,	'item',	0,	'A',	'50111516',	1,	'BBGR001'),
(112,	'POTATO SALADS',	'PTT001',	45.00,	0.00,	'EA',	23,	1,	'item',	0,	'A',	'50193201',	1,	'PTT001'),
(113,	'POTATOES',	'PT001',	0.00,	150.00,	'EA',	23,	1,	'item',	0,	'A',	'50405700',	1,	'PT001'),
(114,	'CHIKEN',	'CKNR001',	0.00,	120.00,	'EA',	23,	1,	'item',	0,	'A',	'50112010',	1,	'CKNR001'),
(115,	'COOKING OIL',	'OIL001',	0.00,	200.00,	'Ltr',	23,	1,	'item',	0,	'A',	'50151520',	1,	'OIL001'),
(116,	'Tip / Service charge',	'ZRA-TIP',	0.00,	0.00,	'EA',	NULL,	0,	'service',	1,	'A',	'50202201',	1,	'ZRA-TIP'),
(117,	'Mojo',	NULL,	7.00,	4.00,	'330ml',	3,	1,	'item',	0,	'A',	'50202306',	1,	'STP0000117'),
(118,	'PORK',	'PORK-001',	80.00,	50.00,	'KG',	23,	1,	'item',	0,	'A',	'50111514',	0,	'PORK-001');

DROP TABLE IF EXISTS `purchase_inquiries`;
CREATE TABLE `purchase_inquiries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int DEFAULT NULL,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `notes` text,
  `needed_by` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pi_status` (`status`),
  KEY `idx_pi_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `purchase_inquiry_items`;
CREATE TABLE `purchase_inquiry_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inquiry_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pii_inquiry` (`inquiry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `received_qty` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_poi_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inquiry_id` int DEFAULT NULL,
  `vendor_id` int NOT NULL,
  `location_id` int NOT NULL,
  `user_id` int NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` text,
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `receiving_dock_items`;
CREATE TABLE `receiving_dock_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dock_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `is_unregistered` tinyint(1) NOT NULL DEFAULT '0',
  `sku` varchar(80) DEFAULT NULL,
  `unspsc_code` varchar(16) DEFAULT NULL,
  `tax_class` varchar(8) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `unit` varchar(50) NOT NULL DEFAULT 'Units',
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `line_key` varchar(190) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dock_items` (`dock_id`),
  CONSTRAINT `fk_dock_items` FOREIGN KEY (`dock_id`) REFERENCES `receiving_docks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `receiving_docks`;
CREATE TABLE `receiving_docks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tracking_code` varchar(64) NOT NULL,
  `source_name` varchar(150) NOT NULL DEFAULT '',
  `intended_location_id` int DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `status` enum('docked','received','cancelled') NOT NULL DEFAULT 'docked',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `received_at` datetime DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `grv_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_receiving_track` (`tracking_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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


DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE `restaurant_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int NOT NULL DEFAULT '0',
  `zone_name` varchar(50) NOT NULL DEFAULT 'Main Dining',
  `table_name` varchar(50) NOT NULL,
  `capacity` int NOT NULL DEFAULT '4',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `refund_sale_item_id` int DEFAULT NULL,
  `is_refund` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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
  `zra_rcpt_no` int DEFAULT NULL,
  `zra_intrl_data` varchar(255) DEFAULT NULL,
  `zra_rcpt_sign` varchar(255) DEFAULT NULL,
  `zra_vsdc_rcpt_pbct_date` varchar(20) DEFAULT NULL,
  `zra_sdc_id` varchar(50) DEFAULT NULL,
  `zra_mrc_no` varchar(50) DEFAULT NULL,
  `zra_qr_code_url` text,
  `zra_status` varchar(20) DEFAULT 'pending',
  `zra_cis_invc_no` varchar(50) DEFAULT NULL,
  `zra_org_invc_no` int DEFAULT NULL,
  `zra_last_error` text,
  `zra_rfd_rsn_cd` varchar(2) DEFAULT NULL,
  `split_payments` text,
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name',	'smartTradePOS'),
('license_tier',	'enterprise'),
('lockout_date',	''),
('receipt_footer',	'Thank you for your business!\r\nOdelia-POS'),
('receipt_header',	'Mazabuka\r\n+260972205210 | mando@odeliazm.com\r\nTPIN: _____________________'),
('theme_accent',	'#683e0d'),
('theme_cart',	'#3e2723'),
('theme_color',	'#230d06'),
('vsdc_bhfNm',	'Mazabuka-'),
('vsdc_default_unspsc',	'50202201'),
('vsdc_device_installed',	'1'),
('vsdc_last_class_dt',	'20231215000000'),
('vsdc_last_code_dt',	'20260913172454'),
('vsdc_last_invc_no',	'0'),
('vsdc_last_sale_no',	'0'),
('vsdc_last_sar_no',	'0'),
('vsdc_mrcNo',	'WIS00004268  '),
('vsdc_sdcId',	'SDC0060001122'),
('vsdc_taxprNm',	'MANDO NGOMA CHISHIMBA');

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


DROP TABLE IF EXISTS `taxes`;
CREATE TABLE `taxes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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

INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `is_active`) VALUES
(1,	'Coca Cola Zambia',	'Mr. Phiri',	NULL,	1),
(2,	'Zambeef',	'Sales Rep',	NULL,	1),
(3,	'Tiger Animal Feeds',	'Mrs. Banda',	NULL,	1),
(4,	'Shoprite',	'Maybin',	'12345678',	1),
(5,	'PRAV',	'PAVIN',	'097',	1),
(6,	'V-SIAS Inspection',	'Site inspection',	'',	1);

DROP TABLE IF EXISTS `vsias_sync_log`;
CREATE TABLE `vsias_sync_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tracking_code` varchar(64) NOT NULL,
  `line_key` varchar(190) NOT NULL,
  `product_id` int DEFAULT NULL,
  `zra_status` varchar(32) DEFAULT NULL,
  `zra_message` text,
  `booked` tinyint(1) NOT NULL DEFAULT '0',
  `grv_id` int DEFAULT NULL,
  `transfer_id` int DEFAULT NULL,
  `dock_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vsias_track_line` (`tracking_code`,`line_key`),
  KEY `idx_vsias_track` (`tracking_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `zra_item_classes`;
CREATE TABLE `zra_item_classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_cls_cd` varchar(16) NOT NULL,
  `item_cls_nm` varchar(500) NOT NULL,
  `item_cls_lvl` int DEFAULT '0',
  `item_cls_path` varchar(700) DEFAULT NULL,
  `search_text` text,
  `tax_ty_cd` varchar(10) DEFAULT NULL,
  `use_yn` char(1) DEFAULT 'Y',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cls` (`item_cls_cd`),
  KEY `idx_cls_lvl` (`item_cls_lvl`),
  FULLTEXT KEY `ft_unspsc_search` (`item_cls_nm`,`search_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `zra_standard_codes`;
CREATE TABLE `zra_standard_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code_class` varchar(10) NOT NULL,
  `code_class_name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `code_name` varchar(100) NOT NULL,
  `user_defined_name` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code_class`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `zra_sync_log`;
CREATE TABLE `zra_sync_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(20) NOT NULL,
  `entity_id` int NOT NULL,
  `result_cd` varchar(10) DEFAULT NULL,
  `result_msg` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- 2026-09-20 17:07:44 UTC
