-- Freeze the "MYR (convert)" value (foreign cost -> MYR, bank charge already
-- baked in) onto each cost row at save time, so it never drifts when the
-- exchange-rate master or bank charge changes later. NULL = legacy pre-freeze
-- row (read falls back to recomputing from the snapshot/live rate).
-- Plain ADD COLUMN (no IF NOT EXISTS): MySQL rejects that syntax for columns;
-- the patch runner treats a re-run "Duplicate column name" (1060) as non-fatal.
ALTER TABLE `costing_booking_items`
    ADD COLUMN `myr_per_unit` DECIMAL(18,2) NULL DEFAULT NULL AFTER `bank_charges_myr`;
