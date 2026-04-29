CREATE TABLE `quick_filter` (
  `QuickFilterID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(100) NOT NULL,
  `FilterData` TEXT NOT NULL,
  `Status` ENUM('Y','N') DEFAULT 'Y',
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_quick_filter_name` (`Name`)
);
