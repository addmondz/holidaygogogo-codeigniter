CREATE TABLE IF NOT EXISTS `customer` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(50) DEFAULT NULL COMMENT 'Debtor code',
  `name` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(255) DEFAULT NULL,
  `autocountsyncaction` CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status',
  `autocountsyncstatus` CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed',
  `autocountmessage` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_customer_code` (`customer_code`),
  INDEX `idx_name` (`name`),
  INDEX `idx_autocountsyncaction` (`autocountsyncaction`),
  INDEX `idx_autocountsyncstatus` (`autocountsyncstatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;