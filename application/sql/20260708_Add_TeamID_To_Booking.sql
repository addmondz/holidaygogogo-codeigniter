ALTER TABLE `booking` ADD COLUMN `TeamID` INT NULL DEFAULT NULL AFTER `SalesAgent`;

CREATE INDEX `idx_booking_team_id` ON `booking` (`TeamID`);

UPDATE `booking` b
JOIN `admin` a ON a.`AdminID` = b.`SalesAgent`
SET b.`TeamID` = a.`TeamID`
WHERE b.`SalesAgent` IS NOT NULL
  AND b.`SalesAgent` > 0
  AND a.`TeamID` IS NOT NULL;
