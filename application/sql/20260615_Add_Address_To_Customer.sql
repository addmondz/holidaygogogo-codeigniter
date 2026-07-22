ALTER TABLE `customer`
  ADD COLUMN `Address` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Billing address, synced to AutoCount debtor address' AFTER `tin_no`;
