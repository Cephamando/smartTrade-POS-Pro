USE `smartTradePOS_db`;

-- 1. Ensure settings table can hold arbitrary text configuration
ALTER TABLE `settings` MODIFY `setting_value` TEXT NOT NULL;

-- 2. Pre-seed settings with VSDC parameters
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('vsdc_sdcId', ''),
('vsdc_mrcNo', ''),
('vsdc_taxprNm', ''),
('vsdc_last_sale_no', '0'),
('vsdc_last_invc_no', '0');

-- [COMMENTED OUT] Add Sync tracking to products - Column already exists!
ALTER TABLE `products`
ADD COLUMN `zra_synced` TINYINT(1) DEFAULT 0 COMMENT '1 if synced to ZRA';

-- 3. Enhance sales table with ZRA fiscal response attributes
ALTER TABLE `sales`
ADD COLUMN `zra_rcpt_no` INT DEFAULT NULL,
ADD COLUMN `zra_intrl_data` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `zra_rcpt_sign` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `zra_vsdc_rcpt_pbct_date` VARCHAR(20) DEFAULT NULL,
ADD COLUMN `zra_sdc_id` VARCHAR(50) DEFAULT NULL,
ADD COLUMN `zra_mrc_no` VARCHAR(50) DEFAULT NULL,
ADD COLUMN `zra_qr_code_url` TEXT DEFAULT NULL;

-- Add ZRA tracking to goods received (for Stock Master sync)
ALTER TABLE `grvs`
ADD COLUMN `zra_synced` TINYINT(1) DEFAULT 0 COMMENT '1 if pushed to ZRA stock';

-- 4. Create standard code lookup table for classifications/taxes
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