-- Allow bookings (drafts and Pending BCs) to be saved without a Full Payment
-- Deadline. Previously the column was NOT NULL, which forced the booking form
-- to auto-fill a placeholder (travel start date / today) when staff left the
-- deadline blank. Making it nullable lets a blank deadline stay blank.
ALTER TABLE `booking` MODIFY `FullPaymentDeadline` DATE NULL DEFAULT NULL;
