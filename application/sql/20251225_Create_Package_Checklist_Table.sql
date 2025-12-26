-- Create package_checklist table
CREATE TABLE IF NOT EXISTS `package_checklist` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_required` TINYINT(1) DEFAULT 0 COMMENT '1 = required, 0 = not required',
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  `UpdateBy` INT(11) DEFAULT NULL,
  `UpdateDate` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial data
INSERT INTO `package_checklist` (`name`, `is_required`) VALUES ('Payment Out To Supplier', 1);

