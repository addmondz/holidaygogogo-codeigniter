-- Per-day rich-text blocks for the costing itinerary. `description` already
-- exists; add the four new TinyMCE fields. Plain ADD COLUMN (no IF NOT EXISTS):
-- MySQL rejects that syntax for columns; the patch runner treats a re-run
-- "Duplicate column name" (1060) as non-fatal.
ALTER TABLE `costing_itinerary_days`
    ADD COLUMN `meal_plan` TEXT NULL DEFAULT NULL AFTER `description`,
    ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `meal_plan`,
    ADD COLUMN `special_remark` TEXT NULL DEFAULT NULL AFTER `notes`,
    ADD COLUMN `terms_and_conditions` TEXT NULL DEFAULT NULL AFTER `special_remark`;
