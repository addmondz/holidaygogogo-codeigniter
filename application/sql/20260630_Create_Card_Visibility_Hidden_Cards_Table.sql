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
