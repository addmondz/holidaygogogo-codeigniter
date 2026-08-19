-- Flag a customer that was created from an e-invoice request rather than being
-- the booking customer (booker). Set automatically by the e-invoice submit flow
-- (Invoice_Split_Model::Create_Customers_For_New_Pax) and toggleable on the
-- customer create/edit form. Shown as a badge on the customer listing.
--
-- Plain ALTER (no IF NOT EXISTS): the patch runner swallows a re-run 1060.
ALTER TABLE `customer`
  ADD COLUMN `CreatedFromEInvoice` TINYINT(1) NOT NULL DEFAULT 0;
