-- Notes / Special Remark / Terms & Conditions belong to the WHOLE itinerary,
-- not each day. Move them off costing_itinerary_days onto costing_packages.
-- Plain ADD/UPDATE/DROP (no IF NOT EXISTS): MySQL rejects that syntax and the
-- patch runner treats a re-run 1060 (dup column) / 1091 (drop-missing) as
-- non-fatal.
ALTER TABLE costing_packages ADD COLUMN itinerary_notes TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN itinerary_special_remark TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN itinerary_terms_and_conditions TEXT NULL DEFAULT NULL;

-- Backfill: the earliest day (by day_number, id) that carried a non-empty value
-- wins, so any existing content survives the move.
UPDATE costing_packages cp SET itinerary_notes = (
    SELECT d.notes FROM costing_itinerary_days d
    WHERE d.package_id = cp.id AND d.notes IS NOT NULL AND d.notes <> ''
    ORDER BY d.day_number ASC, d.id ASC LIMIT 1
);
UPDATE costing_packages cp SET itinerary_special_remark = (
    SELECT d.special_remark FROM costing_itinerary_days d
    WHERE d.package_id = cp.id AND d.special_remark IS NOT NULL AND d.special_remark <> ''
    ORDER BY d.day_number ASC, d.id ASC LIMIT 1
);
UPDATE costing_packages cp SET itinerary_terms_and_conditions = (
    SELECT d.terms_and_conditions FROM costing_itinerary_days d
    WHERE d.package_id = cp.id AND d.terms_and_conditions IS NOT NULL AND d.terms_and_conditions <> ''
    ORDER BY d.day_number ASC, d.id ASC LIMIT 1
);

-- Drop the now-unused per-day columns.
ALTER TABLE costing_itinerary_days DROP COLUMN notes;
ALTER TABLE costing_itinerary_days DROP COLUMN special_remark;
ALTER TABLE costing_itinerary_days DROP COLUMN terms_and_conditions;
