-- Quotation System feedback (8 Sep 2026) sales person, travel date range,
-- manual per-combination selling price, and Include/Exclude/Important Notes.
-- Plain ALTERs only (MySQL 9 rejects IF NOT EXISTS as 1064). The patch runner
-- swallows re-run 1060/1061/1091 as non-fatal.

-- #1 Sales person on the package (dropdown of admin users).
ALTER TABLE costing_packages ADD COLUMN sales_admin_id INT NULL DEFAULT NULL AFTER customer_email;

-- #7 Notes and Terms split into Include / Exclude / Important Notes. Terms
-- already exists as itinerary_terms_and_conditions. The old itinerary_notes and
-- itinerary_special_remark columns are left in place (unused) to avoid data loss.
ALTER TABLE costing_packages ADD COLUMN itinerary_includes TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN itinerary_excludes TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN itinerary_important_notes TEXT NULL DEFAULT NULL;

-- #2 / #9 Travel date as a start-end range (existing travel_date is the start).
ALTER TABLE costing_bookings ADD COLUMN travel_date_end DATE NULL DEFAULT NULL AFTER travel_date;

-- #5 Manual selling price per pax per combination (NULL means use the suggested
-- Cost after Markup from the gross margin).
ALTER TABLE costing_combinations ADD COLUMN selling_price_per_pax DECIMAL(12,2) NULL DEFAULT NULL;
