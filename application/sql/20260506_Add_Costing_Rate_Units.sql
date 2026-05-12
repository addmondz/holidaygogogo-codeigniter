ALTER TABLE `costing_exchange_rates`
    ADD COLUMN `unit_amount` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 AFTER `to_currency_id`;