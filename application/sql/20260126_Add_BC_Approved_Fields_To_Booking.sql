-- Migration: Add BC approval fields to booking table
-- Date: 2026-01-26
-- Description: Adds bc_approved and bc_approval_admin_id fields to track BC approval status

ALTER TABLE `booking`
  ADD COLUMN `bc_approved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'BC approval status: 0 = not approved, 1 = approved',
  ADD COLUMN `bc_approval_admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ID who approved the BC',
  ADD INDEX `idx_bc_approved` (`bc_approved`),
  ADD INDEX `idx_bc_approval_admin_id` (`bc_approval_admin_id`);
