-- Adminer 5.4.1 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

USE `pos_db`;

SET NAMES utf8mb4;

TRUNCATE `categories`;
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1,	'Food',	NULL),
(2,	'Meal',	NULL),
(3,	'Beverages',	NULL),
(4,	'Ingredient',	NULL);

TRUNCATE `expenses`;

TRUNCATE `grv_items`;
INSERT INTO `grv_items` (`id`, `grv_id`, `product_id`, `quantity`, `unit_cost`) VALUES
(1,	1,	1,	100.00,	130.00),
(2,	2,	2,	500.00,	750.00),
(3,	3,	1,	50.00,	130.00),
(4,	4,	3,	100.00,	130.00),
(5,	5,	5,	100.00,	50.00),
(6,	6,	4,	60.00,	35.00);

TRUNCATE `grvs`;
INSERT INTO `grvs` (`id`, `vendor_id`, `location_id`, `received_by`, `total_cost`, `reference_no`, `created_at`) VALUES
(1,	1,	1,	1,	13000.00,	'INV-001-300126',	'2026-01-30 05:35:11'),
(2,	3,	3,	5,	375000.00,	'',	'2026-01-30 07:16:02'),
(3,	1,	3,	5,	6500.00,	'',	'2026-01-30 08:02:25'),
(4,	1,	3,	5,	13000.00,	'',	'2026-01-31 12:38:16'),
(5,	2,	3,	5,	5000.00,	'',	'2026-01-31 13:38:05'),
(6,	3,	3,	5,	2100.00,	'',	'2026-01-31 13:38:25');

TRUNCATE `inventory_transfers`;
INSERT INTO `inventory_transfers` (`id`, `source_location_id`, `dest_location_id`, `product_id`, `quantity`, `user_id`, `status`, `created_at`, `dispatched_at`, `received_at`) VALUES
(1,	7,	3,	2,	10.00,	5,	'cancelled',	'2026-01-30 09:54:39',	NULL,	NULL),
(2,	3,	2,	1,	5.00,	8,	'completed',	'2026-01-30 10:01:12',	'2026-01-30 10:03:04',	'2026-01-30 10:04:33'),
(3,	3,	1,	2,	5.00,	4,	'completed',	'2026-01-30 10:05:13',	'2026-01-30 10:05:29',	'2026-01-30 10:07:22'),
(4,	3,	1,	3,	10.00,	4,	'completed',	'2026-01-31 14:36:15',	'2026-01-31 14:38:39',	'2026-01-31 14:39:13'),
(5,	3,	1,	5,	10.00,	4,	'completed',	'2026-01-31 15:41:05',	'2026-01-31 15:42:06',	'2026-01-31 15:42:46'),
(6,	3,	1,	4,	10.00,	4,	'completed',	'2026-01-31 15:41:18',	'2026-01-31 15:42:13',	'2026-01-31 15:42:49');

TRUNCATE `location_stock`;
INSERT INTO `location_stock` (`id`, `location_id`, `product_id`, `quantity`) VALUES
(1,	1,	1,	90.00),
(2,	3,	2,	455.00),
(3,	1,	2,	55.00),
(4,	3,	1,	55.00),
(6,	2,	1,	-6.00),
(9,	3,	3,	110.00),
(11,	1,	3,	5.00),
(12,	3,	5,	110.00),
(13,	3,	4,	70.00),
(16,	1,	5,	8.00),
(17,	1,	4,	10.00);

TRUNCATE `locations`;
INSERT INTO `locations` (`id`, `name`, `type`, `can_sell`, `can_receive_from_vendor`, `address`, `phone`) VALUES
(1,	'Kitchen',	'store',	1,	0,	'',	'555-0000'),
(2,	'Main Bar',	'store',	1,	0,	'',	'555-0000'),
(3,	'Main Storeroom',	'warehouse',	1,	0,	'',	'555-0000'),
(4,	'Restaurant Bar',	'store',	1,	0,	'',	'555-0000'),
(5,	'Outside Bar',	'store',	1,	0,	'',	'555-0000'),
(7,	'Mini storeroom',	'store',	1,	0,	'',	'555-0000'),
(8,	'Warehouse',	'warehouse',	1,	0,	'',	'555-0000'),
(9,	'Main Branch',	'warehouse',	1,	0,	'',	'555-0000'),
(12,	'shop',	'store',	1,	0,	'',	'555-0000');

TRUNCATE `pickup_notifications`;
INSERT INTO `pickup_notifications` (`id`, `sale_id`, `item_name`, `status`, `collected_by`, `created_at`) VALUES
(1,	1,	'Beef Sausage',	'collected',	3,	'2026-01-30 07:00:08');

TRUNCATE `products`;
INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`) VALUES
(1,	'Beef Sausage',	NULL,	130.00,	100.00,	'Kg',	2,	1),
(2,	'Flour',	NULL,	750.00,	500.00,	'Kg',	4,	1),
(3,	'T-Bone Steak',	NULL,	120.00,	0.00,	'unit',	2,	1),
(4,	'Mosi Larger',	NULL,	35.00,	20.00,	'ml',	3,	1),
(5,	'Amarulla',	NULL,	134.00,	100.00,	'ml',	3,	1);

TRUNCATE `refund_requests`;

TRUNCATE `sale_items`;
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price_at_sale`, `cost_at_sale`, `status`) VALUES
(1,	1,	1,	1,	130.00,	0.00,	'served'),
(2,	2,	3,	1,	120.00,	0.00,	'served'),
(3,	3,	1,	1,	130.00,	0.00,	'served'),
(4,	3,	3,	1,	120.00,	0.00,	'served'),
(5,	4,	3,	1,	120.00,	0.00,	'served'),
(6,	5,	3,	1,	120.00,	0.00,	'served'),
(7,	6,	1,	1,	130.00,	0.00,	'served'),
(8,	7,	3,	3,	120.00,	0.00,	'served'),
(9,	8,	4,	1,	35.00,	0.00,	'served'),
(10,	8,	5,	2,	134.00,	0.00,	'served'),
(11,	8,	3,	1,	120.00,	0.00,	'served'),
(12,	8,	1,	1,	130.00,	0.00,	'served'),
(13,	9,	5,	1,	134.00,	0.00,	'served'),
(14,	10,	3,	1,	120.00,	0.00,	'served'),
(15,	11,	1,	5,	130.00,	0.00,	'served'),
(16,	12,	1,	5,	130.00,	0.00,	'served'),
(17,	12,	3,	5,	120.00,	0.00,	'served'),
(18,	13,	1,	1,	130.00,	0.00,	'served'),
(19,	14,	1,	1,	130.00,	0.00,	'served'),
(20,	15,	3,	1,	120.00,	0.00,	'served'),
(21,	16,	1,	1,	130.00,	0.00,	'served'),
(22,	17,	3,	1,	120.00,	0.00,	'served'),
(23,	18,	1,	1,	130.00,	0.00,	'served'),
(24,	19,	1,	1,	130.00,	0.00,	'served'),
(25,	20,	3,	1,	120.00,	0.00,	'served'),
(26,	21,	1,	1,	130.00,	0.00,	'served'),
(27,	22,	3,	1,	120.00,	0.00,	'served'),
(28,	23,	3,	1,	120.00,	0.00,	'served'),
(29,	23,	1,	1,	130.00,	0.00,	'served'),
(30,	24,	5,	1,	134.00,	0.00,	'served'),
(31,	24,	3,	1,	120.00,	0.00,	'served'),
(32,	25,	3,	1,	120.00,	0.00,	'served'),
(33,	25,	5,	1,	134.00,	0.00,	'served');

TRUNCATE `sales`;
INSERT INTO `sales` (`id`, `location_id`, `user_id`, `shift_id`, `total_amount`, `discount`, `total_tax`, `tip`, `final_total`, `payment_method`, `status`, `created_at`, `collected_by`) VALUES
(1,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-30 06:09:19',	NULL),
(2,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-30 11:45:13',	NULL),
(3,	1,	4,	2,	250.00,	0.00,	0.00,	0.00,	250.00,	'cash',	'completed',	'2026-01-30 11:46:25',	NULL),
(4,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-30 11:48:08',	NULL),
(5,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-30 11:55:21',	NULL),
(6,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-30 11:59:26',	NULL),
(7,	1,	3,	1,	360.00,	0.00,	0.00,	0.00,	360.00,	'cash',	'completed',	'2026-01-30 18:07:09',	NULL),
(8,	2,	7,	5,	553.00,	0.00,	0.00,	0.00,	553.00,	'cash',	'completed',	'2026-01-31 03:56:30',	NULL),
(9,	2,	7,	5,	134.00,	0.00,	0.00,	0.00,	134.00,	'cash',	'completed',	'2026-01-31 04:03:30',	NULL),
(10,	2,	7,	5,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-31 04:03:39',	NULL),
(11,	2,	7,	5,	650.00,	0.00,	0.00,	0.00,	650.00,	'cash',	'completed',	'2026-01-31 04:05:40',	'Mumba Bar-Manager'),
(12,	2,	7,	5,	1250.00,	0.00,	0.00,	0.00,	1250.00,	'cash',	'completed',	'2026-01-31 04:06:53',	'Mumba Bar-Manager'),
(13,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 04:07:41',	'Mwale Kitchen Cashier'),
(14,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 04:07:45',	'Mwale Kitchen Cashier'),
(15,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-31 04:07:49',	'Mwale Kitchen Cashier'),
(16,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 04:07:53',	'Mwale Kitchen Cashier'),
(17,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-31 04:07:56',	'Mwale Kitchen Cashier'),
(18,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 04:08:01',	'Mwale Kitchen Cashier'),
(19,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 04:08:05',	'Mwale Kitchen Cashier'),
(20,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-31 12:40:41',	'Mwale Kitchen Cashier'),
(21,	1,	3,	1,	130.00,	0.00,	0.00,	0.00,	130.00,	'cash',	'completed',	'2026-01-31 12:49:55',	'Mwale Kitchen Cashier'),
(22,	1,	3,	1,	120.00,	0.00,	0.00,	0.00,	120.00,	'cash',	'completed',	'2026-01-31 12:53:08',	'Mwale Kitchen Cashier'),
(23,	1,	3,	1,	250.00,	0.00,	0.00,	0.00,	250.00,	'cash',	'completed',	'2026-01-31 12:54:43',	'Mwale Kitchen Cashier'),
(24,	1,	3,	1,	254.00,	0.00,	0.00,	0.00,	254.00,	'mobile_money',	'completed',	'2026-01-31 13:43:39',	'Mwale Kitchen Cashier'),
(25,	1,	3,	1,	254.00,	0.00,	0.00,	0.00,	254.00,	'cash',	'completed',	'2026-01-31 13:46:37',	'Mwale Kitchen Cashier');

TRUNCATE `shifts`;
INSERT INTO `shifts` (`id`, `user_id`, `location_id`, `start_time`, `end_time`, `starting_cash`, `closing_cash`, `expected_cash`, `manager_closing_cash`, `status`, `variance_reason`, `handover_notes`, `start_verified_by`, `start_verified_at`, `end_verified_by`, `end_verified_at`) VALUES
(1,	3,	1,	'2026-01-30 06:09:06',	NULL,	100.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	1,	'2026-01-30 06:09:06',	NULL,	NULL),
(2,	4,	1,	'2026-01-30 06:58:36',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	1,	'2026-01-30 06:58:36',	NULL,	NULL),
(3,	6,	1,	'2026-01-30 11:56:10',	NULL,	0.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	1,	'2026-01-30 11:56:10',	NULL,	NULL),
(4,	8,	2,	'2026-01-30 18:12:30',	NULL,	500.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	1,	'2026-01-30 18:12:30',	NULL,	NULL),
(5,	7,	2,	'2026-01-30 18:23:48',	NULL,	100.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	8,	'2026-01-30 18:23:48',	NULL,	NULL),
(6,	1,	3,	'2026-01-31 05:08:33',	NULL,	100.00,	0.00,	0.00,	0.00,	'open',	NULL,	NULL,	5,	'2026-01-31 05:08:33',	NULL,	NULL);

TRUNCATE `stock_transfer_items`;
INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `product_id`, `quantity_requested`, `quantity_sent`, `quantity_received`) VALUES
(1,	1,	2,	50.00,	50.00,	50.00);

TRUNCATE `stock_transfers`;
INSERT INTO `stock_transfers` (`id`, `source_location_id`, `destination_location_id`, `user_id`, `status`, `created_at`) VALUES
(1,	3,	1,	5,	'completed',	'2026-01-30 07:16:26');

TRUNCATE `taxes`;

TRUNCATE `users`;
INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`) VALUES
(1,	'odelia_admin',	'Mando Odelia',	'$2y$10$Hf3oqWOf/u3p8mVDynHZp.Fr.9bgbxm6ptvrZCiqHEmSBs5MByTz2',	'dev',	9,	'2026-01-29 12:55:36',	0),
(3,	'cashier',	'Mwale Kitchen Cashier',	'$2y$10$dorPozd1gh1C8YoG5MqBbe4DPQff/U9QQbTOduBMf2T5y4u6BZSpO',	'cashier',	1,	'2026-01-29 12:55:36',	1),
(4,	'head_chef',	'Head Chef',	'$2y$10$SdL7lTZSPTS5QLhje3BCeeq08kcW5jLd3SNZpWTvoVbgWboXSut9O',	'head_chef',	1,	'2026-01-30 05:37:27',	1),
(5,	'stores_manager',	'Choolwe Stores Manager',	'$2y$10$FB40ePAQ5abCVXLEhjuVI.G8Ebe/9s8rWxCUPGRcWvXHxy85Ufifi',	'manager',	3,	'2026-01-30 06:39:29',	1),
(6,	'chef',	'Sililo Chef',	'$2y$10$hAAvflXVOdkSTkpgESAj5O2w3TfRlZe4uW/OiKw0WlusFUdzmseWK',	'chef',	1,	'2026-01-30 06:40:05',	1),
(7,	'Main_bartender',	'Main Bar Bartender',	'$2y$10$1LpLTw/HiJsIEbNjwZ44zuNm.K.DiDtFretzcA65M7RV6tliGCN86',	'bartender',	2,	'2026-01-30 06:40:57',	1),
(8,	'Bar_Manager',	'Mumba Bar-Manager',	'$2y$10$LYO5xnXXOYai3a9S2qZMIuH4loofSX3UzXRD/lUKanlykNt9UDh1S',	'manager',	2,	'2026-01-30 07:59:03',	1),
(10,	'Daliso',	'Daliso Nindi',	'$2y$10$VlTfRDkhaOa3Mi.l7Eubd.fr/yfJ5m5fixiDVgs45nZroOUPhtYty',	'admin',	9,	'2026-01-30 18:19:15',	1),
(11,	'Admin',	'Admininistaror Account',	'$2y$10$ko3GeD23e0xGx5uVI.sFl.kCwhrw56eHBD9XtS9iwUo9ov2b9uZ2u',	'admin',	9,	'2026-01-31 07:09:34',	1),
(12,	'mary_sales',	'Mary sales lady',	'$2y$10$G5lCwVTFpHyZGUTgUR5oZOlZA71YAa3sm/VMh8hp087ePV2E3/iM.',	'shopkeeper',	12,	'2026-01-31 14:06:24',	1);

TRUNCATE `vendors`;
INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`) VALUES
(1,	'Zambeef Zambia',	'Mr. Phir',	''),
(2,	'Coca Cola Zambia',	'Mr. Daliso',	''),
(3,	'Shoprite Zambia',	'Mr. Choogo',	'');

-- 2026-01-31 14:10:46 UTC