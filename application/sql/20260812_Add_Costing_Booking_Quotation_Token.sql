-- 2026-08-12  Shareable quotation link token for a costing scenario.
-- Unguessable handle used by Costing::Quotation to stream the customer PDF
-- (base_url('Costing/Quotation?token=...')). Set once on first save.
ALTER TABLE `costing_bookings`
  ADD COLUMN `quotation_token` CHAR(32) NULL DEFAULT NULL AFTER `status`,
  ADD UNIQUE KEY `uniq_costing_bookings_quotation_token` (`quotation_token`);
