-- Migration: Update Admin Access Control and Set BC Approved
-- Date: 2026-01-26
-- Description: 
--   1. For all admins with level = 20 (SALES AGENT) and AccessControl containing 'VB', replace 'VB' with 'AB'
--   2. Set all current bookings bc_approved = 1

UPDATE `admin`
SET `AccessControl` = 'AB, VB'
WHERE `Level` = '20'
  AND `AccessControl` = 'VB';

-- Step 2: Set all current bookings bc_approved = 1
UPDATE `booking`
SET `bc_approved` = 1
WHERE `bc_approved` = 0;
