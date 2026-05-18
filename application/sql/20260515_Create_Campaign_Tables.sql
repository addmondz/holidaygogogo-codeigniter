-- Campaign module: a curated group of guests (sourced from the merged
-- guest_list + ghl_contacts view) used for marketing / follow-up batches.

CREATE TABLE IF NOT EXISTS `campaign` (
  `CampaignID`   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`         VARCHAR(255) NOT NULL,
  `CampaignDate` DATE NULL,
  `Description`  TEXT NULL,
  `Status`       ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `InsertBy`     INT(11) NULL,
  `InsertDate`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdateBy`     INT(11) NULL,
  `UpdateDate`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CampaignID`),
  KEY `idx_campaign_date` (`CampaignDate`),
  KEY `idx_campaign_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot. DedupKey is the same expression used by Guests_Model to merge
-- booking guests with GHL contacts (phone last-9 -> email -> row fallback).
-- Snapshot the display fields so the campaign roster stays readable even
-- if the upstream guest record changes or is removed.
CREATE TABLE IF NOT EXISTS `campaign_guests` (
  `CampaignID` INT(11) UNSIGNED NOT NULL,
  `DedupKey`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `GuestName`  VARCHAR(255) NULL,
  `ContactNum` VARCHAR(50) NULL,
  `Email`      VARCHAR(255) NULL,
  `GuestType`  ENUM('Booking Guest','GHL') NULL,
  `InsertBy`   INT(11) NULL,
  `InsertDate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CampaignID`, `DedupKey`),
  KEY `idx_cg_dedup` (`DedupKey`),
  CONSTRAINT `fk_cg_campaign`
    FOREIGN KEY (`CampaignID`) REFERENCES `campaign`(`CampaignID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
