CREATE TABLE IF NOT EXISTS `booking_customer_type` (
  `BookingCustomerTypeID` INT AUTO_INCREMENT PRIMARY KEY,
  `BookingID` INT NOT NULL,
  `CustomerTypeID` INT NOT NULL,
  `InsertDate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_booking_customer_type` (`BookingID`, `CustomerTypeID`),
  INDEX `idx_bct_booking` (`BookingID`),
  INDEX `idx_bct_customer_type` (`CustomerTypeID`)
);

INSERT IGNORE INTO `booking_customer_type` (`BookingID`, `CustomerTypeID`)
SELECT b.`BookingID`, ct.`CustomerTypeID`
FROM `booking` b
JOIN `customer` c       ON c.`CustomerID`   = b.`CustomerID`
JOIN `customer_type` ct
  ON CONVERT(ct.`Name` USING utf8mb4) COLLATE utf8mb4_unicode_ci
   = CONVERT(c.`customer_type` USING utf8mb4) COLLATE utf8mb4_unicode_ci
WHERE c.`customer_type` IS NOT NULL AND c.`customer_type` <> '';
