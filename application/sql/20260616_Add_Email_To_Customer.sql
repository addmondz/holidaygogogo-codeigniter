ALTER TABLE `customer`
  ADD COLUMN `PrimaryEmail` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Primary email, synced to AutoCount debtor emailAddress' AFTER `Address`;
