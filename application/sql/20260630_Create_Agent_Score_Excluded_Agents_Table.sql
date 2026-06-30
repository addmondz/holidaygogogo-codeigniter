-- Agent Score exclusion list.
-- Each row marks a sales agent (admin.AdminID, Level 20/50) as EXCLUDED from the
-- Agent Score: they drop out of the TC "Agent Score" Top-5 leaderboard and its
-- 100-benchmark anchors, and out of the Agent Score column on the owner matrix.
-- Presence of a row => excluded. Managed by OWNER (admin.Level = '10') on the
-- Agent Score Settings page (controller: Agent_Score_Setting).

CREATE TABLE IF NOT EXISTS `agent_score_excluded_agents` (
  `AdminID`    INT      NOT NULL,
  `InsertBy`   INT      NULL DEFAULT NULL,
  `InsertDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AdminID`),
  CONSTRAINT `fk_asea_admin` FOREIGN KEY (`AdminID`)
    REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
