-- Add explicit guest list submission flag
ALTER TABLE `booking`
  ADD COLUMN `is_submitted` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Guest list submitted flag: 0 = not submitted, 1 = submitted'
  AFTER `LockStatus`;

