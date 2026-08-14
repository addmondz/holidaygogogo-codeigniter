-- Add the "per_day_pax" (day x pax) multiplier option.
-- costing_items.multiplier_type is an ENUM that must list the new value;
-- costing_booking_items.multiplier_type was VARCHAR(10) but "per_day_pax" is 11
-- chars, so widen it. MODIFY COLUMN is idempotent (safe to re-run).
ALTER TABLE `costing_items`
    MODIFY COLUMN `multiplier_type` ENUM('per_day','per_pax','per_day_pax','fixed') NOT NULL DEFAULT 'fixed';

ALTER TABLE `costing_booking_items`
    MODIFY COLUMN `multiplier_type` VARCHAR(20) NULL;
