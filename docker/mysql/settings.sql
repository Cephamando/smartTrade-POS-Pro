-- Adminer 5.5.1 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

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
('vsdc_last_class_dt',	'20250729120234'),
('vsdc_last_code_dt',	'20260913172454'),
('vsdc_last_invc_no',	'0'),
('vsdc_last_sale_no',	'0'),
('vsdc_last_sar_no',	'0'),
('vsdc_mrcNo',	'WIS00004268  '),
('vsdc_sdcId',	'SDC0060001122'),
('vsdc_taxprNm',	'MANDO NGOMA CHISHIMBA');

-- 2026-09-13 17:28:54 UTC
