-- =============================================
-- 20260424_Add_PartialRefund_To_Booking
-- =============================================
-- Adds a PartialRefund flag on booking. When set to 'Y', the booking is
-- NOT cancelled (CancelStatus stays 'N'), but it will appear in the
-- "Cancelled" tab on the customer profile page so the customer sees it
-- alongside their cancelled bookings after a partial refund is issued.
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `PartialRefund` ENUM('Y','N') NOT NULL DEFAULT 'N'
  COMMENT 'Partial-refund cancel: booking is NOT cancelled, but appears in the cancelled tab on the customer profile.'
  AFTER `CancelStatus`;
