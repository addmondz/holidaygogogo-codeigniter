-- Lead Status presets, managed under Settings (Owner L10 + Team Lead L25). Manual
-- leads store the chosen status by NAME in ghl_contacts.lead_status. Mirrors the
-- customer_type table (Universal_Model::Validate_Id relies on the Status column).
CREATE TABLE `lead_status` (
  `LeadStatusID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(50) NOT NULL,
  `Status` ENUM('Y','N') DEFAULT 'Y',
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_lead_status_name` (`Name`)
);
