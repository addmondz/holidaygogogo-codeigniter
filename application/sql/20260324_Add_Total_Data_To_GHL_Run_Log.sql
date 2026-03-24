ALTER TABLE `ghl_sync_run_log`
ADD COLUMN IF NOT EXISTS `total_data` INT NOT NULL DEFAULT 0 AFTER `total_page`,
ADD COLUMN IF NOT EXISTS `full_sync` TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_data`,
ADD COLUMN IF NOT EXISTS `status` ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending' AFTER `full_sync`,
ADD COLUMN IF NOT EXISTS `started_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`,
ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL DEFAULT NULL AFTER `started_at`;
