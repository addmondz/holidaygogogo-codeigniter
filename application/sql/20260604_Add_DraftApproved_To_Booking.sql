-- Migration: Add the customer-intake draft approval flag.
-- Date: 2026-06-04
-- Description: After the customer submits the intake, staff review and click
--              "Approve". Only once approved can the booking graduate to PB or
--              PBC. DraftApproved gates the graduate buttons and
--              DraftApprovedDate records when it happened.

ALTER TABLE `booking`
    ADD COLUMN `DraftApproved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `BookingFormText`,
    ADD COLUMN `DraftApprovedDate` DATETIME NULL DEFAULT NULL AFTER `DraftApproved`;
