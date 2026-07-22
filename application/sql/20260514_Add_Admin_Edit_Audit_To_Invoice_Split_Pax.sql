ALTER TABLE `invoice_split_pax`
  ADD COLUMN `LastEditedByAdmin` INT(11) NULL DEFAULT NULL AFTER `SubmittedDate`,
  ADD COLUMN `LastEditedDate` DATETIME NULL DEFAULT NULL AFTER `LastEditedByAdmin`;
