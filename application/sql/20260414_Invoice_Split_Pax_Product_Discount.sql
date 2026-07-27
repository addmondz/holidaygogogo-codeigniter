-- Add product-level discount column for e-invoice split
-- Created: 2026-04-14

ALTER TABLE `invoice_split_pax_product`
    ADD COLUMN `DiscountAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `Amount`;
