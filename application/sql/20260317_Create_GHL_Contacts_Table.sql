-- GHL (GoHighLevel) contacts sync table
-- Stores incrementally synced contacts fetched from GET https://services.leadconnectorhq.com/contacts/

CREATE TABLE IF NOT EXISTS `ghl_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` VARCHAR(100) NOT NULL COMMENT 'GHL contact id',
  `first_name` VARCHAR(255) NULL DEFAULT NULL,
  `last_name` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(50) NULL DEFAULT NULL,
  `assigned_to` VARCHAR(100) NULL DEFAULT NULL COMMENT 'GHL user id',
  `date_added` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_contact_id` (`contact_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_date_added` (`date_added`),
  KEY `idx_email` (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
