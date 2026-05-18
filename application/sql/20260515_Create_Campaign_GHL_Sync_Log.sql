-- Audit log for "Sync Campaign to GHL" runs. One run = one run_summary row
-- plus one per-guest row recording the action taken (matched / created /
-- tag_applied / skipped / failed).

CREATE TABLE IF NOT EXISTS `campaign_ghl_sync_log` (
  `ID`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `CampaignID`   INT(11) UNSIGNED NOT NULL,
  `RunID`        VARCHAR(64) NOT NULL,
  `DedupKey`     VARCHAR(255) NULL,
  `GhlContactID` VARCHAR(64) NULL,
  `Action`       ENUM('matched','created','tag_applied','skipped','failed','run_summary') NOT NULL,
  `Message`      VARCHAR(512) NULL,
  `HttpStatus`   INT(11) NULL,
  `Status`       ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  `MatchedCount` INT(11) NOT NULL DEFAULT 0,
  `CreatedCount` INT(11) NOT NULL DEFAULT 0,
  `TaggedCount`  INT(11) NOT NULL DEFAULT 0,
  `FailedCount`  INT(11) NOT NULL DEFAULT 0,
  `InsertBy`     INT(11) NULL,
  `InsertDate`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `CompletedAt`  DATETIME NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_cgsl_campaign` (`CampaignID`, `InsertDate`),
  KEY `idx_cgsl_run`      (`RunID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
