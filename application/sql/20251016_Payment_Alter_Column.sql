ALTER TABLE `payment`
	ADD COLUMN IF NOT EXISTS `AutocountReferenceNumber` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb4_general_ci' AFTER `ReferenceNumber`,
	ADD INDEX IF NOT EXISTS `AutocountReferenceNumber` (`AutocountReferenceNumber`);
