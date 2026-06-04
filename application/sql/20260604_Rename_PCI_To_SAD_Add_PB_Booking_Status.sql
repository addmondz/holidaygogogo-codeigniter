-- Migration: Refactor the customer-intake draft status flow.
-- Date: 2026-06-04
-- Description:
--   * Rename PCI ("PENDING CUSTOMER INFO") to SAD ("SAVE AS DRAFT") — the parked
--     draft anchor. Existing PCI rows + status-log entries are backfilled so the
--     intake response-time metric (SAD -> PBC) and history stay continuous.
--   * Add PB ("PENDING BC") as a new early flow status, the target of the new
--     "Save as Pending BC" graduate button (distinct from PBC "PENDING BC
--     CONFIRMATION").
--
-- Old enum: 'Y','N','P','PP','PT','OG','PTV','PBC','PBO','PGL','PCI'
-- New enum: 'Y','N','P','PP','PT','OG','PTV','PBC','PB','PBO','PGL','SAD'

-- 1) Widen the enum to hold both old (PCI) and new (SAD, PB) codes during backfill.
ALTER TABLE `booking`
    MODIFY COLUMN `Status` ENUM(
        'Y','N','P','PP','PT','OG','PTV','PBC','PB','PBO','PGL','PCI','SAD'
    ) NOT NULL DEFAULT 'PBC';

-- 2) Backfill existing draft bookings and their status-log trail.
UPDATE `booking`            SET `Status`      = 'SAD' WHERE `Status`      = 'PCI';
UPDATE `booking_status_log` SET `to_status`   = 'SAD' WHERE `to_status`   = 'PCI';
UPDATE `booking_status_log` SET `from_status` = 'SAD' WHERE `from_status` = 'PCI';

-- 3) Drop the retired PCI code from the enum.
ALTER TABLE `booking`
    MODIFY COLUMN `Status` ENUM(
        'Y','N','P','PP','PT','OG','PTV','PBC','PB','PBO','PGL','SAD'
    ) NOT NULL DEFAULT 'PBC';
