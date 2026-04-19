CREATE TABLE IF NOT EXISTS `ghl_processing_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `processor_name` VARCHAR(120) NOT NULL,
  `last_processed_at` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
  `last_processed_message_row_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_processor_name` (`processor_name`),
  KEY `idx_last_processed_at` (`last_processed_at`, `last_processed_message_row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
