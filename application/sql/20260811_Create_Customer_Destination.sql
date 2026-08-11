-- Customer <-> Destination many-to-many link.
--
-- Lets a customer have MULTIPLE destinations manually attached on the Customer
-- create/edit form (Customer/Create, Customer/Update). This is separate from the
-- destinations implicitly derived from the customer's bookings (Guest List) —
-- those stay read-only and computed. CategoryID references a destination category
-- (category.IsDestination = 'YES'). Mirrors the category_product link convention.
CREATE TABLE IF NOT EXISTS `customer_destination` (
  `CustomerDestinationID` INT(11) NOT NULL AUTO_INCREMENT,
  `CustomerID` INT(11) NOT NULL,
  `CategoryID` INT(11) NOT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT(11) NULL,
  `InsertDate` DATETIME NULL,
  PRIMARY KEY (`CustomerDestinationID`),
  UNIQUE KEY `uq_customer_destination` (`CustomerID`, `CategoryID`),
  KEY `idx_customer_destination_customer` (`CustomerID`),
  KEY `idx_customer_destination_category` (`CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
