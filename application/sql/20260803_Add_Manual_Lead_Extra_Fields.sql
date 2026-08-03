-- Extra hand-entered ("Manual") lead fields for the Create Lead form + bulk
-- import. These live on the SAME ghl_contacts table as the synced GHL leads but
-- are only ever written for lead_source='manual' rows (the GHL API sync never
-- touches them). date_of_birth stays on the table (no longer shown on the form).
--
-- NOTE: company_name, source and country already exist on ghl_contacts (GHL sync
-- fields) — the manual form reuses them. country is widened to hold a full name
-- (it was varchar(10), a code). customer_type / lead_status store the preset NAME
-- string: customer_type reuses the existing customer_type module, lead_status the
-- new lead_status settings module.
ALTER TABLE `ghl_contacts`
  MODIFY COLUMN `country`      VARCHAR(100) NULL,
  ADD COLUMN `address`       VARCHAR(255) NULL AFTER `nationality`,
  ADD COLUMN `notes`         TEXT         NULL AFTER `tags_json`,
  ADD COLUMN `customer_type` VARCHAR(50)  NULL AFTER `notes`,
  ADD COLUMN `lead_intro`    TEXT         NULL AFTER `customer_type`,
  ADD COLUMN `lead_status`   VARCHAR(50)  NULL AFTER `lead_intro`;
