CREATE TABLE `lc_module_access` (
  `LcAccessID` INT AUTO_INCREMENT PRIMARY KEY,
  `AdminID` INT NOT NULL,
  `Module` ENUM('customer','guests','ghl_leads','manual_leads') NOT NULL,
  `CanView` TINYINT(1) NOT NULL DEFAULT 0,
  `CanEdit` TINYINT(1) NOT NULL DEFAULT 0,
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_lc_admin_module` (`AdminID`,`Module`)
);
