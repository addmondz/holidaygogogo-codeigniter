ALTER TABLE `customer`
  ADD COLUMN `AltName` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Alternate/secondary customer name shown on BC form and Guest/Customer dashboard' AFTER `name`;
