-- Adds the OP TEAM LEAD role (Level 45) and a dedicated OP-team mapping.
--
-- Level 45 = OP TEAM LEAD: a senior operations lead who may tick the booking
-- checklist for their own bookings and for bookings handled by their OP team
-- members. Team membership is kept SEPARATE from the sales-side admin.TeamLeadID
-- via the new OpTeamLeadID column (each OP points at their OP team lead).

ALTER TABLE `admin` MODIFY COLUMN `Level` ENUM('10','20','25','30','40','45','50') NOT NULL;

ALTER TABLE `admin` ADD COLUMN `OpTeamLeadID` INT NULL DEFAULT NULL AFTER `TeamLeadID`;
