CREATE TABLE IF NOT EXISTS `ghl_sync_run_log` (
  `LogID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `RunID` VARCHAR(64) NOT NULL,
  `SyncKey` VARCHAR(120) NOT NULL,
  `LogType` VARCHAR(40) NOT NULL,
  `Message` TEXT NULL,
  `Page` INT NULL DEFAULT NULL,
  `PulledCount` INT NOT NULL DEFAULT 0,
  `InsertedCount` INT NOT NULL DEFAULT 0,
  `HttpStatus` INT NULL DEFAULT NULL,
  `Meta` LONGTEXT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `idx_runid_created` (`RunID`, `CreatedAt`),
  KEY `idx_logtype_created` (`LogType`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;