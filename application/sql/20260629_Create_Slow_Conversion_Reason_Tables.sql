-- Master list of selectable "why was this booking slow to convert" reasons,
-- managed under Settings (mirrors cancellation_reason).
CREATE TABLE IF NOT EXISTS `slow_conversion_reason` (
  `SlowConversionReasonID` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(255) NOT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  `UpdateBy` INT(11) DEFAULT NULL,
  `UpdateDate` DATETIME DEFAULT NULL,
  PRIMARY KEY (`SlowConversionReasonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Junction: which reasons a booking was tagged with (many-to-many).
CREATE TABLE IF NOT EXISTS `booking_slow_conversion_reason` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `BookingID` INT(11) NOT NULL,
  `SlowConversionReasonID` INT(11) NOT NULL,
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uniq_booking_reason` (`BookingID`, `SlowConversionReasonID`),
  KEY `idx_booking` (`BookingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
