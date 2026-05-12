-- Invoice Split by Pax tables
-- Created: 2026-02-27

CREATE TABLE IF NOT EXISTS `invoice_split_pax` (
    `InvoiceSplitPaxID` INT(11) NOT NULL AUTO_INCREMENT,
    `BookingID` INT(11) NOT NULL,
    `PaxName` VARCHAR(255) NOT NULL,
    `TIN` VARCHAR(50) NOT NULL,
    `SubtotalAmount` DECIMAL(10,2) DEFAULT 0.00,
    `DiscountAmount` DECIMAL(10,2) DEFAULT 0.00,
    `NetAmount` DECIMAL(10,2) DEFAULT 0.00,
    `SortOrder` INT(11) DEFAULT 0,
    `Status` CHAR(1) DEFAULT 'Y',
    `InsertDate` DATETIME DEFAULT NULL,
    `UpdateDate` DATETIME DEFAULT NULL,
    PRIMARY KEY (`InvoiceSplitPaxID`),
    KEY `idx_booking_id` (`BookingID`),
    KEY `idx_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoice_split_pax_product` (
    `InvoiceSplitPaxProductID` INT(11) NOT NULL AUTO_INCREMENT,
    `InvoiceSplitPaxID` INT(11) NOT NULL,
    `BookingProductID` INT(11) NOT NULL,
    `Quantity` DECIMAL(10,2) DEFAULT 0.00,
    `UnitPrice` DECIMAL(10,2) DEFAULT 0.00,
    `Amount` DECIMAL(10,2) DEFAULT 0.00,
    `Status` CHAR(1) DEFAULT 'Y',
    PRIMARY KEY (`InvoiceSplitPaxProductID`),
    KEY `idx_invoice_split_pax_id` (`InvoiceSplitPaxID`),
    KEY `idx_booking_product_id` (`BookingProductID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
