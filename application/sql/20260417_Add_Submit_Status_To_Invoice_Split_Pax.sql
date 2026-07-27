-- Add SubmitStatus / SubmittedDate to invoice_split_pax so the customer portal
-- can distinguish a draft e-invoice request (Save) from a finalized one (Submit).
-- 'D' = Draft (customer can still edit), 'S' = Submitted (form is locked).
-- Existing rows default to 'D' so no prior requests are treated as submitted.
ALTER TABLE `invoice_split_pax`
  ADD COLUMN `SubmitStatus` CHAR(1) NOT NULL DEFAULT 'D' AFTER `Status`,
  ADD COLUMN `SubmittedDate` DATETIME NULL DEFAULT NULL AFTER `SubmitStatus`;
