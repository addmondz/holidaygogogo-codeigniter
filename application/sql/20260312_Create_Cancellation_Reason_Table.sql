CREATE TABLE IF NOT EXISTS `cancellation_reason` (
  `CancellationReasonID` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(255) NOT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  `UpdateBy` INT(11) DEFAULT NULL,
  `UpdateDate` DATETIME DEFAULT NULL,
  PRIMARY KEY (`CancellationReasonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
