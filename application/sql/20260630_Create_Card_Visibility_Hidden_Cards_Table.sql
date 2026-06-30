-- Per-(user x card) visibility blocklist.
-- Each row HIDES one summary card (CardSlug, from card_visibility_helper's
-- registry) from one non-owner user (admin.AdminID). Cards are VISIBLE BY
-- DEFAULT to their normal role; presence of a row => that user does NOT see that
-- card. Managed by OWNER (admin.Level = '10') on the Card Visibility Settings
-- page (controller: Card_Visibility_Setting); read by Booking::ajax_summary_cards
-- (strips the card's data) and booking/_summary_cards (hides the card's column).

CREATE TABLE IF NOT EXISTS `card_visibility_hidden_cards` (
  `AdminID`    INT          NOT NULL,
  `CardSlug`   VARCHAR(64)  NOT NULL,
  `InsertBy`   INT          NULL DEFAULT NULL,
  `InsertDate` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminID`, `CardSlug`),
  CONSTRAINT `fk_cvhc_admin` FOREIGN KEY (`AdminID`)
    REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supersedes the earlier all-cards allowlist table.
DROP TABLE IF EXISTS `card_visibility_allowed_agents`;
