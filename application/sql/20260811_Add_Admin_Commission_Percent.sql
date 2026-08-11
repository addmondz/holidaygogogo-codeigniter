-- 2026-08-11  Per-TC sales commission rate.
-- Adds a commission percentage column to each admin. Used by the TC Booking
-- dashboard "Sales Commission (Month)" card: commission = CommissionPercent
-- applied to the NetTotal of completed bookings on which the TC is SalesAgent2.
-- Set per TC under Admin -> (Update) -> Sales Target section.
ALTER TABLE `admin`
    ADD COLUMN `CommissionPercent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `TeamID`;
