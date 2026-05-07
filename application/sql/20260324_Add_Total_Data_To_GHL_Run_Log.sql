-- =============================================
-- GHL_SYNC_RUN_LOG - ADD SYNC PROGRESS COLUMNS
-- =============================================
-- Idempotent migration: works on MySQL < 8.0.29 (no ADD COLUMN IF NOT EXISTS)
-- by checking INFORMATION_SCHEMA before each ALTER.

-- total_data
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = 'ghl_sync_run_log'
         AND COLUMN_NAME  = 'total_data') = 0,
    'ALTER TABLE `ghl_sync_run_log` ADD COLUMN `total_data` INT NOT NULL DEFAULT 0 AFTER `total_page`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- full_sync
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = 'ghl_sync_run_log'
         AND COLUMN_NAME  = 'full_sync') = 0,
    'ALTER TABLE `ghl_sync_run_log` ADD COLUMN `full_sync` TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_data`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = 'ghl_sync_run_log'
         AND COLUMN_NAME  = 'status') = 0,
    "ALTER TABLE `ghl_sync_run_log` ADD COLUMN `status` ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending' AFTER `full_sync`",
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- started_at
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = 'ghl_sync_run_log'
         AND COLUMN_NAME  = 'started_at') = 0,
    'ALTER TABLE `ghl_sync_run_log` ADD COLUMN `started_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `status`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- completed_at
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME   = 'ghl_sync_run_log'
         AND COLUMN_NAME  = 'completed_at') = 0,
    'ALTER TABLE `ghl_sync_run_log` ADD COLUMN `completed_at` DATETIME NULL DEFAULT NULL AFTER `started_at`',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
