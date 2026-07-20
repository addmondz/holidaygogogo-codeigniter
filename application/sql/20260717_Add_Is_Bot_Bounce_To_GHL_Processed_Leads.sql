ALTER TABLE `ghl_processed_leads`
  ADD COLUMN `is_bot_bounce` TINYINT(1) NOT NULL DEFAULT 0 AFTER `converted_at`,
  ADD KEY `idx_is_bot_bounce` (`is_bot_bounce`);

ALTER TABLE `ghl_lead_ownership`
  ADD COLUMN `is_bot_bounce` TINYINT(1) NOT NULL DEFAULT 0 AFTER `converted_at`,
  ADD KEY `idx_is_bot_bounce` (`is_bot_bounce`);
