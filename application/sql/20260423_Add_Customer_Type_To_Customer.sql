ALTER TABLE `customer`
  ADD COLUMN `customer_type` VARCHAR(50) NULL DEFAULT NULL AFTER `tin_no`;
