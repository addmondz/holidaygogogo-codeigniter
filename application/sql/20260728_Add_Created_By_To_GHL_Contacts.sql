ALTER TABLE `ghl_contacts`
  ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'admin.AdminID of the manual-lead creator (NULL for GHL-synced)' AFTER `lead_source`,
  ADD INDEX `idx_ghl_contacts_created_by` (`created_by`);
