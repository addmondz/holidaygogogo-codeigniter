-- Per-pax "name is same as the booker" flag on an e-invoice split request.
-- When ticked, the pax IS the booking customer, so the e-invoice submit flow
-- (Invoice_Split_Model::Create_Customers_For_New_Pax) must NOT spin off a
-- duplicate customer even when the pax name is spelled differently from the
-- booking customer (the Jamuna "A/P" case: same phone, different spelling).
--
-- Plain ALTER (no IF NOT EXISTS): the patch runner swallows a re-run 1060.
ALTER TABLE `invoice_split_pax`
  ADD COLUMN `SameAsBooker` TINYINT(1) NOT NULL DEFAULT 0;
