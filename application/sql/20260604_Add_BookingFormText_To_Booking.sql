-- Migration: Add the free-text "Booking Form" column to bookings.
-- Date: 2026-06-04
-- Description: Editable on the admin booking form while a booking is a draft
--              (SAD). Free-form notes the staff capture for the customer-intake
--              draft before pricing/products are known.

ALTER TABLE `booking`
    ADD COLUMN `BookingFormText` TEXT NULL DEFAULT NULL AFTER `BookingRemark`;
