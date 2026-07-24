ALTER TABLE `ghl_contacts`
  ADD COLUMN `lead_source`   VARCHAR(20)  NOT NULL DEFAULT 'ghl' COMMENT 'ghl (API-synced) | manual (hand-entered)' AFTER `assigned_to`,
  ADD COLUMN `gender`        VARCHAR(20)  NULL DEFAULT NULL AFTER `lead_source`,
  ADD COLUMN `race`          VARCHAR(100) NULL DEFAULT NULL AFTER `gender`,
  ADD COLUMN `nationality`   VARCHAR(100) NULL DEFAULT NULL AFTER `race`,
  ADD COLUMN `chat_language` VARCHAR(100) NULL DEFAULT NULL AFTER `nationality`,
  ADD COLUMN `date_of_birth` DATE         NULL DEFAULT NULL AFTER `chat_language`,
  ADD COLUMN `tags_json`     TEXT         NULL DEFAULT NULL AFTER `date_of_birth`,
  ADD INDEX `idx_ghl_contacts_lead_source` (`lead_source`);
