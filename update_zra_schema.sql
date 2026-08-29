USE `smartTradePOS_db`;

-- Additive ZRA columns. The live app also applies these automatically via ZraVsdcClient.
-- Run manually only if you want to prepare the database before the first page load.

ALTER TABLE `settings` MODIFY `setting_value` TEXT NOT NULL;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('vsdc_sdcId', ''),
('vsdc_mrcNo', ''),
('vsdc_taxprNm', ''),
('vsdc_bhfNm', ''),
('vsdc_last_sale_no', '0'),
('vsdc_last_invc_no', '0'),
('vsdc_last_sar_no', '0'),
('vsdc_default_unspsc', '50101500');

CREATE TABLE IF NOT EXISTS `zra_standard_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code_class` VARCHAR(10) NOT NULL,
  `code_class_name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `code_name` VARCHAR(100) NOT NULL,
  `user_defined_name` VARCHAR(100) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_code` (`code_class`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `zra_item_classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_cls_cd` VARCHAR(10) NOT NULL,
  `item_cls_nm` VARCHAR(200) NOT NULL,
  `item_cls_lvl` INT DEFAULT 0,
  `tax_ty_cd` VARCHAR(10) DEFAULT NULL,
  `use_yn` CHAR(1) DEFAULT 'Y',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_cls` (`item_cls_cd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `zra_sync_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` VARCHAR(20) NOT NULL,
  `entity_id` INT NOT NULL,
  `result_cd` VARCHAR(10) DEFAULT NULL,
  `result_msg` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
