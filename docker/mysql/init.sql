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
  `action_type` enum('sale','grv','transfer_in','transfer_out','adjustment') NOT NULL,
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

-- 2026-02-09 19:46:26 UTC
