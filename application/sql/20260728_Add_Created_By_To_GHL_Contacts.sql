-- Manual leads move to their own private "Manual Leads" page where a lead is
-- visible only to the admin who created it (Owner/Team Lead/Marketing see all).
-- Record that creator so the listing can scope by it. Existing manual leads keep
-- created_by = NULL (visible only to the view-all roles until reassigned).
ALTER TABLE `ghl_contacts`
  ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'admin.AdminID of the manual-lead creator (NULL for GHL-synced)' AFTER `lead_source`,
  ADD INDEX `idx_ghl_contacts_created_by` (`created_by`);
