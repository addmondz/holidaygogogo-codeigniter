-- Plain ADD COLUMN (no IF NOT EXISTS): MySQL rejects that syntax for columns;
-- the patch runner treats a re-run "Duplicate column name" (1060) as non-fatal.
ALTER TABLE `costing_items`
    ADD COLUMN `multiplier_type` ENUM('per_day','per_pax','fixed') NOT NULL DEFAULT 'fixed' AFTER `category`;

ALTER TABLE `costing_booking_items`
    ADD COLUMN `multiplier_type` VARCHAR(10) NULL AFTER `pax_type`;
