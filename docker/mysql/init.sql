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
(1, 'Beverages',  NULL, 'drink'),
(2, 'Meals',  NULL, 'food'),
(3, 'Ingredients',  NULL, 'ingredients'),
(4, 'Snacks', NULL, 'food'),
(5, 'Cleaning Material',  NULL, 'other');

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

TRUNCATE `locations`;
INSERT INTO `locations` (`id`, `name`, `type`, `can_sell`, `can_receive_from_vendor`, `address`, `phone`) VALUES
(1, 'main bar', 'store',  1,  0,  '', '555-0000'),
(2, 'outside bar',  'store',  1,  0,  '', '555-0000'),
(3, 'Kitchen',  'warehouse',  1,  0,  '', '555-0000'),
(4, 'Main Warehouse', 'warehouse',  1,  0,  '', '555-0000'),
(5, 'mini storeroom', 'store',  1,  0,  '', '555-0000'),
(6, 'HQ', 'warehouse',  1,  0,  '', '555-0000');

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`, `type`, `is_open_price`) VALUES
(15,  'Beef Burger',  NULL, 70.00,  50.00,  'Kg', 2,  1,  'item', 0),
(16,  'T-Bone Steak', 'Tbone001', 60.00,  40.00,  'kg', 2,  1,  'item', 0),
(17,  'wifi', NULL, 0.00, 0.00, 'unit', NULL, 1,  'service',  0),
(18,  'Coca Cola',  'CC001',  15.00,  10.00,  'ml', 1,  1,  'item', 0),
(19,  'water bottle', NULL, 0.00, 0.00, 'unit', NULL, 1,  'service',  0),
(20,  'JAMESON IPA 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(21,  'HEINEKEN SILVER NRB 340mls', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(22,  'COSMO',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(23,  'VICE MOJITO',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(24,  'CUVEE ROSE', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(25,  'LAUTUS WINE',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(26,  'CALCIUM 5 L',  NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(27,  'CABANOSIS',  NULL, 35.00,  0.00, 'unit', 4,  1,  'item', 0),
(28,  '1430 CIDER', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(29,  'RASPBERRY GRENACHE', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(30,  'CHATEAU CAN',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(31,  'HENNESSY VERY SPECIAL',  NULL, 90.00,  0.00, 'unit', 1,  1,  'item', 0),
(32,  'GINOLOGIST TREE SET',  NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(33,  'ROBERTSON PINOTAGE', NULL, 240.00, 0.00, 'unit', 1,  1,  'item', 0),
(34,  'KWV PINOTAGE', NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(35,  'ROBERTSON CHAPHEL RED',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(36,  'VAN LOVEREN PINOTAGE', NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(37,  'GOLDEN KAAN',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(38,  'PEARLY BAY', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(39,  'FAT BASTARD CHARDONNAY 750 ML',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(40,  'PROTEA ROSE',  NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(41,  'TALL HORSE SHIRAZ',  NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(42,  'NEDERBURG SHIRAZ', NULL, 520.00, 0.00, 'unit', 1,  1,  'item', 0),
(43,  'JP CHENET',  NULL, 600.00, 0.00, 'unit', 1,  1,  'item', 0),
(44,  'VAN LOVREN BRUT 750 ML', NULL, 700.00, 0.00, 'unit', 1,  1,  'item', 0),
(45,  'ALITA CUVEE BRUT 750 ML',  NULL, 750.00, 0.00, 'unit', 1,  1,  'item', 0),
(46,  'FAIRVIEW SWEET RED SOFT',  NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(47,  'MOJITO', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(48,  'BROTHERS STRAWBERRY',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(49,  'GRANULAR CHLORIDE 2 KGs',  NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(50,  'BUTTLERS TRIPPLE SEC', NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(51,  'FOUR COUSINS SWEET RED 3L',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(52,  'VICE PINACOLANDA', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(53,  'HENNESSY VSOP',  NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(54,  'PROTEA MERLOT',  NULL, 320.00, 0.00, 'unit', 1,  1,  'item', 0),
(55,  'PAUL CLUVER',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(56,  'CRONIER SWEET RED',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(57,  'JC LE ROUX LA CHANSON 750',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(58,  'KWV CHARDONNAY', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(59,  'PORCUPINE RIDGE 750mls', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(60,  'TALL HORSE MERLOT',  NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(61,  'ZONNEBLOEM', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(62,  'GLEN CARLOU MERLOT 750mls',  NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(63,  'FRANSEHHOEK MERLOT', NULL, 700.00, 0.00, 'unit', 1,  1,  'item', 0),
(64,  '1698 PINOTAGE',  NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(65,  'DE KRANS', NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(66,  'FAIRVIEW SWEET RED', NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(67,  'WAR WICK', NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(68,  'CEDERBERG',  NULL, 900.00, 0.00, 'unit', 1,  1,  'item', 0),
(69,  'TRANSCHOEK CELLAR',  NULL, 900.00, 0.00, 'unit', 1,  1,  'item', 0),
(70,  'REAM OF PAPER',  NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(71,  'MINERAL VATRA WATER 500ML',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(72,  'STRAWBERRY LIPS',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(73,  'DISARONNO',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(74,  'CELLAR CASK RED 5L', NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(75,  'CHAMDOR GRAPE',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(76,  'FOUR COUSINS WHITE 750mls',  NULL, 320.00, 0.00, 'unit', 1,  1,  'item', 0),
(77,  'DROSTDY DRY RED 750mls', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(78,  'KWV CHENIN BLANC', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(79,  'WOLFTRAP ROSE',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(80,  'BONNE ESPIRANCE WHITE',  NULL, 430.00, 0.00, 'unit', 1,  1,  'item', 0),
(81,  'DIEMERSDAL WINE',  NULL, 480.00, 0.00, 'unit', 1,  1,  'item', 0),
(82,  'CHATEU_LIBERTAS 750mls', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(83,  'TALL HORSE CAB SAV', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(84,  'SPIRIT OF SALT 2.5 L', NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(85,  'GINGER ALE', NULL, 20.00,  0.00, 'unit', 1,  1,  'item', 0),
(86,  'GINGER ALE 330ML', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(87,  'NEDERBURG PINOTAGE 750mls',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(88,  'SWARTLAND',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(89,  'JP CHENET PINK 750 ML',  NULL, 600.00, 0.00, 'unit', 1,  1,  'item', 0),
(90,  'MOET CHAMPAGNE 750mls',  NULL, 2000.00,  0.00, 'unit', 1,  1,  'item', 0),
(91,  'SAVANNAH LIME CORDIAL 2 LTRS', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(92,  'COSMO BROTHERS_',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(93,  'TEE-POL 5L', NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(94,  'RED BULL', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(95,  'SHERIDAN SHOOTER', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(96,  'KWV BRANDY 10 YRS 750 ML', NULL, 65.00,  0.00, 'unit', 1,  1,  'item', 0),
(97,  'JAMESON STOUT 750 ML', NULL, 75.00,  0.00, 'unit', 1,  1,  'item', 0),
(98,  'DROSTDY DRY WHITE 750mls', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(99,  'CRONIER MERLOT 750mls',  NULL, 600.00, 0.00, 'unit', 1,  1,  'item', 0),
(100, 'BLACK LABEL WHISKY', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(101, 'THICK BLEACH 750MLS',  NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(102, 'VICE LEMON DROP',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(103, 'SUN KISSED SWEET RED 750mls',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(104, 'BELGRAVIA CAN',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(105, 'PINA COLADA COCKTAIL', NULL, 170.00, 0.00, 'unit', 1,  1,  'item', 0),
(106, 'BROTHERS PIN COLADA',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(107, 'FLYING FISH BOTTLE', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(108, 'ROBERTSON WINE 5L',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(109, 'JP CHENET ICE WHITE',  NULL, 430.00, 0.00, 'unit', 1,  1,  'item', 0),
(110, 'FURNITURE SPRAY 750MLS', NULL, 0.00, 0.00, 'unit', 5,  1,  'item', 0),
(111, 'BELVEDERE VODKA',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(112, 'FAT BASTARD GOLDEN 750mls',  NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(113, 'NANO PLUS 750MLS', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(114, 'GINGER ALE SCHWEPPES 330mls',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(115, 'GENTLEMAN JACK', NULL, 120.00, 0.00, 'unit', 1,  1,  'item', 0),
(116, 'TONIC WATER BROTHERS 200 ML',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(117, 'HUNTERS DRY',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(118, 'FOUR COUSINS SWEET RED 750mls',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(119, 'LYRIC NEDERBURG WHITE',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(120, 'HUNTERS GOLD CAN', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(121, 'CRONIER SHIRAZ 750mls',  NULL, 600.00, 0.00, 'unit', 1,  1,  'item', 0),
(122, 'SAVANNA LIME 500mls',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(123, 'PRAVDA VODKA', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(124, 'SUN KISSED SWEET WHITE 750mls',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(125, 'SODA WATER BROTHERS 200 ML', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(126, 'INVERROCHE AMBER', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(127, 'KLIPDRIFT PRIMIUM 750 ML', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(128, 'SAVANNA DRY',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(129, 'AMARULA ETHIOPIAN',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(130, 'BUMBU GOLD', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(131, 'VATRA 500MLS', NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(132, 'GINGER ALE BROTHERS 200 ML', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(133, 'BUTLERS PEPPER MINT',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(134, 'FAT BASTARD CABERNET 750mls',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(135, 'CHAPTAIN MORGAN SPICE GOLD', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(136, 'MINERAL VATRA WATER 750ML',  NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(137, 'BLUE CURACAO', NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(138, 'COKE ZERO PET 500mls', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(139, 'DONJULIO', NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(140, 'ROYAL FLUSH GIN',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(141, 'SINGLETON WHISKEY',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(142, 'ROYAL RHINO 750MLS', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(143, 'MOSI LAGER RGB_750mls',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(144, 'INVERROCHE VERDANY', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(145, 'HENNESSEY X.O',  NULL, 450.00, 0.00, 'unit', 1,  1,  'item', 0),
(146, 'BOTTEGA GIN',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(147, 'TULLAMORE DEW 1L', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(148, 'GRANTS APERITIF SPIRIT', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(149, 'RED HEART',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(150, 'GRANTS 12yrs', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(151, 'ABSOLUTE VODKA RASBERRY',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(152, 'JACK DANIELS 750 ML',  NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(153, 'PONCHOS PREM TEQUILIO',  NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(154, 'CAPE SAINT BLAZE', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(155, 'CRUZ_VODKA MANHATTAN', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(156, 'MAKERS MARK',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(157, 'CRUZ_VODKA VINTAGE BLACK', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(158, 'BULLDOG GIN 750 ML', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(159, 'DRUNKEN HORSE',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(160, 'SKILPADTEPEL', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(161, 'PATRON TEQUILA', NULL, 250.00, 0.00, 'unit', 1,  1,  'item', 0),
(162, 'REMMY MARTINI XO', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(163, 'OUDE MASTER VSO',  NULL, 630.00, 0.00, 'unit', 1,  1,  'item', 0),
(164, 'SAVANNA LIME 2L',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(165, 'CACTUS JACK TEQUILA',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(166, 'JAM JAR RED 750 ML', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(167, 'BOTEGA GOLD 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(168, 'CAPTAIN MORGAN DARK',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(169, 'BOYYEGA GIN BACUR 1L', NULL, 90.00,  0.00, 'unit', 1,  1,  'item', 0),
(170, 'TEQUILA 1800', NULL, 110.00, 0.00, 'unit', 1,  1,  'item', 0),
(171, 'JAM JAR WHITE 750mls', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(172, 'NSHIMA PORTION', NULL, 30.00,  0.00, 'unit', 2,  1,  'item', 0),
(173, 'TULLAMORE DEW 750 ML', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(174, 'KWV BRANDY 5 YRS 750 ML',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(175, 'WINDHOEK LAGER', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(176, 'JAM JAR SWEET SHIRAZ 750 ML',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(177, 'GINOLOGIST GIN 750 ML',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(178, 'ROBERTSON NATURAL SWEET RED 750mls', NULL, 460.00, 0.00, 'unit', 1,  1,  'item', 0),
(179, 'BLACK LABEL LAGER RGB 750mls', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(180, 'LEMONADE 200 ML',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(181, 'CIROC SNAPFROST',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(182, 'REMY MARTIN V.O.S.P',  NULL, 130.00, 0.00, 'unit', 1,  1,  'item', 0),
(183, 'KWV BRANDY 3 YRS 750 ML',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(184, 'CASTLE LAGER RGB 750mls',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(185, 'CINZANO RED (ROSO)', NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(186, 'BAILEYS 750 ML', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(187, 'SKY VODKA',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(188, 'CHIVAS LEGAL 15YRS', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(189, 'REMMY MARTINI VSOP', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(190, 'TEQUILA GOLD', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(191, 'JACK DANIELS HONEY 750mls',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(192, 'BELLS XTRA SPECIAL', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(193, 'JOHN WALKER 18 YRS', NULL, 180.00, 0.00, 'unit', 1,  1,  'item', 0),
(194, 'BLACK COFFEE', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(195, 'BUDWEISER',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(196, 'JAM TARTS',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(197, 'KAHLUA COFFEE',  NULL, 75.00,  0.00, 'unit', 1,  1,  'item', 0),
(198, 'SPRITE_RGB_300mls',  NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(199, 'BRUTAL FRUIT BOTTLE',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(200, 'CHIVAS 12 YEARS 750 ML', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(201, 'FRUITICANA JUICE', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(202, 'WINDHOEK DRAUGHT', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(203, 'JAMESON BLACK BARREL 750 ML',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(204, 'JAGERMASTER 1L', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(205, 'CIROC APPLE',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(206, 'BUMBU BLACK XO', NULL, 90.00,  0.00, 'unit', 1,  1,  'item', 0),
(207, 'TEQUILA SILVER 750 ML',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(208, 'JACKSON BROWN 750mls', NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(209, 'VICEROY 5yrs', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(210, 'CIROC PINEAPPLE',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(211, 'JOHNNIE WALKER B/LABEL 750ML', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(212, 'FANTA CANNED', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(213, 'MINUTE MAID',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(214, 'JOHNNIE WALKER R/LABEL 750ML', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(215, 'HUNTERS GOLD', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(216, 'BUMBU SPIRIT APARITIF',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(217, 'JOHNNIE WALKER G/LABLE 750ML', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(218, 'ABSOLUTE VODKA', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(219, 'AQUA SAVANA 750ML',  NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(220, 'HEINIKEN NRB 340mls',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(221, 'SPRITE PET 500mls',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(222, 'COKE CANNED',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(223, 'FRUTICANA',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(224, 'BARCADI RUM',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(225, 'AMERICANO COFFEE', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(226, 'STELLA ARTOIS',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(227, 'CAPPUCCINO_COFFEE',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(228, 'COFFEE LA TE', NULL, 65.00,  0.00, 'unit', 1,  1,  'item', 0),
(229, 'GLENFIDICH 15YRS', NULL, 140.00, 0.00, 'unit', 1,  1,  'item', 0),
(230, 'CASTLE LAGER RGB 375mls',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(231, 'BEEFEATER WHITE',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(232, 'FANTA_ORANGE_RGB_300mls',  NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(233, 'HENDRICKS',  NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(234, 'AMARULA 750 ML', NULL, 100.00, 80.00,  'unit', 1,  1,  'item', 0),
(235, 'FANTA ORANGE PET 500mls',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(236, 'GLENLIVET 12 YEARS 750 ML',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(237, 'FLYING FISH CAN 500mls', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(238, 'GLENLIVET F/RESERVE',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(239, 'JAMESON IRISH 750 ML', NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(240, 'J&B WHISKY 750 ML',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(241, 'NEDERBURG BARRON 750mls',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(242, 'TANQUARAY GIN 750mls', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(243, 'CASTLE LITE NRB 340mls', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(244, 'MONKEY SHOULDER 750 ML', NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(245, 'COFFEE CUPS',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(246, 'BRUTAL FRUIT CAN 500mls',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(247, 'VAT 69', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(248, 'GLENFIDDICH 18YRS',  NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(249, 'BOMBAY SAPHIRE 750 ML',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(250, 'COKE RGB 300mls',  NULL, 15.00,  0.00, 'unit', 1,  1,  'item', 0),
(251, 'KLIPDRIFT',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(252, 'CELLAR CASK NATURAL JUICY 750mls', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(253, 'BEEFEATER PINK', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(254, 'BALLANTINES',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(255, 'CAPTAIN MORGAN GOLD',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(256, 'COKE PET 500mls',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(257, 'BELLS WHISKEY',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(258, 'GLENFIDICH 12YRS', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(259, 'BLACK LABEL CAN 500MLS', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(260, 'CORONA EXTRA', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(261, 'ROBERTSON_NATURAL_DRY_RED_3L', NULL, 70.00,  0.00, 'unit', 1,  1,  'item', 0),
(262, 'CASTLE LITE CAN 500mls', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(263, 'GRANTS TRIPPLE WOOD 750 ML', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(264, 'SOUTHERN COMFORT 750 ML',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(265, 'GRANTS WHISKY ORDINARY 750 ML',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(266, 'AQUA SAVANA 500ML',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(267, 'BLACK LABEL LAGER RGB 375mls', NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(268, 'MOSI LAGER RGB 375mls',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(269, 'GORDONS DRY GIN',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(270, 'ABSOLUTE BLUE VODKA',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(271, 'ABSOLUTE VODKA WATERMELON',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(272, 'ACTIVE SPECIAL', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(273, 'AMARULA GIN',  NULL, 0.00, 60.00,  'unit', 1,  1,  'item', 0),
(274, 'BELGRAVIA 10', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(275, 'BELGRAVIA 8 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(276, 'BELGRAVIA BLACK BERRY',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(277, 'BELGRAVIA DRY',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(278, 'BELGRAVIA STRAWBERRY', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(279, 'BELVEDERE',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(280, 'BLANCO 1800 SILVER', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(281, 'BOTEGA PINK 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(282, 'CINZANO WHITE (BIANCO)', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(283, 'COCO', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(284, 'CRONIER 3LIT', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(285, 'DROSTOF RED WINE 5 LITERS',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(286, 'FOUR COUSINS RED 5LITERS', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(287, 'GENOLOGIST BOTTLE',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(288, 'GLENLIVET 18 YEARS 750 ML',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(289, 'GRILL DE 20th',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(290, 'HIGHBURY WHISKY',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(291, 'J&B WHISKY', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(292, 'JACK DANIELS GENTLEMAN JACK',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(293, 'JAGERMASTER 750mls', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(294, 'JAGERMESTER MANIFESTO',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(295, 'JOHNNIE WALKER D/BLACK 750ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(296, 'MALIBU', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(297, 'MARBORO BLUE', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(298, 'MARBORO GOLD', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(299, 'MARBORO RED',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(300, 'OLD BROWN',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(301, 'OVERMEER CASK RED 5 LITERS', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(302, 'OVERMEER WHITE', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(303, 'RED SQUARE', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(304, 'REMY MARTIN X.O',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(305, 'REPOSADO 1800 GOLD', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(306, 'ROBERTO VODKA',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(307, 'SALMON ROSETS',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(308, 'SMIRNOF 1818 VODKA', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(309, 'STRETTON DRY LONDON 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(310, 'STRETTON PINK GIN 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(311, 'TEQUILA CHOCOLATE',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(312, 'THREE SPECIAL',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(313, 'WILD AFRICA 750 ML', NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(314, 'WILD AFRICA CHOCOLATE',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(315, 'CINZANO',  NULL, 0.00, 0.00, 'unit', 1,  1,  'item', 0),
(316, 'BUTTLERS EXPRESSO',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(317, 'BUTTLERS STRAWBERRY',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(318, 'COKE BOTTLE [STAFF P]',  NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(319, 'LIME', NULL, 10.00,  0.00, 'unit', 1,  1,  'item', 0),
(320, 'CRISPS', NULL, 15.00,  0.00, 'unit', 4,  1,  'item', 0),
(321, 'PASSION FRUIT 750ML',  NULL, 20.00,  0.00, 'unit', 1,  1,  'item', 0),
(322, 'SMALL TEA',  NULL, 20.00,  0.00, 'unit', 1,  1,  'item', 0),
(323, 'CREME SODA', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(324, 'GINGER BEER',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(325, 'SCHWEPPES LEMONADE', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(326, 'SPRITE CAN', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(327, 'TONIC WATER SCHWEPPES',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(328, 'FANTA_PINE_PET 500mls',  NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(329, 'FANTA GRAPE PET 500mls', NULL, 25.00,  0.00, 'unit', 1,  1,  'item', 0),
(330, 'MONJO SODA WATER', NULL, 26.00,  0.00, 'unit', 1,  1,  'item', 0),
(331, 'KALUA',  NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(332, 'SPECIAL BB', NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(333, 'SPECIAL HD', NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(334, 'SPECIAL HG', NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(335, 'SPECIAL SAV',  NULL, 30.00,  0.00, 'unit', 1,  1,  'item', 0),
(336, 'BLACK LABEL DAMPY',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(337, 'CELER CASK WINE RED 5 LITERS', NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(338, 'MOSI LITE',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(339, 'TEA',  NULL, 35.00,  0.00, 'unit', 1,  1,  'item', 0),
(340, 'AMSTEL LITE',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(341, 'ESPRESSO', NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(342, 'INVEROCHE',  NULL, 40.00,  0.00, 'unit', 1,  1,  'item', 0),
(343, 'AMSTEL', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(344, 'BENIN BOTTLE', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(345, 'BREEZERS', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(346, 'CARRIER BAG',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(347, 'GINOLOGIST DAMPY', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(348, 'HENIEKEN SILVER',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(349, 'SPECIAL BC', NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(350, 'SPECIAL FFC',  NULL, 45.00,  0.00, 'unit', 1,  1,  'item', 0),
(351, 'ABSOLUTE LIME',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(352, 'ABSOLUTE GRAPE FRUIT', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(353, 'FOUR COUSINS WHITE 5 LITERS',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(354, 'FRUIT TREE 5 LITERS',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(355, 'HEINEKEN CAN 500mls',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(356, 'HENIEKEN CAN', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(357, 'MALAWI SHANDY',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(358, 'MINTS FRIZ0',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(359, 'PINA COLADA MOCKTAIL', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(360, 'PURE JOY FRUIT JUICE 1LITER',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(361, 'SMAL. BLOW JOB', NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(362, 'VIRGIN MOJITO',  NULL, 50.00,  0.00, 'unit', 1,  1,  'item', 0),
(363, 'APPLETTIZER',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(364, 'BEELS',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(365, 'BENIN CANNED', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(366, 'CASTLE LAGER CAN', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(367, 'GINOLOGIST CANE',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(368, 'HEINEKEN SILVER CAN 500mls', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(369, 'HUNTERS DRY CAN',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(370, 'JUICE PACK 500MLS',  NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(371, 'MILK SHAKE', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(372, 'PRAVDA', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(373, 'PREDATOR', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(374, 'PURE JOY FRUIT JUICE 500ML', NULL, 60.00,  0.00, 'unit', 1,  1,  'item', 0),
(375, 'DUCK GRILL SHANDY',  NULL, 65.00,  0.00, 'unit', 1,  1,  'item', 0),
(376, 'GINGER TEA', NULL, 65.00,  0.00, 'unit', 1,  1,  'item', 0),
(377, 'APPLE CRUMBE', NULL, 80.00,  0.00, 'unit', 4,  1,  'item', 0),
(378, 'CHERIDAN', NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(379, 'HOT CHOCOLATE',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(380, 'MOCACCINO',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(381, 'PLAIN ICE CREAM',  NULL, 80.00,  0.00, 'unit', 1,  1,  'item', 0),
(382, 'BLOW JOB', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(383, 'FOUR SPECIAL', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(384, 'JAGER BOMB', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(385, 'MAGARITA COCKTAIL',  NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(386, 'MATIN EXPRESSO', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(387, 'SPECIAL IS', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(388, 'SPRING BOK', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(389, 'WHITE RUSSIANS', NULL, 100.00, 0.00, 'unit', 1,  1,  'item', 0),
(390, 'CABANOSIS BIG',  NULL, 120.00, 0.00, 'unit', 4,  1,  'item', 0),
(391, 'CAMEL',  NULL, 140.00, 0.00, 'unit', 1,  1,  'item', 0),
(392, 'B AND H',  NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(393, 'BLUE', NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(394, 'CAPPACCIO',  NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(395, 'COSMOPOLITAN', NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(396, 'LONG ISLAND TEA',  NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(397, 'SEX ON THE BEACH', NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(398, 'WRINGSON SPECIAL', NULL, 150.00, 0.00, 'unit', 1,  1,  'item', 0),
(399, 'DUCK GRILL COCKTAIL',  NULL, 170.00, 0.00, 'unit', 1,  1,  'item', 0),
(400, 'DUNHILL',  NULL, 170.00, 0.00, 'unit', 1,  1,  'item', 0),
(401, 'WINSTON TOBACCO',  NULL, 170.00, 0.00, 'unit', 1,  1,  'item', 0),
(402, '4 COUSINS N S ROSE', NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(403, 'COCTAIL JAR',  NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(404, 'ICE TROPEZ', NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(405, 'ROYALTY SPARKLING',  NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(406, 'SPARKLING WINE', NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(407, 'ST ANNA WHITE WINE 750 ML',  NULL, 200.00, 0.00, 'unit', 1,  1,  'item', 0),
(408, 'NEDERBURG DUET', NULL, 210.00, 0.00, 'unit', 1,  1,  'item', 0),
(409, 'ROBERTSON C SAU',  NULL, 240.00, 0.00, 'unit', 1,  1,  'item', 0),
(410, 'ROBERTSON MERLOT', NULL, 240.00, 0.00, 'unit', 1,  1,  'item', 0),
(411, 'ROBERTSON SHIRAZ', NULL, 240.00, 0.00, 'unit', 1,  1,  'item', 0),
(412, 'DUCK GRILL PITCHER', NULL, 250.00, 0.00, 'unit', 1,  1,  'item', 0),
(413, 'VINO ROSSO MELOT', NULL, 250.00, 0.00, 'unit', 1,  1,  'item', 0),
(414, 'EASY PLATTER', NULL, 280.00, 200.00, 'unit', 2,  1,  'item', 0),
(415, '4 COUSINS',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(416, 'CHAMDOR RED',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(417, 'CHAMDOR WHITE',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(418, 'KWV MERLOT', NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(419, 'KWV SHIRAZ', NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(420, 'PAERLY BAY ROSE',  NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(421, 'ROBERTSON RED 750 ML', NULL, 300.00, 0.00, 'unit', 1,  1,  'item', 0),
(422, 'ROBERTSON ROSE', NULL, 310.00, 0.00, 'unit', 1,  1,  'item', 0),
(423, 'KWV CARDONNEY 750 ML', NULL, 320.00, 0.00, 'unit', 1,  1,  'item', 0),
(424, 'NEDERBURG CHARDONAY 750ML',  NULL, 320.00, 0.00, 'unit', 1,  1,  'item', 0),
(425, 'PROTEA CARB SAUV 750 ML',  NULL, 320.00, 0.00, 'unit', 1,  1,  'item', 0),
(426, 'BONNE ESPIRANCE RED',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(427, 'CHEATEAU LIBERTAS 750 ML', NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(428, 'FAT BASTARD SAU BLANC',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(429, 'JC LE ROUX 750 ML DOMENI', NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(430, 'ROBERTSON SWEET WHITE',  NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(431, 'SAINT CELINE', NULL, 350.00, 0.00, 'unit', 1,  1,  'item', 0),
(432, 'FAT BASTARD 750 ML', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(433, 'SAINT ANNA', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(434, 'SAINT CLAIRE', NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(435, 'SAINT RAPHAEL',  NULL, 380.00, 0.00, 'unit', 1,  1,  'item', 0),
(436, '4TH STREET SWEET WINE',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(437, 'FAT BASTARD C BS', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(438, 'FAT BASTARD MERLOT', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(439, 'FAT BASTARD PINOTAGE', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(440, 'FAT BASTARD SHIRAZ', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(441, 'FOUR COUSINS ROSEV', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(442, 'MERLOT', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(443, 'NEDERBURG MELOT 750 ML', NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(444, 'SERAH CREEK',  NULL, 400.00, 0.00, 'unit', 1,  1,  'item', 0),
(445, 'BABIES ROSE',  NULL, 450.00, 0.00, 'unit', 1,  1,  'item', 0),
(446, 'ROBERTSON SWEET RED',  NULL, 460.00, 0.00, 'unit', 1,  1,  'item', 0),
(447, 'ALITA ROSE 750 ML',  NULL, 470.00, 0.00, 'unit', 1,  1,  'item', 0),
(448, 'BLANC DE BLANC', NULL, 470.00, 0.00, 'unit', 1,  1,  'item', 0),
(449, 'ALVIS DRIFT',  NULL, 480.00, 0.00, 'unit', 1,  1,  'item', 0),
(450, 'JC LE ROUX 750 ML NECTER', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(451, 'NEDERBURG CAB SAV 750 ML', NULL, 500.00, 0.00, 'unit', 1,  1,  'item', 0),
(452, 'KRONE',  NULL, 600.00, 0.00, 'unit', 1,  1,  'item', 0),
(453, 'RUPERT ROTHSCHILD',  NULL, 650.00, 0.00, 'unit', 1,  1,  'item', 0),
(454, 'CLEN CARLOU MEROT',  NULL, 700.00, 0.00, 'unit', 1,  1,  'item', 0),
(455, 'BABEL RED',  NULL, 800.00, 0.00, 'unit', 1,  1,  'item', 0),
(456, 'CATHEDRAL CELLAR', NULL, 900.00, 0.00, 'unit', 1,  1,  'item', 0),
(457, 'MEERLUST RED 750 ML',  NULL, 900.00, 0.00, 'unit', 1,  1,  'item', 0),
(458, 'Chicken Wrap', NULL, 160.00, 0.00, 'unit', 2,  1,  'item', 0);

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

TRUNCATE `restaurant_tables`;
INSERT INTO `restaurant_tables` (`id`, `location_id`, `zone_name`, `table_name`, `capacity`) VALUES
(1, 1,  'Main Dining',  'Table 1',  4),
(2, 1,  'Main Dining',  'Table 2',  4),
(3, 1,  'Main Dining',  'Table 3',  6),
(4, 1,  'Main Dining',  'Table 4',  2),
(5, 1,  'Patio / Outside',  'Patio 1',  4),
(6, 1,  'Patio / Outside',  'Patio 2',  4),
(7, 1,  'VIP Bar',  'Bar Stool A',  1),
(8, 1,  'VIP Bar',  'Bar Stool B',  1);

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


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

TRUNCATE `settings`;
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'duckGrill-POS'),
('license_tier',  'hospitality'),
('receipt_footer',  'Thank you for your business!'),
('receipt_header',  'duckGrill'),
('theme_color', '#581904');

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

TRUNCATE `users`;
INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`, `is_active`) VALUES
(1, 'admin',  'System Admin', '$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu', 'admin',  3,  '2026-02-06 19:59:35',  0,  1),
(2, 'mando',  'mando chishimba',  '$2y$10$f7zbSGxQ7gvsH5N1dEOLauAgZS3dO1cV/8Gj.UNOmK6Z2a0JxWBbu', 'dev',  6,  '2026-02-06 19:59:35',  0,  1),
(3, 'manager',  'Manager',  '$2y$10$3owGZE25FfMAUBAFy9oL7OwSu6atNLFJZW7uwH7DG8k.RC71DWnLq', 'manager',  1,  '2026-02-06 19:59:35',  0,  1),
(4, 'main_bar', 'Main Bar', '$2y$10$VlnXSk.UJ.Wkp1Zr4nIMQOkjUdZqBuW64xHxSZG4SPE45f9lwpMri', 'bartender',  2,  '2026-02-06 19:59:35',  0,  1),
(5, 'head_chef',  'Head Chef',  '$2y$10$Mjj1v4RzMY4u0hdaNUcdCeuoq5OBjWndjv4P3phfjoCmtkeEskUHy', 'head_chef',  1,  '2026-02-06 19:59:35',  0,  1),
(6, 'waiter', 'Restaurant Waiter',  '$2y$10$EGK83.eKGrOChYOMm.IWg.2IUjJNFusgrA35equMDe18uAaxY8fl2', 'waiter', 4,  '2026-02-06 19:59:35',  0,  1),
(7, 'bartender',  'Main Bartender', '$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu', 'bartender',  2,  '2026-02-06 19:59:35',  0,  0),
(8, 'chef', 'chef', '$2y$10$M9vANzbl70OlafoGFIn/b.0Kyhl6JuQi21i5d6tjcBWnV8RxtjOZ2', 'chef', 1,  '2026-02-10 08:21:50',  0,  1),
(9, 'Res_Bar',  'Restaurant Bar', '$2y$10$Tn2NAv5T0.1kqWKPKOvthOVSGRS9prQbZevAa6DYqWvQsNE/lsBvy', 'bartender',  1,  '2026-02-10 09:53:29',  0,  1),
(10,  'daliso', 'Daliso Nindi', '$2y$10$0Cmqbbba.ipH/7x1fh9QOuQ8z4FayAfR1TuvXhzShncyprrq27Z4.', 'admin',  6,  '2026-02-10 10:20:55',  0,  1),
(11,  'Front_Manager',  'Front Desk Manager', '$2y$10$F9JzTIUjAP3wP9Egyk7b3uzy7XDZzDwku4t4V8YIezc15YuY8kng6', 'manager',  1,  '2026-02-14 09:31:02',  0,  1);

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
(1, 'Coca Cola Zambia', 'Mr. Phiri',  NULL, 1),
(2, 'Zambeef',  'Sales Rep',  NULL, 1),
(3, 'Tiger Animal Feeds', 'Mrs. Banda', NULL, 1),
(4, 'Shoprite', 'Maybin', '12345678', 1),
(5, 'PRAV', 'PAVIN',  '097',  1);

-- 2026-02-28 04:01:46 UTC