CREATE TABLE IF NOT EXISTS `ghl_sync_run_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_name` VARCHAR(120) NOT NULL,
  `RunID` VARCHAR(120) NOT NULL,
  `total_page` INT NOT NULL DEFAULT 0,
  `total_data` INT NOT NULL DEFAULT 0,
  `full_sync` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `pulled_count` INT NOT NULL DEFAULT 0,
  `updated_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_runid` (`RunID`),
  KEY `idx_module_name_created_at` (`module_name`, `created_at`),
  KEY `idx_runid_created_at` (`RunID`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
