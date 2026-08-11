-- Campaign visibility: PER-CAMPAIGN opt-out of the audience picker. A row means
-- "when editing this CampaignID, hide this person (DedupKey) from the guest
-- picker search results". DedupKey is the same phone-last-9 -> ghl/row fallback
-- key used by campaign_guests, so hiding one Customer row hides that same person
-- everywhere they surface in that campaign's picker (booking guest / customer /
-- GHL). Composite key = one hide per (campaign, person).
CREATE TABLE IF NOT EXISTS `campaign_hidden_guests` (
  `CampaignID` INT(11) UNSIGNED NOT NULL,
  `DedupKey`   VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `HiddenBy`   INT(11) NULL,
  `HiddenAt`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CampaignID`, `DedupKey`),
  KEY `idx_chg_dedup` (`DedupKey`),
  CONSTRAINT `fk_chg_campaign`
    FOREIGN KEY (`CampaignID`) REFERENCES `campaign`(`CampaignID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
