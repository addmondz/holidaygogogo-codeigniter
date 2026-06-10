ALTER TABLE `booking_supplier_invoice`
  ADD COLUMN `InvoiceFilePath` VARCHAR(255) NULL DEFAULT NULL AFTER `Remark`;
