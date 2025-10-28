CREATE TABLE IF NOT EXISTS `customer` (
  `CustomerID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `CustomerCode` VARCHAR(50) DEFAULT NULL COMMENT 'Debtor code',
  `name` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(255) DEFAULT NULL,
  `ChatLanguage` VARCHAR(50) DEFAULT NULL,
  `AutocountSyncAction` CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status',
  `AutocountSyncStatus` CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed',
  `AutocountSyncMessage` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CustomerID`),
  INDEX `idx_customercode` (`CustomerCode`),
  INDEX `idx_name` (`name`),
  INDEX `idx_phone_number` (`phone_number`),
  INDEX `idx_autocountsyncaction` (`autocountsyncaction`),
  INDEX `idx_autocountsyncstatus` (`autocountsyncstatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;