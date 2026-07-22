-- Per-admin Lead Dashboard agent restriction.
-- Each row authorizes (AdminID) to view leads assigned to (GhlUserID) on
-- Report/Lead_Dashboard and Report/Lead_Data. Empty set => no leads visible.
-- OWNER (admin.Level = '10') bypasses this restriction in app code.

CREATE TABLE IF NOT EXISTS `admin_lead_dashboard_agents` (
  `AdminID`    INT          NOT NULL,
  `GhlUserID`  VARCHAR(100) NOT NULL,
  `InsertBy`   INT          NULL DEFAULT NULL,
  `InsertDate` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminID`, `GhlUserID`),
  KEY `idx_alda_ghl_user` (`GhlUserID`),
  CONSTRAINT `fk_alda_admin` FOREIGN KEY (`AdminID`)
    REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
