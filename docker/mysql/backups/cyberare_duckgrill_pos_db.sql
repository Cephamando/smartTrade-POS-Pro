-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 14, 2026 at 05:44 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cyberare_duckgrill_pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('food','drink','meal','ingredients','other') NOT NULL DEFAULT 'other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `type`) VALUES
(1, 'Beverages', NULL, 'drink'),
(2, 'Meals', NULL, 'food'),
(3, 'Ingredients', NULL, 'ingredients'),
(4, 'Snacks', NULL, 'food'),
(5, 'Cleaning Material', NULL, 'other');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `description` varchar(255) NOT NULL DEFAULT 'Expense'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grvs`
--

CREATE TABLE `grvs` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grv_items`
--

CREATE TABLE `grv_items` (
  `id` int(11) NOT NULL,
  `grv_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `location_id`, `quantity`, `updated_at`) VALUES
(1, 15, 4, 181, '2026-02-14 05:01:46'),
(2, 15, 3, 14, '2026-02-13 16:30:21'),
(4, 15, 1, 19, '2026-02-13 20:37:36'),
(5, 16, 3, 430, '2026-02-14 05:05:04'),
(6, 16, 2, 67, '2026-02-14 05:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `change_qty` decimal(10,2) NOT NULL,
  `after_qty` decimal(10,2) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `product_id`, `location_id`, `user_id`, `change_qty`, `after_qty`, `action_type`, `reference_id`, `created_at`) VALUES
(1, 15, 4, 1, 100.00, 100.00, 'restock', 0, '2026-02-13 15:40:37'),
(2, 15, 4, 1, -5.00, 195.00, 'transfer_out', 1, '2026-02-13 15:56:39'),
(3, 15, 3, 1, 5.00, 5.00, 'transfer_in', 1, '2026-02-13 15:56:42'),
(4, 15, 4, 1, -1.00, 194.00, 'sale', 1, '2026-02-13 15:57:27'),
(5, 15, 4, 1, -10.00, 184.00, 'transfer_out', 2, '2026-02-13 16:28:59'),
(6, 15, 3, 10, 10.00, 15.00, 'transfer_in', 2, '2026-02-13 16:29:42'),
(7, 15, 3, 8, -1.00, 14.00, 'sale', 2, '2026-02-13 16:30:21'),
(8, 15, 4, 1, -2.00, 182.00, 'sale', 3, '2026-02-13 20:24:34'),
(9, 15, 4, 1, -1.00, 181.00, 'sale', 4, '2026-02-13 20:24:49'),
(10, 15, 4, 1, -20.00, 161.00, 'transfer_out', 3, '2026-02-13 20:37:20'),
(11, 15, 1, 1, 20.00, 20.00, 'transfer_in', 3, '2026-02-13 20:37:23'),
(12, 15, 1, 1, -1.00, 19.00, 'sale', 5, '2026-02-13 20:37:36'),
(13, 15, 4, 1, 20.00, 181.00, 'restock', 0, '2026-02-14 05:01:46'),
(14, 16, 3, 1, 500.00, 500.00, 'restock', 0, '2026-02-14 05:03:53'),
(15, 16, 3, 1, -70.00, 430.00, 'transfer_out', 4, '2026-02-14 05:05:04'),
(16, 16, 2, 1, 70.00, 70.00, 'transfer_in', 4, '2026-02-14 05:05:08'),
(17, 16, 2, 1, -1.00, 69.00, 'sale', 6, '2026-02-14 05:06:26'),
(18, 16, 2, 1, -1.00, 68.00, 'sale', 7, '2026-02-14 05:09:16'),
(19, 16, 2, 1, -1.00, 67.00, 'sale', 7, '2026-02-14 05:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transfers`
--

CREATE TABLE `inventory_transfers` (
  `id` int(11) NOT NULL,
  `source_location_id` int(11) NOT NULL,
  `dest_location_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `dispatched_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_transfers`
--

INSERT INTO `inventory_transfers` (`id`, `source_location_id`, `dest_location_id`, `product_id`, `quantity`, `user_id`, `status`, `created_at`, `dispatched_at`, `received_at`) VALUES
(1, 4, 3, 15, 5.00, 1, 'completed', '2026-02-13 15:56:27', '2026-02-13 15:56:39', '2026-02-13 15:56:42'),
(2, 4, 3, 15, 10.00, 8, 'completed', '2026-02-13 16:28:41', '2026-02-13 16:28:59', '2026-02-13 16:29:42'),
(3, 4, 1, 15, 20.00, 1, 'completed', '2026-02-13 20:37:15', '2026-02-13 20:37:20', '2026-02-13 20:37:23'),
(4, 3, 2, 16, 70.00, 1, 'completed', '2026-02-14 05:05:00', '2026-02-14 05:05:04', '2026-02-14 05:05:08');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `type` enum('store','kitchen','bar','warehouse') NOT NULL DEFAULT 'store',
  `can_sell` tinyint(1) DEFAULT 1,
  `can_receive_from_vendor` tinyint(1) DEFAULT 0,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT '555-0000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `type`, `can_sell`, `can_receive_from_vendor`, `address`, `phone`) VALUES
(1, 'main bar', 'store', 1, 0, '', '555-0000'),
(2, 'outside bar', 'store', 1, 0, '', '555-0000'),
(3, 'Kitchen', 'warehouse', 1, 0, '', '555-0000'),
(4, 'Main Warehouse', 'warehouse', 1, 0, '', '555-0000'),
(5, 'mini storeroom', 'store', 1, 0, '', '555-0000');

-- --------------------------------------------------------

--
-- Table structure for table `location_stock`
--

CREATE TABLE `location_stock` (
  `id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `location_stock`
--

INSERT INTO `location_stock` (`id`, `location_id`, `product_id`, `quantity`) VALUES
(1, 3, 15, 0.00),
(2, 3, 16, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `points_balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `phone`, `email`, `points_balance`, `created_at`) VALUES
(1, 'JAMES CHABOOKA', '0977', 'JCHABOOK@YAHOO.COM', 0.00, '2026-02-13 06:43:31');

-- --------------------------------------------------------

--
-- Table structure for table `pickup_notifications`
--

CREATE TABLE `pickup_notifications` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `status` enum('ready','collected') DEFAULT 'ready',
  `collected_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pickup_notifications`
--

INSERT INTO `pickup_notifications` (`id`, `sale_id`, `item_name`, `status`, `collected_by`, `created_at`) VALUES
(1, 1, 'Beef Burger', 'ready', NULL, '2026-02-13 15:57:45'),
(2, 2, 'Beef Burger', 'ready', NULL, '2026-02-13 16:31:04'),
(3, 3, 'Beef Burger', 'ready', NULL, '2026-02-13 20:37:44'),
(4, 4, 'Beef Burger', 'ready', NULL, '2026-02-13 20:37:47'),
(5, 5, 'Beef Burger', 'ready', NULL, '2026-02-13 20:37:48'),
(6, 6, 'T-Bone Steak', 'ready', NULL, '2026-02-14 05:10:46'),
(7, 7, 'T-Bone Steak', 'ready', NULL, '2026-02-14 05:10:49');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'unit',
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `type` enum('item','service') DEFAULT 'item',
  `is_open_price` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `price`, `cost_price`, `unit`, `category_id`, `is_active`, `type`, `is_open_price`) VALUES
(15, 'Beef Burger', NULL, 70.00, 50.00, 'Kg', 2, 1, 'item', 0),
(16, 'T-Bone Steak', 'Tbone001', 60.00, 40.00, 'kg', 2, 1, 'item', 0);

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `amount_refunded` decimal(10,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_tax` decimal(10,2) DEFAULT 0.00,
  `tip` decimal(10,2) DEFAULT 0.00,
  `final_total` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `status` enum('completed','refund_requested','refunded','partially_refunded') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `collected_by` varchar(100) DEFAULT NULL,
  `payment_status` enum('paid','pending','refunded') NOT NULL DEFAULT 'pending',
  `customer_name` varchar(100) DEFAULT 'Walk-in',
  `amount_tendered` decimal(10,2) DEFAULT 0.00,
  `change_due` decimal(10,2) DEFAULT 0.00,
  `member_id` int(11) DEFAULT NULL,
  `points_earned` decimal(10,2) DEFAULT 0.00,
  `points_redeemed` decimal(10,2) DEFAULT 0.00,
  `split_group_id` varchar(50) DEFAULT NULL,
  `split_type` enum('none','item','even','custom') DEFAULT 'none',
  `tip_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `location_id`, `user_id`, `shift_id`, `total_amount`, `discount`, `total_tax`, `tip`, `final_total`, `payment_method`, `status`, `created_at`, `collected_by`, `payment_status`, `customer_name`, `amount_tendered`, `change_due`, `member_id`, `points_earned`, `points_redeemed`, `split_group_id`, `split_type`, `tip_amount`) VALUES
(1, 4, 1, 4, 70.00, 0.00, 0.00, 0.00, 70.00, 'Cash', 'completed', '2026-02-13 15:57:27', NULL, 'paid', 'DJ', 70.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(2, 3, 8, 3, 70.00, 0.00, 0.00, 0.00, 70.00, 'Cash', 'completed', '2026-02-13 16:30:21', NULL, 'paid', 'Walk-in', 70.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(3, 4, 1, 4, 140.00, 0.00, 0.00, 0.00, 140.00, 'MTN Money', 'completed', '2026-02-13 20:24:34', NULL, 'paid', 'Walk-in', 140.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(4, 4, 1, 4, 70.00, 0.00, 0.00, 0.00, 70.00, 'Card', 'completed', '2026-02-13 20:24:49', NULL, 'paid', 'Walk-in', 70.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(5, 1, 1, 5, 70.00, 0.00, 0.00, 0.00, 70.00, 'Cash', 'completed', '2026-02-13 20:37:36', NULL, 'paid', 'Walk-in', 70.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(6, 2, 1, 6, 60.00, 0.00, 0.00, 0.00, 60.00, 'Pending', 'completed', '2026-02-14 05:06:26', NULL, 'pending', 'Mweemba - table  5', 0.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00),
(7, 2, 1, 6, 120.00, 0.00, 0.00, 0.00, 120.00, 'Pending', 'completed', '2026-02-14 05:09:16', NULL, 'pending', 'Bwalya table -5', 0.00, 0.00, NULL, 0.00, 0.00, NULL, 'none', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_sale` decimal(10,2) NOT NULL,
  `cost_at_sale` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','cooking','ready','served') DEFAULT 'pending',
  `updated_at` timestamp NULL DEFAULT NULL,
  `fulfillment_status` enum('collected','uncollected') DEFAULT 'collected'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price_at_sale`, `cost_at_sale`, `status`, `updated_at`, `fulfillment_status`) VALUES
(2, 1, 15, 1, 70.00, 0.00, 'ready', NULL, 'collected'),
(3, 2, 15, 1, 70.00, 0.00, 'ready', NULL, 'collected'),
(4, 3, 15, 2, 70.00, 0.00, 'ready', NULL, 'collected'),
(5, 4, 15, 1, 70.00, 0.00, 'ready', NULL, 'collected'),
(6, 5, 15, 1, 70.00, 0.00, 'ready', NULL, 'collected'),
(7, 6, 16, 1, 60.00, 0.00, 'ready', NULL, 'collected'),
(8, 7, 16, 1, 60.00, 0.00, 'ready', NULL, 'uncollected'),
(9, 7, 16, 1, 60.00, 0.00, 'pending', NULL, 'uncollected');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `start_time` timestamp NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `starting_cash` decimal(10,2) DEFAULT 0.00,
  `closing_cash` decimal(10,2) DEFAULT 0.00,
  `expected_cash` decimal(10,2) DEFAULT 0.00,
  `manager_closing_cash` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending_approval','open','closed') DEFAULT 'pending_approval',
  `variance_reason` text DEFAULT NULL,
  `handover_notes` text DEFAULT NULL,
  `start_verified_by` int(11) DEFAULT NULL,
  `start_verified_at` timestamp NULL DEFAULT NULL,
  `end_verified_by` int(11) DEFAULT NULL,
  `end_verified_at` timestamp NULL DEFAULT NULL,
  `variance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `user_id`, `location_id`, `start_time`, `end_time`, `starting_cash`, `closing_cash`, `expected_cash`, `manager_closing_cash`, `status`, `variance_reason`, `handover_notes`, `start_verified_by`, `start_verified_at`, `end_verified_by`, `end_verified_at`, `variance`) VALUES
(1, 1, 1, '2026-02-13 03:07:58', '2026-02-13 09:07:09', 0.00, 0.00, 0.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00),
(2, 10, 1, '2026-02-13 06:18:13', NULL, 0.00, 0.00, 0.00, 0.00, 'pending_approval', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(3, 8, 3, '2026-02-13 09:08:14', NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(4, 1, 4, '2026-02-13 15:56:56', '2026-02-13 20:36:19', 0.00, 70.00, 70.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00),
(5, 1, 1, '2026-02-13 20:36:34', '2026-02-14 05:05:24', 0.00, 70.00, 70.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00),
(6, 1, 2, '2026-02-14 05:05:47', '2026-02-14 05:40:31', 400.00, 400.00, 400.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `source_location_id` int(11) NOT NULL,
  `destination_location_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','completed','cancelled','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL,
  `quantity_sent` decimal(10,2) DEFAULT 0.00,
  `quantity_received` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `taxes`
--

CREATE TABLE `taxes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `from_location_id` int(11) NOT NULL,
  `to_location_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','completed') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','shopkeeper','manager','cashier','dev','chef','waiter','head_chef','bartender') NOT NULL DEFAULT 'cashier',
  `location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `force_password_change` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `location_id`, `created_at`, `force_password_change`, `is_active`) VALUES
(1, 'admin', 'System Admin', '$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu', 'admin', 3, '2026-02-06 19:59:35', 0, 1),
(2, 'mando', 'mando chishimba', '$2y$10$f7zbSGxQ7gvsH5N1dEOLauAgZS3dO1cV/8Gj.UNOmK6Z2a0JxWBbu', 'dev', 3, '2026-02-06 19:59:35', 0, 1),
(3, 'manager', 'Manager', '$2y$10$3owGZE25FfMAUBAFy9oL7OwSu6atNLFJZW7uwH7DG8k.RC71DWnLq', 'manager', 2, '2026-02-06 19:59:35', 0, 1),
(4, 'main_bar', 'Main Bar', '$2y$10$rt3.rImohXcDiq/J4HikCOQJp..byFlP4zaFxvLCRgZBVoW9R6TOi', 'cashier', 2, '2026-02-06 19:59:35', 0, 1),
(5, 'head_chef', 'Head Chef', '$2y$10$oC8.PLHRf618Hx3vHSBMHusOiRFb9m82/mnRg3zYyOJ9eKLZh/CRi', 'manager', 1, '2026-02-06 19:59:35', 0, 1),
(6, 'waiter', 'Restaurant Waiter', '$2y$10$44N80Jh9tzg6KlcvyXoj..2pjF705XsJjsU/TinTWdEW3BPKBq9ni', 'cashier', 4, '2026-02-06 19:59:35', 0, 1),
(7, 'bartender', 'Main Bartender', '$2y$10$AJ1DKDKmwF3BHFNYpQUgwOTwbzuUCXH04KMXRykW.chnVaFT2NIfu', 'bartender', 2, '2026-02-06 19:59:35', 0, 0),
(8, 'chef', 'chef', '$2y$10$M9vANzbl70OlafoGFIn/b.0Kyhl6JuQi21i5d6tjcBWnV8RxtjOZ2', 'cashier', NULL, '2026-02-10 08:21:50', 0, 1),
(9, 'Res_Bar', 'Restaurant Bar', '$2y$10$kgBG5wcxk3KB1/37QJU3ZuTxGkwD8PAzVha11irdvuY.N.IdrZaD2', 'cashier', NULL, '2026-02-10 09:53:29', 0, 1),
(10, 'daliso', 'Daliso Nindi', '$2y$10$0Cmqbbba.ipH/7x1fh9QOuQ8z4FayAfR1TuvXhzShncyprrq27Z4.', 'admin', NULL, '2026-02-10 10:20:55', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `is_active`) VALUES
(1, 'Coca Cola Zambia', 'Mr. Phiri', NULL, 1),
(2, 'Zambeef', 'Sales Rep', NULL, 1),
(3, 'Tiger Animal Feeds', 'Mrs. Banda', NULL, 1),
(4, 'Shoprite', 'Maybin', '12345678', 1),
(5, 'PRAV', 'PAVIN', '097', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `grvs`
--
ALTER TABLE `grvs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `grv_items`
--
ALTER TABLE `grv_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grv_id` (`grv_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stock` (`product_id`,`location_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `location_stock`
--
ALTER TABLE `location_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loc_prod_unique` (`location_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `pickup_notifications`
--
ALTER TABLE `pickup_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `requested_by_user_id` (`requested_by_user_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `start_verified_by` (`start_verified_by`),
  ADD KEY `end_verified_by` (`end_verified_by`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_location_id` (`source_location_id`),
  ADD KEY `destination_location_id` (`destination_location_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `taxes`
--
ALTER TABLE `taxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grvs`
--
ALTER TABLE `grvs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grv_items`
--
ALTER TABLE `grv_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `location_stock`
--
ALTER TABLE `location_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pickup_notifications`
--
ALTER TABLE `pickup_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `taxes`
--
ALTER TABLE `taxes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `grvs`
--
ALTER TABLE `grvs`
  ADD CONSTRAINT `grvs_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `grvs_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `grvs_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `grv_items`
--
ALTER TABLE `grv_items`
  ADD CONSTRAINT `grv_items_ibfk_1` FOREIGN KEY (`grv_id`) REFERENCES `grvs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grv_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `location_stock`
--
ALTER TABLE `location_stock`
  ADD CONSTRAINT `location_stock_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_stock_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD CONSTRAINT `refund_requests_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `refund_requests_ibfk_2` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shifts_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `shifts_ibfk_3` FOREIGN KEY (`start_verified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shifts_ibfk_4` FOREIGN KEY (`end_verified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`source_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`destination_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
