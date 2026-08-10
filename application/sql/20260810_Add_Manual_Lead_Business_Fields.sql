ALTER TABLE `ghl_contacts`
  ADD COLUMN `nature_of_business` VARCHAR(150) NULL AFTER `lead_status`,
  ADD COLUMN `number_of_pax`      VARCHAR(20)  NULL AFTER `nature_of_business`,
  ADD COLUMN `client_type`        VARCHAR(50)  NULL AFTER `number_of_pax`;
