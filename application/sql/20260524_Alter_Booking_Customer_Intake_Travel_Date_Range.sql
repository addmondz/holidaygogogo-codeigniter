-- Migration: Customer intake travel date becomes a range (start + end)
-- Date: 2026-05-24
-- Description: The booking form already stores StartDate + EndDate as a range
--              and renders the picker as "d/m/Y - d/m/Y". To make the intake
--              data auto-propagate cleanly into the booking edit form, capture
--              both dates from the customer instead of a single travel_date.

ALTER TABLE `booking_customer_intake`
    CHANGE COLUMN `travel_date` `travel_start_date` DATE NOT NULL;

ALTER TABLE `booking_customer_intake`
    ADD COLUMN `travel_end_date` DATE NULL AFTER `travel_start_date`;

-- Backfill any pre-existing rows as same-day trips so the NOT NULL tightening
-- below succeeds. New submissions always supply both dates explicitly.
UPDATE `booking_customer_intake`
    SET `travel_end_date` = `travel_start_date`
    WHERE `travel_end_date` IS NULL;

ALTER TABLE `booking_customer_intake`
    MODIFY COLUMN `travel_end_date` DATE NOT NULL;
