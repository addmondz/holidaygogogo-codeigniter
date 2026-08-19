-- 2026-08-19  Tag each cost row with the combination it belongs to.
-- NULL means an internal cost-template row (the section above, hidden from the
-- customer PDF). A non-NULL combination_id means the row is part of that customer
-- combination bundle. Plain ADD COLUMN is used because MySQL rejects the
-- ADD COLUMN IF NOT EXISTS syntax -- the patch runner treats a re-run
-- Duplicate column name (1060) as non-fatal.
ALTER TABLE `costing_booking_items`
    ADD COLUMN `combination_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `booking_id`;
