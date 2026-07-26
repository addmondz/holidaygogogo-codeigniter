ALTER TABLE `costing_booking_items`
    ADD COLUMN IF NOT EXISTS `bank_charges_myr` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`;
