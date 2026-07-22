-- Migration: Add PBC (PENDING BC CONFIRMATION) and PBO (PENDING BOOKING OPERATION) to booking Status enum
-- Date: 2026-01-20
-- Description: Extends booking status enum to support new booking flow stages

-- Note: MySQL requires MODIFY COLUMN to change ENUM values
-- Current enum: 'Y','N','P','PP','PT','OG','PTV'
-- New enum: 'Y','N','P','PP','PT','OG','PTV','PBC','PBO'

ALTER TABLE `booking`
  MODIFY COLUMN `Status` ENUM('Y','N','P','PP','PT','OG','PTV','PBC','PBO') NOT NULL DEFAULT 'PBC' COLLATE 'utf8mb3_general_ci'
  COMMENT 'Booking status: PBC=PENDING BC CONFIRMATION, P=PENDING PAYMENT, PBO=PENDING BOOKING OPERATION, PP=PARTIAL PAYMENT, PTV=PENDING TRAVEL VOUCHER, PGL=PENDING GUEST LIST (derived), PT=PENDING TRAVEL, OG=ON-GOING, Y=COMPLETED, C=CANCELLED (via CancelStatus), N=LOST';
