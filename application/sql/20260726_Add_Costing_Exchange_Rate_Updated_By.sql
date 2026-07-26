ALTER TABLE `costing_exchange_rates`
    ADD COLUMN IF NOT EXISTS `updated_by_admin_id` INT(15) NULL DEFAULT NULL AFTER `bank_charges_myr`;
