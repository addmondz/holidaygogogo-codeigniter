CREATE TABLE IF NOT EXISTS `booking_supplier_invoice` (
  `SupplierInvoiceID` INT AUTO_INCREMENT PRIMARY KEY,
  `BookingID` INT NOT NULL,
  `SupplierID` INT NOT NULL,
  `InvoiceNumber` VARCHAR(100) NOT NULL,
  `InvoiceAmount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `PaymentDeadline` DATE NULL,
  `Remark` TEXT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_supplier_invoice` (`SupplierID`, `InvoiceNumber`),
  INDEX `idx_bsi_booking` (`BookingID`),
  INDEX `idx_bsi_supplier_deadline` (`SupplierID`, `PaymentDeadline`),
  INDEX `idx_bsi_invoice_number` (`InvoiceNumber`),
  INDEX `idx_bsi_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
