-- Add persistent customer portal visibility flag
-- This ensures bookings remain visible in customer portal once BC has been approved at least once
ALTER TABLE `booking`
  ADD COLUMN IF NOT EXISTS `customer_portal_visible` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Customer portal visibility: 0 = hidden, 1 = visible once BC approved'
  AFTER `bc_approval_date`;

CREATE INDEX IF NOT EXISTS `idx_customer_portal_visible` ON `booking` (`customer_portal_visible`);

-- Backfill existing approved bookings so they remain visible in customer portal
UPDATE `booking`
SET `customer_portal_visible` = 1
WHERE `bc_approved` = 1;
