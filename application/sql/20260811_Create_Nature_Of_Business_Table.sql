-- Nature of Business presets, managed under the Leads/Customer tab (owner-granted
-- per page, like Lead Status). Manual leads store the chosen nature by NAME in
-- ghl_contacts.nature_of_business. Mirrors the lead_status table (Universal_Model::
-- Validate_Id relies on the Status column).
CREATE TABLE `nature_of_business` (
  `NatureOfBusinessID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(150) NOT NULL,
  `Status` ENUM('Y','N') DEFAULT 'Y',
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_nature_of_business_name` (`Name`)
);
