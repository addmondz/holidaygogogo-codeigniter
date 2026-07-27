-- Migration: Add PGL (PENDING GUEST LIST) to booking Status enum
-- Date: 2026-01-26
-- Description: Adds PGL status to the booking Status enum to allow storing PENDING GUEST LIST status in database

-- Note: MySQL requires MODIFY COLUMN to change ENUM values
-- Current enum: 'Y','N','P','PP','PT','OG','PTV','PBC','PBO'
-- New enum: 'Y','N','P','PP','PT','OG','PTV','PBC','PBO','PGL'

ALTER TABLE `booking`
  MODIFY COLUMN `Status` ENUM('Y','N','P','PP','PT','OG','PTV','PBC','PBO','PGL') NOT NULL DEFAULT 'PBC' COLLATE 'utf8mb3_general_ci'
  COMMENT 'Booking status: PBC=PENDING BC CONFIRMATION, P=PENDING PAYMENT, PBO=PENDING BOOKING OPERATION, PP=PARTIAL PAYMENT, PTV=PENDING TRAVEL VOUCHER, PGL=PENDING GUEST LIST, PT=PENDING TRAVEL, OG=ON-GOING, Y=COMPLETED, C=CANCELLED (via CancelStatus), N=LOST';
