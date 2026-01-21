ALTER TABLE `booking`
  ADD COLUMN IF NOT EXISTS `bc_approval_date` DATETIME NULL DEFAULT NULL COMMENT 'Date and time BC was approved' AFTER `bc_approved`;
