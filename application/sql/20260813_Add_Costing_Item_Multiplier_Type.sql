-- Cost-template wizard: each cost line scales by number of days, number of pax,
-- or a fixed 1. Store that intent on the global item master and copy it onto each
-- snapshot cost row so the wizard can re-render the right "No of Day / No of pax"
-- label and count on edit.

ALTER TABLE `costing_items`
    ADD COLUMN IF NOT EXISTS `multiplier_type` ENUM('per_day','per_pax','fixed') NOT NULL DEFAULT 'fixed' AFTER `category`;

ALTER TABLE `costing_booking_items`
    ADD COLUMN IF NOT EXISTS `multiplier_type` VARCHAR(10) NULL AFTER `pax_type`;
