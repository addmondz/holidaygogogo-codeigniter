-- GHL (GoHighLevel) Users sync table
-- Stores users fetched from GET https://services.leadconnectorhq.com/users/?locationId=...

CREATE TABLE IF NOT EXISTS `ghl_users` (
  `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `RunID` VARCHAR(64) NOT NULL,
  `UserID` VARCHAR(100) NOT NULL COMMENT 'GHL user id',
  `LocationID` VARCHAR(100) NOT NULL,
  `Name` VARCHAR(255) NULL DEFAULT NULL,
  `FirstName` VARCHAR(255) NULL DEFAULT NULL,
  `LastName` VARCHAR(255) NULL DEFAULT NULL,
  `Email` VARCHAR(255) NULL DEFAULT NULL,
  `Phone` VARCHAR(50) NULL DEFAULT NULL,
  `Extension` VARCHAR(50) NULL DEFAULT NULL,
  `Deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `RoleType` VARCHAR(50) NULL DEFAULT NULL COMMENT 'e.g. account, agency',
  `RoleName` VARCHAR(50) NULL DEFAULT NULL COMMENT 'e.g. admin, user',
  `RestrictSubAccount` TINYINT(1) NOT NULL DEFAULT 0,
  `LocationIds` LONGTEXT NULL COMMENT 'JSON array of location IDs',
  `LcPhone` LONGTEXT NULL COMMENT 'JSON object',
  `ProfilePhoto` VARCHAR(512) NULL DEFAULT NULL,
  `InvitedForMobileApp` TINYINT(1) NOT NULL DEFAULT 0,
  `FreshdeskContactId` VARCHAR(100) NULL DEFAULT NULL,
  `MembershipContactId` VARCHAR(100) NULL DEFAULT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_ghl_users_location_user` (`LocationID`, `UserID`),
  KEY `idx_location_id` (`LocationID`),
  KEY `idx_user_id` (`UserID`),
  KEY `idx_run_id` (`RunID`),
  KEY `idx_email` (`Email`(191)),
  KEY `idx_deleted` (`Deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
