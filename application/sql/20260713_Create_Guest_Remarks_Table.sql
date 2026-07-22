CREATE TABLE IF NOT EXISTS `guest_remarks` (
  `RemarkID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dedup_key` VARCHAR(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `RemarkAt` DATETIME NOT NULL,
  `Remark` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `Status` CHAR(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `CreatedBy` INT NULL DEFAULT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`RemarkID`),
  KEY `idx_dedup_key_status` (`dedup_key`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
