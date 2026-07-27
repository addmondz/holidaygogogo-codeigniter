ALTER TABLE `ghl_processed_leads`
  ADD COLUMN `follow_up_status` ENUM('pending', 'sent', 'completed', 'expired') NOT NULL DEFAULT 'pending' AFTER `avg_recent_5_response_seconds`,
  ADD COLUMN `follow_up_sent_at` DATETIME NULL DEFAULT NULL AFTER `follow_up_status`,
  ADD COLUMN `follow_up_replied_at` DATETIME NULL DEFAULT NULL AFTER `follow_up_sent_at`,
  ADD COLUMN `follow_up_expired_at` DATETIME NULL DEFAULT NULL AFTER `follow_up_replied_at`,
  ADD KEY `idx_follow_up_status` (`follow_up_status`, `follow_up_sent_at`);

ALTER TABLE `ghl_lead_ownership`
  ADD COLUMN `follow_up_status` ENUM('pending', 'sent', 'completed', 'expired') NOT NULL DEFAULT 'pending' AFTER `avg_recent_5_response_seconds`,
  ADD KEY `idx_follow_up_status` (`follow_up_status`);
