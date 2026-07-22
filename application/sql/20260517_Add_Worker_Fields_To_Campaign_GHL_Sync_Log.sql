ALTER TABLE `campaign_ghl_sync_log` ADD COLUMN `CurrentOffset` INT(11) NULL AFTER `FailedCount`;

ALTER TABLE `campaign_ghl_sync_log` ADD COLUMN `TotalGuests` INT(11) NULL AFTER `CurrentOffset`;

ALTER TABLE `campaign_ghl_sync_log` ADD COLUMN `ClaimedAt` DATETIME NULL AFTER `TotalGuests`;

ALTER TABLE `campaign_ghl_sync_log` ADD INDEX `idx_cgsl_action_status_claim` (`Action`, `Status`, `ClaimedAt`);
