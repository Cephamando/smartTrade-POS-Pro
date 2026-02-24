-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 24, 2026 at 03:12 PM
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

--
-- Dumping data for table `grvs`
--

INSERT INTO `grvs` (`id`, `vendor_id`, `location_id`, `received_by`, `total_cost`, `reference_no`, `created_at`) VALUES
(2, 5, 4, 10, 400.00, '', '2026-02-23 16:42:35'),
(3, 5, 2, 10, 500.00, '', '2026-02-23 16:48:06'),
(4, 5, 1, 10, 88200.00, '', '2026-02-23 16:56:31');

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

--
-- Dumping data for table `grv_items`
--

INSERT INTO `grv_items` (`id`, `grv_id`, `product_id`, `quantity`, `unit_cost`) VALUES
(2, 2, 77, 1.00, 400.00),
(3, 3, 59, 1.00, 500.00),
(4, 4, 207, 42.00, 2100.00);

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
(8, 77, 4, 1, '2026-02-23 16:42:35'),
(9, 59, 2, 1, '2026-02-23 16:48:06'),
(10, 207, 1, 42, '2026-02-23 16:56:31');

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
(21, 77, 4, 10, 1.00, 1.00, 'restock', 2, '2026-02-23 16:42:35'),
(22, 59, 2, 10, 1.00, 1.00, 'restock', 3, '2026-02-23 16:48:06'),
(23, 207, 1, 10, 42.00, 42.00, 'restock', 4, '2026-02-23 16:56:31');

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
(4, 'Main Warehouse', 'warehouse', 0, 1, '', '555-0000'),
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
(18, 'JAMESON IPA 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(19, 'HEINEKEN SILVER NRB 340mls', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(20, 'COSMO', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(21, 'VICE MOJITO', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(22, 'CUVEE ROSE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(23, 'LAUTUS WINE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(24, 'CALCIUM 5 L', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(25, 'CABANOSIS', NULL, 35.00, 0.00, 'unit', 4, 1, 'item', 0),
(26, '1430 CIDER', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(27, 'RASPBERRY GRENACHE', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(28, 'CHATEAU CAN', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(29, 'HENNESSY VERY SPECIAL', NULL, 90.00, 0.00, 'unit', 1, 1, 'item', 0),
(30, 'GINOLOGIST TREE SET', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(31, 'ROBERTSON PINOTAGE', NULL, 240.00, 0.00, 'unit', 1, 1, 'item', 0),
(32, 'KWV PINOTAGE', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(33, 'ROBERTSON CHAPHEL RED', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(34, 'VAN LOVEREN PINOTAGE', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(35, 'GOLDEN KAAN', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(36, 'PEARLY BAY', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(37, 'FAT BASTARD CHARDONNAY 750 ML', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(38, 'PROTEA ROSE', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(39, 'TALL HORSE SHIRAZ', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(40, 'NEDERBURG SHIRAZ', NULL, 520.00, 0.00, 'unit', 1, 1, 'item', 0),
(41, 'JP CHENET', NULL, 600.00, 0.00, 'unit', 1, 1, 'item', 0),
(42, 'VAN LOVREN BRUT 750 ML', NULL, 700.00, 0.00, 'unit', 1, 1, 'item', 0),
(43, 'ALITA CUVEE BRUT 750 ML', NULL, 750.00, 0.00, 'unit', 1, 1, 'item', 0),
(44, 'FAIRVIEW SWEET RED SOFT', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(45, 'MOJITO', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(46, 'BROTHERS STRAWBERRY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(47, 'GRANULAR CHLORIDE 2 KGs', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(48, 'BUTTLERS TRIPPLE SEC', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(49, 'FOUR COUSINS SWEET RED 3L', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(50, 'VICE PINACOLANDA', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(51, 'HENNESSY VSOP', NULL, 150.00, 0.00, 'unit', 1, 1, 'item', 0),
(52, 'PROTEA MERLOT', NULL, 320.00, 0.00, 'unit', 1, 1, 'item', 0),
(53, 'PAUL CLUVER', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(54, 'CRONIER SWEET RED', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(55, 'JC LE ROUX LA CHANSON 750', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(56, 'KWV CHARDONNAY', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(57, 'PORCUPINE RIDGE 750mls', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(58, 'TALL HORSE MERLOT', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(59, 'ZONNEBLOEM', NULL, 500.00, 500.00, 'unit', 1, 1, 'item', 0),
(60, 'GLEN CARLOU MERLOT 750mls', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(61, 'FRANSEHHOEK MERLOT', NULL, 700.00, 0.00, 'unit', 1, 1, 'item', 0),
(62, '1698 PINOTAGE', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(63, 'DE KRANS', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(64, 'FAIRVIEW SWEET RED', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(65, 'WAR WICK', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(66, 'CEDERBERG', NULL, 900.00, 0.00, 'unit', 1, 1, 'item', 0),
(67, 'TRANSCHOEK CELLAR', NULL, 900.00, 0.00, 'unit', 1, 1, 'item', 0),
(68, 'REAM OF PAPER', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(69, 'MINERAL VATRA WATER 500ML', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(70, 'STRAWBERRY LIPS', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(71, 'DISARONNO', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(72, 'CELLAR CASK RED 5L', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(73, 'CHAMDOR GRAPE', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(74, 'FOUR COUSINS WHITE 750mls', NULL, 320.00, 0.00, 'unit', 1, 1, 'item', 0),
(75, 'DROSTDY DRY RED 750mls', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(76, 'KWV CHENIN BLANC', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(77, 'WOLFTRAP ROSE', NULL, 400.00, 400.00, 'unit', 1, 1, 'item', 0),
(78, 'BONNE ESPIRANCE WHITE', NULL, 430.00, 0.00, 'unit', 1, 1, 'item', 0),
(79, 'DIEMERSDAL WINE', NULL, 480.00, 0.00, 'unit', 1, 1, 'item', 0),
(80, 'CHATEU_LIBERTAS 750mls', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(81, 'TALL HORSE CAB SAV', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(82, 'SPIRIT OF SALT 2.5 L', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(83, 'GINGER ALE', NULL, 20.00, 0.00, 'unit', 1, 1, 'item', 0),
(84, 'GINGER ALE 330ML', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(85, 'NEDERBURG PINOTAGE 750mls', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(86, 'SWARTLAND', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(87, 'JP CHENET PINK 750 ML', NULL, 600.00, 0.00, 'unit', 1, 1, 'item', 0),
(88, 'MOET CHAMPAGNE 750mls', NULL, 2000.00, 0.00, 'unit', 1, 1, 'item', 0),
(89, 'SAVANNAH LIME CORDIAL 2 LTRS', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(90, 'COSMO BROTHERS', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(91, 'TEE-POL 5L', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(92, 'RED BULL', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(93, 'SHERIDAN SHOOTER', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(94, 'KWV BRANDY 10 YRS 750 ML', NULL, 65.00, 0.00, 'unit', 1, 1, 'item', 0),
(95, 'JAMESON STOUT 750 ML', NULL, 75.00, 0.00, 'unit', 1, 1, 'item', 0),
(96, 'DROSTDY DRY WHITE 750mls', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(97, 'CRONIER MERLOT 750mls', NULL, 600.00, 0.00, 'unit', 1, 1, 'item', 0),
(98, 'BLACK LABEL WHISKY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(99, 'THICK BLEACH 750MLS', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(100, 'VICE LEMON DROP', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(101, 'SUN KISSED SWEET RED 750mls', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(102, 'BELGRAVIA CAN', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(103, 'PINA COLADA COCKTAIL', NULL, 170.00, 0.00, 'unit', 1, 1, 'item', 0),
(104, 'BROTHERS PIN COLADA', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(105, 'FLYING FISH BOTTLE', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(106, 'ROBERTSON WINE 5L', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(107, 'JP CHENET ICE WHITE', NULL, 430.00, 0.00, 'unit', 1, 1, 'item', 0),
(108, 'FURNITURE SPRAY 750MLS', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(109, 'BELVEDERE VODKA', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(110, 'FAT BASTARD GOLDEN 750mls', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(111, 'NANO PLUS 750MLS', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(112, 'GINGER ALE SCHWEPPES 330mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(113, 'GENTLEMAN JACK', NULL, 120.00, 0.00, 'unit', 1, 1, 'item', 0),
(114, 'TONIC WATER BROTHERS 200 ML', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(115, 'HUNTERS DRY', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(116, 'FOUR COUSINS SWEET RED 750mls', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(117, 'LYRIC NEDERBURG WHITE', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(118, 'HUNTERS GOLD CAN', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(119, 'CRONIER SHIRAZ 750mls', NULL, 600.00, 0.00, 'unit', 1, 1, 'item', 0),
(120, 'SAVANNA LIME 500mls', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(121, 'PRAVDA VODKA', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(122, 'SUN KISSED SWEET WHITE 750mls', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(123, 'SODA WATER BROTHERS 200 ML', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(124, 'INVERROCHE AMBER', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(125, 'KLIPDRIFT PRIMIUM 750 ML', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(126, 'SAVANNA DRY', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(127, 'AMARULA ETHIOPIAN', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(128, 'BUMBU GOLD', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(129, 'VATRA 500MLS', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(130, 'GINGER ALE BROTHERS 200 ML', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(131, 'BUTLERS PEPPER MINT', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(132, 'FAT BASTARD CABERNET 750mls', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(133, 'CHAPTAIN MORGAN SPICE GOLD', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(134, 'MINERAL VATRA WATER 750ML', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(135, 'BLUE CURACAO', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(136, 'COKE ZERO PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(137, 'DONJULIO', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(138, 'ROYAL FLUSH GIN', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(139, 'SINGLETON WHISKEY', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(140, 'ROYAL RHINO 750MLS', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(141, 'MOSI LAGER RGB 750mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(142, 'INVERROCHE VERDANY', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(143, 'HENNESSEY X.O', NULL, 450.00, 0.00, 'unit', 1, 1, 'item', 0),
(144, 'BOTTEGA GIN', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(145, 'TULLAMORE DEW 1L', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(146, 'GRANTS APERITIF SPIRIT', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(147, 'RED HEART', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(148, 'GRANTS 12yrs', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(149, 'ABSOLUTE VODKA RASBERRY', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(150, 'JACK DANIELS 750 ML', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(151, 'PONCHOS PREM TEQUILIO', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(152, 'CAPE SAINT BLAZE', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(153, 'CRUZ VODKA MANHATTAN', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(154, 'MAKERS MARK', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(155, 'CRUZ VODKA VINTAGE BLACK', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(156, 'BULLDOG GIN 750 ML', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(157, 'DRUNKEN HORSE', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(158, 'SKILPADTEPEL', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(159, 'PATRON TEQUILA', NULL, 250.00, 0.00, 'unit', 1, 1, 'item', 0),
(160, 'REMMY MARTINI XO', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(161, 'OUDE MASTER VSO', NULL, 630.00, 0.00, 'unit', 1, 1, 'item', 0),
(162, 'SAVANNA LIME 2L', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(163, 'CACTUS JACK TEQUILA', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(164, 'JAM JAR RED 750 ML', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(165, 'BOTEGA GOLD 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(166, 'CAPTAIN MORGAN DARK', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(167, 'BOYYEGA GIN BACUR 1L', NULL, 90.00, 0.00, 'unit', 1, 1, 'item', 0),
(168, 'TEQUILA 1800', NULL, 110.00, 0.00, 'unit', 1, 1, 'item', 0),
(169, 'JAM JAR WHITE 750mls', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(170, 'NSHIMA PORTION', NULL, 30.00, 0.00, 'unit', 2, 1, 'item', 0),
(171, 'TULLAMORE DEW 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(172, 'KWV BRANDY 5 YRS 750 ML', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(173, 'WINDHOEK LAGER', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(174, 'JAM JAR SWEET SHIRAZ 750 ML', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(175, 'GINOLOGIST GIN 750 ML', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(176, 'ROBERTSON NATURAL SWEET RED 750mls', NULL, 460.00, 0.00, 'unit', 1, 1, 'item', 0),
(177, 'BLACK LABEL LAGER RGB 750mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(178, 'LEMONADE 200 ML', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(179, 'CIROC SNAPFROST', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(180, 'REMY MARTIN V.O.S.P', NULL, 130.00, 0.00, 'unit', 1, 1, 'item', 0),
(181, 'KWV BRANDY 3 YRS 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(182, 'CASTLE LAGER RGB 750mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(183, 'CINZANO RED (ROSO)', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(184, 'BAILEYS 750 ML', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(185, 'SKY VODKA', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(186, 'CHIVAS LEGAL 15YRS', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(187, 'REMMY MARTINI VSOP', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(188, 'TEQUILA GOLD', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(189, 'JACK DANIELS HONEY 750mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(190, 'BELLS XTRA SPECIAL', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(191, 'JOHN WALKER 18 YRS', NULL, 180.00, 0.00, 'unit', 1, 1, 'item', 0),
(192, 'BLACK COFFEE', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(193, 'BUDWEISER', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(194, 'JAM TARTS', NULL, 50.00, 0.00, 'unit', 2, 1, 'item', 0),
(195, 'KAHLUA COFFEE', NULL, 75.00, 0.00, 'unit', 1, 1, 'item', 0),
(196, 'SPRITE RGB 300mls', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(197, 'BRUTAL FRUIT BOTTLE', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(198, 'CHIVAS 12 YEARS 750 ML', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(199, 'FRUITICANA JUICE', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(200, 'WINDHOEK DRAUGHT', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(201, 'JAMESON BLACK BARREL 750 ML', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(202, 'JAGERMASTER 1L', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(203, 'CIROC APPLE', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(204, 'BUMBU BLACK XO', NULL, 90.00, 0.00, 'unit', 1, 1, 'item', 0),
(205, 'TEQUILA SILVER 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(206, 'JACKSON BROWN 750mls', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(207, 'VICEROY 5yrs', NULL, 50.00, 2100.00, 'unit', 1, 1, 'item', 0),
(208, 'CIROC PINEAPPLE', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(209, 'JOHNNIE WALKER B/LABEL 750ML', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(210, 'FANTA CANNED', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(211, 'MINUTE MAID', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(212, 'JOHNNIE WALKER R/LABEL 750ML', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(213, 'HUNTERS GOLD', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(214, 'BUMBU SPIRIT APARITIF', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(215, 'JOHNNIE WALKER G/LABLE 750ML', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(216, 'ABSOLUTE VODKA', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(217, 'AQUA SAVANA 750ML', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(218, 'HEINIKEN NRB 340mls', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(219, 'SPRITE PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(220, 'COKE CANNED', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(221, 'FRUTICANA', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(222, 'BARCADI RUM', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(223, 'AMERICANO COFFEE', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(224, 'STELLA ARTOIS', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(225, 'CAPPUCCINO COFFEE', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(226, 'COFFEE LA TE', NULL, 65.00, 0.00, 'unit', 1, 1, 'item', 0),
(227, 'GLENFIDICH 15YRS', NULL, 140.00, 0.00, 'unit', 1, 1, 'item', 0),
(228, 'CASTLE LAGER RGB 375mls', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(229, 'BEEFEATER WHITE', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(230, 'FANTA ORANGE RGB 300mls', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(231, 'HENDRICKS', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(232, 'AMARULA 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(233, 'FANTA ORANGE PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(234, 'GLENLIVET 12 YEARS 750 ML', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(235, 'FLYING FISH CAN 500mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(236, 'GLENLIVET F/RESERVE', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(237, 'JAMESON IRISH 750 ML', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(238, 'J&B WHISKY 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(239, 'NEDERBURG BARRON 750mls', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(240, 'TANQUARAY GIN 750mls', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(241, 'CASTLE LITE NRB 340mls', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(242, 'MONKEY SHOULDER 750 ML', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(243, 'COFFEE CUPS', NULL, 10.00, 0.00, 'unit', 5, 1, 'item', 0),
(244, 'BRUTAL FRUIT CAN 500mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(245, 'VAT 69', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(246, 'GLENFIDDICH 18YRS', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(247, 'BOMBAY SAPHIRE 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(248, 'COKE RGB 300mls', NULL, 15.00, 0.00, 'unit', 1, 1, 'item', 0),
(249, 'KLIPDRIFT', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(250, 'CELLAR CASK NATURAL JUICY 750mls', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(251, 'BEEFEATER PINK', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(252, 'BALLANTINES', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(253, 'CAPTAIN MORGAN GOLD', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(254, 'COKE PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(255, 'BELLS WHISKEY', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(256, 'GLENFIDICH 12YRS', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(257, 'BLACK LABEL CAN 500MLS', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(258, 'CORONA EXTRA', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(259, 'ROBERTSON NATURAL DRY RED 3L', NULL, 70.00, 0.00, 'unit', 1, 1, 'item', 0),
(260, 'CASTLE LITE CAN 500mls', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(261, 'GRANTS TRIPPLE WOOD 750 ML', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(262, 'SOUTHERN COMFORT 750 ML', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(263, 'GRANTS WHISKY ORDINARY 750 ML', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(264, 'AQUA SAVANA 500ML', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(265, 'BLACK LABEL LAGER RGB 375mls', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(266, 'MOSI LAGER RGB 375mls', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(267, 'GORDONS DRY GIN', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(268, 'ABSOLUTE BLUE VODKA', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(269, 'ABSOLUTE VODKA WATERMELON', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(270, 'ACTIVE SPECIAL', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(271, 'AMARULA GIN', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(272, 'BELGRAVIA 10', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(273, 'BELGRAVIA 8 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(274, 'BELGRAVIA BLACK BERRY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(275, 'BELGRAVIA DRY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(276, 'BELGRAVIA STRAWBERRY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(277, 'BELVEDERE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(278, 'BLANCO 1800 SILVER', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(279, 'BOTEGA PINK 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(280, 'CINZANO WHITE (BIANCO)', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(281, 'COCO', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(282, 'CRONIER 3LIT', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(283, 'DROSTOF RED WINE 5 LITERS', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(284, 'FOUR COUSINS RED 5LITERS', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(285, 'GENOLOGIST BOTTLE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(286, 'GLENLIVET 18 YEARS 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(287, 'GRILL DE 20th', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(288, 'HIGHBURY WHISKY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(289, 'J&B WHISKY', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(290, 'JACK DANIELS GENTLEMAN JACK', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(291, 'JAGERMASTER 750mls', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(292, 'JAGERMESTER MANIFESTO', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(293, 'JOHNNIE WALKER D/BLACK 750ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(294, 'MALIBU', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(295, 'MARBORO BLUE', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(296, 'MARBORO GOLD', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(297, 'MARBORO RED', NULL, 0.00, 0.00, 'unit', 5, 1, 'item', 0),
(298, 'OLD BROWN', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(299, 'OVERMEER CASK RED 5 LITERS', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(300, 'OVERMEER WHITE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(301, 'RED SQUARE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(302, 'REMY MARTIN X.O', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(303, 'REPOSADO 1800 GOLD', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(304, 'ROBERTO VODKA', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(305, 'SALMON ROSETS', NULL, 0.00, 0.00, 'unit', 2, 1, 'item', 0),
(306, 'SMIRNOF 1818 VODKA', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(307, 'STRETTON DRY LONDON 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(308, 'STRETTON PINK GIN 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(309, 'TEQUILA CHOCOLATE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(310, 'THREE SPECIAL', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(311, 'WILD AFRICA 750 ML', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(312, 'WILD AFRICA CHOCOLATE', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(313, 'CINZANO', NULL, 0.00, 0.00, 'unit', 1, 1, 'item', 0),
(314, 'BUTTLERS EXPRESSO', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(315, 'BUTTLERS STRAWBERRY', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(316, 'COKE BOTTLE STAFF P', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(317, 'LIME', NULL, 10.00, 0.00, 'unit', 1, 1, 'item', 0),
(318, 'CRISPS', NULL, 15.00, 0.00, 'unit', 4, 1, 'item', 0),
(319, 'PASSION FRUIT 750ML', NULL, 20.00, 0.00, 'unit', 1, 1, 'item', 0),
(320, 'SMALL TEA', NULL, 20.00, 0.00, 'unit', 1, 1, 'item', 0),
(321, 'CREME SODA', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(322, 'GINGER BEER', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(323, 'SCHWEPPES LEMONADE', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(324, 'SPRITE CAN', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(325, 'TONIC WATER SCHWEPPES', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(326, 'FANTA PINE PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(327, 'FANTA GRAPE PET 500mls', NULL, 25.00, 0.00, 'unit', 1, 1, 'item', 0),
(328, 'MONJO SODA WATER', NULL, 26.00, 0.00, 'unit', 1, 1, 'item', 0),
(329, 'KALUA', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(330, 'SPECIAL BB', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(331, 'SPECIAL HD', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(332, 'SPECIAL HG', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(333, 'SPECIAL SAV', NULL, 30.00, 0.00, 'unit', 1, 1, 'item', 0),
(334, 'BLACK LABEL DAMPY', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(335, 'CELER CASK WINE RED 5 LITERS', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(336, 'MOSI LITE', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(337, 'TEA', NULL, 35.00, 0.00, 'unit', 1, 1, 'item', 0),
(338, 'AMSTEL LITE', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(339, 'ESPRESSO', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(340, 'INVEROCHE', NULL, 40.00, 0.00, 'unit', 1, 1, 'item', 0),
(341, 'AMSTEL', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(342, 'BENIN BOTTLE', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(343, 'BREEZERS', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(344, 'CARRIER BAG', NULL, 45.00, 0.00, 'unit', 5, 1, 'item', 0),
(345, 'GINOLOGIST DAMPY', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(346, 'HENIEKEN SILVER', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(347, 'SPECIAL BC', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(348, 'SPECIAL FFC', NULL, 45.00, 0.00, 'unit', 1, 1, 'item', 0),
(349, 'ABSOLUTE LIME', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(350, 'ABSOLUTE GRAPE FRUIT', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(351, 'FOUR COUSINS WHITE 5 LITERS', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(352, 'FRUIT TREE 5 LITERS', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(353, 'HEINEKEN CAN 500mls', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(354, 'HENIEKEN CAN', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(355, 'MALAWI SHANDY', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(356, 'MINTS FRIZ0', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(357, 'PINA COLADA MOCKTAIL', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(358, 'PURE JOY FRUIT JUICE 1LITER', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(359, 'SMAL. BLOW JOB', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(360, 'VIRGIN MOJITO', NULL, 50.00, 0.00, 'unit', 1, 1, 'item', 0),
(361, 'APPLETTIZER', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(362, 'BEELS', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(363, 'BENIN CANNED', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(364, 'CASTLE LAGER CAN', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(365, 'GINOLOGIST CANE', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(366, 'HEINEKEN SILVER CAN 500mls', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(367, 'HUNTERS DRY CAN', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(368, 'JUICE PACK 500MLS', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(369, 'MILK SHAKE', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(370, 'PRAVDA', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(371, 'PREDATOR', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(372, 'PURE JOY FRUIT JUICE 500ML', NULL, 60.00, 0.00, 'unit', 1, 1, 'item', 0),
(373, 'DUCK GRILL SHANDY', NULL, 65.00, 0.00, 'unit', 1, 1, 'item', 0),
(374, 'GINGER TEA', NULL, 65.00, 0.00, 'unit', 1, 1, 'item', 0),
(375, 'APPLE CRUMBE', NULL, 80.00, 0.00, 'unit', 4, 1, 'item', 0),
(376, 'CHERIDAN', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(377, 'HOT CHOCOLATE', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(378, 'MOCACCINO', NULL, 80.00, 0.00, 'unit', 1, 1, 'item', 0),
(379, 'PLAIN ICE CREAM', NULL, 80.00, 0.00, 'unit', 4, 1, 'item', 0),
(380, 'BLOW JOB', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(381, 'FOUR SPECIAL', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(382, 'JAGER BOMB', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(383, 'MAGARITA COCKTAIL', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(384, 'MATIN EXPRESSO', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(385, 'SPECIAL IS', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(386, 'SPRING BOK', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(387, 'WHITE RUSSIANS', NULL, 100.00, 0.00, 'unit', 1, 1, 'item', 0),
(388, 'CABANOSIS BIG', NULL, 120.00, 0.00, 'unit', 4, 1, 'item', 0),
(389, 'CAMEL', NULL, 140.00, 0.00, 'unit', 5, 1, 'item', 0),
(390, 'B AND H', NULL, 150.00, 0.00, 'unit', 5, 1, 'item', 0),
(391, 'BLUE', NULL, 150.00, 0.00, 'unit', 5, 1, 'item', 0),
(392, 'CAPPACCIO', NULL, 150.00, 0.00, 'unit', 2, 1, 'item', 0),
(393, 'COSMOPOLITAN', NULL, 150.00, 0.00, 'unit', 1, 1, 'item', 0),
(394, 'LONG ISLAND TEA', NULL, 150.00, 0.00, 'unit', 1, 1, 'item', 0),
(395, 'SEX ON THE BEACH', NULL, 150.00, 0.00, 'unit', 1, 1, 'item', 0),
(396, 'WRINGSON SPECIAL', NULL, 150.00, 0.00, 'unit', 1, 1, 'item', 0),
(397, 'DUCK GRILL COCKTAIL', NULL, 170.00, 0.00, 'unit', 1, 1, 'item', 0),
(398, 'DUNHILL', NULL, 170.00, 0.00, 'unit', 5, 1, 'item', 0),
(399, 'WINSTON TOBACCO', NULL, 170.00, 0.00, 'unit', 5, 1, 'item', 0),
(400, '4 COUSINS N S ROSE', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(401, 'COCTAIL JAR', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(402, 'ICE TROPEZ', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(403, 'ROYALTY SPARKLING', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(404, 'SPARKLING WINE', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(405, 'ST ANNA WHITE WINE 750 ML', NULL, 200.00, 0.00, 'unit', 1, 1, 'item', 0),
(406, 'NEDERBURG DUET', NULL, 210.00, 0.00, 'unit', 1, 1, 'item', 0),
(407, 'ROBERTSON C SAU', NULL, 240.00, 0.00, 'unit', 1, 1, 'item', 0),
(408, 'ROBERTSON MERLOT', NULL, 240.00, 0.00, 'unit', 1, 1, 'item', 0),
(409, 'ROBERTSON SHIRAZ', NULL, 240.00, 0.00, 'unit', 1, 1, 'item', 0),
(410, 'DUCK GRILL PITCHER', NULL, 250.00, 0.00, 'unit', 1, 1, 'item', 0),
(411, 'VINO ROSSO MELOT', NULL, 250.00, 0.00, 'unit', 1, 1, 'item', 0),
(412, 'EASY PLATTER', NULL, 280.00, 0.00, 'unit', 2, 1, 'item', 0),
(413, '4 COUSINS', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(414, 'CHAMDOR RED', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(415, 'CHAMDOR WHITE', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(416, 'KWV MERLOT', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(417, 'KWV SHIRAZ', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(418, 'PAERLY BAY ROSE', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(419, 'ROBERTSON RED 750 ML', NULL, 300.00, 0.00, 'unit', 1, 1, 'item', 0),
(420, 'ROBERTSON ROSE', NULL, 310.00, 0.00, 'unit', 1, 1, 'item', 0),
(421, 'KWV CARDONNEY 750 ML', NULL, 320.00, 0.00, 'unit', 1, 1, 'item', 0),
(422, 'NEDERBURG CHARDONAY 750ML', NULL, 320.00, 0.00, 'unit', 1, 1, 'item', 0),
(423, 'PROTEA CARB SAUV 750 ML', NULL, 320.00, 0.00, 'unit', 1, 1, 'item', 0),
(424, 'BONNE ESPIRANCE RED', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(425, 'CHEATEAU LIBERTAS 750 ML', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(426, 'FAT BASTARD SAU BLANC', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(427, 'JC LE ROUX 750 ML DOMENI', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(428, 'ROBERTSON SWEET WHITE', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(429, 'SAINT CELINE', NULL, 350.00, 0.00, 'unit', 1, 1, 'item', 0),
(430, 'FAT BASTARD 750 ML', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(431, 'SAINT ANNA', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(432, 'SAINT CLAIRE', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(433, 'SAINT RAPHAEL', NULL, 380.00, 0.00, 'unit', 1, 1, 'item', 0),
(434, '4TH STREET SWEET WINE', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(435, 'FAT BASTARD C BS', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(436, 'FAT BASTARD MERLOT', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(437, 'FAT BASTARD PINOTAGE', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(438, 'FAT BASTARD SHIRAZ', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(439, 'FOUR COUSINS ROSEV', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(440, 'MERLOT', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(441, 'NEDERBURG MELOT 750 ML', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(442, 'SERAH CREEK', NULL, 400.00, 0.00, 'unit', 1, 1, 'item', 0),
(443, 'BABIES ROSE', NULL, 450.00, 0.00, 'unit', 1, 1, 'item', 0),
(444, 'ROBERTSON SWEET RED', NULL, 460.00, 0.00, 'unit', 1, 1, 'item', 0),
(445, 'ALITA ROSE 750 ML', NULL, 470.00, 0.00, 'unit', 1, 1, 'item', 0),
(446, 'BLANC DE BLANC', NULL, 470.00, 0.00, 'unit', 1, 1, 'item', 0),
(447, 'ALVIS DRIFT', NULL, 480.00, 0.00, 'unit', 1, 1, 'item', 0),
(448, 'JC LE ROUX 750 ML NECTER', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(449, 'NEDERBURG CAB SAV 750 ML', NULL, 500.00, 0.00, 'unit', 1, 1, 'item', 0),
(450, 'KRONE', NULL, 600.00, 0.00, 'unit', 1, 1, 'item', 0),
(451, 'RUPERT ROTHSCHILD', NULL, 650.00, 0.00, 'unit', 1, 1, 'item', 0),
(452, 'CLEN CARLOU MEROT', NULL, 700.00, 0.00, 'unit', 1, 1, 'item', 0),
(453, 'BABEL RED', NULL, 800.00, 0.00, 'unit', 1, 1, 'item', 0),
(454, 'CATHEDRAL CELLAR', NULL, 900.00, 0.00, 'unit', 1, 1, 'item', 0),
(455, 'MEERLUST RED 750 ML', NULL, 900.00, 0.00, 'unit', 1, 1, 'item', 0);

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

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('license_tier', 'lite');

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
(10, 1, 1, '2026-02-20 05:09:07', '2026-02-20 06:20:16', 0.00, 0.00, 0.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00),
(11, 1, 1, '2026-02-20 06:20:55', NULL, 500.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(12, 10, 1, '2026-02-23 16:18:11', '2026-02-23 18:04:41', 0.00, 0.00, 0.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00),
(13, 2, 1, '2026-02-23 16:43:38', NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(14, 8, 3, '2026-02-23 16:44:17', NULL, 0.00, 0.00, 0.00, 0.00, 'open', NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(15, 10, 2, '2026-02-24 08:08:18', '2026-02-24 08:11:15', 0.00, 0.00, 0.00, 0.00, 'closed', '', NULL, NULL, NULL, NULL, NULL, 0.00);

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
(8, 'chef', 'chef', '$2y$10$M9vANzbl70OlafoGFIn/b.0Kyhl6JuQi21i5d6tjcBWnV8RxtjOZ2', 'cashier', 3, '2026-02-10 08:21:50', 0, 1),
(10, 'daliso', 'Daliso Nindi', '$2y$10$0Cmqbbba.ipH/7x1fh9QOuQ8z4FayAfR1TuvXhzShncyprrq27Z4.', 'admin', 4, '2026-02-10 10:20:55', 0, 1);

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
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `grv_items`
--
ALTER TABLE `grv_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=456;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
