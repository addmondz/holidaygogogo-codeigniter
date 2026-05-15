/*
  Add LastEditedByAdmin / LastEditedDate to invoice_split_pax so the admin
  booking detail page can record who/when an admin edited a submitted
  e-invoice request without disturbing the original customer SubmittedDate.
  Both columns are nullable; customer-side submits leave them NULL and
  only admin edits populate them.
*/
ALTER TABLE `invoice_split_pax`
  ADD COLUMN `LastEditedByAdmin` INT(11) NULL DEFAULT NULL AFTER `SubmittedDate`,
  ADD COLUMN `LastEditedDate` DATETIME NULL DEFAULT NULL AFTER `LastEditedByAdmin`;
