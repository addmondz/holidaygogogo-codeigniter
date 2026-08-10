-- More hand-entered ("Manual") lead fields for the Create Lead form, filter and
-- bulk import. Written only for lead_source='manual' rows (the GHL API sync never
-- touches them). NOTE: `state` is NOT added here — ghl_contacts already has a
-- `state` VARCHAR(100) GHL-sync column, which the manual State dropdown reuses.
--
--   nature_of_business : free text (filtered by LIKE).
--   number_of_pax      : a bucket label (1-10 … 91-100, 100+); filtered by IN.
--   client_type        : REPLACES the old free-typed Tags on the manual form —
--                         a fixed dropdown (HRDC/Meeting/Incentive/Conference/
--                         Expo/Leisure); filtered by IN. tags_json stays on the
--                         table for the GHL-synced leads.
ALTER TABLE `ghl_contacts`
  ADD COLUMN `nature_of_business` VARCHAR(150) NULL AFTER `lead_status`,
  ADD COLUMN `number_of_pax`      VARCHAR(20)  NULL AFTER `nature_of_business`,
  ADD COLUMN `client_type`        VARCHAR(50)  NULL AFTER `number_of_pax`;
