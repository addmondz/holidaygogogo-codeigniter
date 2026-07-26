ALTER TABLE `costing_exchange_rates`
    ADD COLUMN IF NOT EXISTS `bank_charges_myr` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `rate`;
