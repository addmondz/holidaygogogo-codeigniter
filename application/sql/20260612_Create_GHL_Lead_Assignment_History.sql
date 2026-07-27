CREATE TABLE IF NOT EXISTS `ghl_lead_assignment_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` VARCHAR(100) NOT NULL,
  `processed_lead_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `owner_user_id` VARCHAR(100) NOT NULL,
  `assigned_at` DATETIME NOT NULL,
  `unassigned_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner_assigned_at` (`owner_user_id`, `assigned_at`),
  KEY `idx_conversation_assigned_at` (`conversation_id`, `assigned_at`),
  KEY `idx_conversation_owner_open` (`conversation_id`, `owner_user_id`, `unassigned_at`),
  KEY `idx_processed_lead_id` (`processed_lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ghl_lead_assignment_history` (`conversation_id`, `owner_user_id`, `assigned_at`)
SELECT
  gc.`conversation_id`,
  gc.`assigned_to`,
  COALESCE(gc.`date_updated`, gc.`date_added`, NOW()) AS `assigned_at`
FROM `ghl_conversations` gc
LEFT JOIN `ghl_lead_assignment_history` glah
  ON glah.`conversation_id` = gc.`conversation_id`
 AND glah.`owner_user_id` = gc.`assigned_to`
 AND glah.`unassigned_at` IS NULL
WHERE gc.`assigned_to` IS NOT NULL
  AND gc.`assigned_to` <> ''
  AND glah.`id` IS NULL;

ALTER TABLE `ghl_lead_ownership`
  ADD COLUMN `assigned_at` DATETIME NULL DEFAULT NULL AFTER `assigned_to_user_id`,
  ADD KEY `idx_owner_assigned_at` (`owner_user_id`, `assigned_at`);
