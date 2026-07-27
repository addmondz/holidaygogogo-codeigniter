-- Migration: Extend booking.Status enum to include PCI (Pending Customer Info)
-- Date: 2026-05-23
-- Description: Required for the new customer-intake link flow. Booking starts
--              at PCI as a draft awaiting customer-submitted details, then
--              advances to PBC once staff finalise the booking.

ALTER TABLE `booking`
    MODIFY COLUMN `Status` ENUM(
        'Y','N','P','PP','PT','OG','PTV','PBC','PBO','PGL','PCI'
    ) NOT NULL DEFAULT 'PBC';
