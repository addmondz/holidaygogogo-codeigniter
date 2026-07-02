INSERT INTO `costing_currencies` (`code`, `name`, `symbol`)
VALUES
  ('MYR', 'Malaysian Ringgit', 'RM'),
  ('SGD', 'Singapore Dollar', 'S$'),
  ('USD', 'US Dollar', '$'),
  ('THB', 'Thai Baht', 'THB'),
  ('IDR', 'Indonesian Rupiah', 'Rp')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `symbol` = VALUES(`symbol`);

SET @myr_id = (SELECT `id` FROM `costing_currencies` WHERE `code` = 'MYR' LIMIT 1);
SET @sgd_id = (SELECT `id` FROM `costing_currencies` WHERE `code` = 'SGD' LIMIT 1);
SET @usd_id = (SELECT `id` FROM `costing_currencies` WHERE `code` = 'USD' LIMIT 1);
SET @thb_id = (SELECT `id` FROM `costing_currencies` WHERE `code` = 'THB' LIMIT 1);
SET @idr_id = (SELECT `id` FROM `costing_currencies` WHERE `code` = 'IDR' LIMIT 1);

INSERT INTO `costing_exchange_rates` (`from_currency_id`, `to_currency_id`, `unit_amount`, `rate`, `valid_from`)
SELECT @sgd_id, @myr_id, 1.00000000, 3.48000000, '2026-07-01 00:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_exchange_rates`
  WHERE `from_currency_id` = @sgd_id AND `to_currency_id` = @myr_id AND `valid_from` = '2026-07-01 00:00:00'
);

INSERT INTO `costing_exchange_rates` (`from_currency_id`, `to_currency_id`, `unit_amount`, `rate`, `valid_from`)
SELECT @usd_id, @myr_id, 1.00000000, 4.72000000, '2026-07-01 00:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_exchange_rates`
  WHERE `from_currency_id` = @usd_id AND `to_currency_id` = @myr_id AND `valid_from` = '2026-07-01 00:00:00'
);

INSERT INTO `costing_exchange_rates` (`from_currency_id`, `to_currency_id`, `unit_amount`, `rate`, `valid_from`)
SELECT @thb_id, @myr_id, 100.00000000, 12.85000000, '2026-07-01 00:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_exchange_rates`
  WHERE `from_currency_id` = @thb_id AND `to_currency_id` = @myr_id AND `valid_from` = '2026-07-01 00:00:00'
);

INSERT INTO `costing_exchange_rates` (`from_currency_id`, `to_currency_id`, `unit_amount`, `rate`, `valid_from`)
SELECT @idr_id, @myr_id, 10000.00000000, 2.88000000, '2026-07-01 00:00:00'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_exchange_rates`
  WHERE `from_currency_id` = @idr_id AND `to_currency_id` = @myr_id AND `valid_from` = '2026-07-01 00:00:00'
);

INSERT INTO `costing_packages` (`name`, `duration_days`, `duration_nights`, `description`, `status`)
SELECT 'Demo Bangkok 4D3N Free & Easy', 4, 3, 'Sample costing package for hotel, transfer, tour, and guide costing.', 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_packages` WHERE `name` = 'Demo Bangkok 4D3N Free & Easy'
);

INSERT INTO `costing_packages` (`name`, `duration_days`, `duration_nights`, `description`, `status`)
SELECT 'Demo Bali 5D4N Private Tour', 5, 4, 'Sample costing package for private tour costing with mixed currencies.', 'draft'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_packages` WHERE `name` = 'Demo Bali 5D4N Private Tour'
);

SET @bangkok_package_id = (SELECT `id` FROM `costing_packages` WHERE `name` = 'Demo Bangkok 4D3N Free & Easy' LIMIT 1);
SET @bali_package_id = (SELECT `id` FROM `costing_packages` WHERE `name` = 'Demo Bali 5D4N Private Tour' LIMIT 1);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bangkok_package_id, 'Bangkok Hotel Twin Sharing', '3 nights city hotel based on twin sharing.', 'Accommodation', 'per_pax', 4200.00, @thb_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Bangkok Hotel Twin Sharing'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bangkok_package_id, 'Airport Return Transfer', 'Private airport return transfer.', 'Transport', 'fixed', 1800.00, @thb_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Airport Return Transfer'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bangkok_package_id, 'Half Day City Tour', 'Temple and city tour with local guide.', 'Tour', 'per_pax', 950.00, @thb_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Half Day City Tour'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bangkok_package_id, 'Tour Leader Allowance', 'Daily allowance for tour leader.', 'Staff', 'per_unit', 180.00, @myr_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Tour Leader Allowance'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bali_package_id, 'Bali Villa Stay', '4 nights villa stay.', 'Accommodation', 'per_pax', 1850000.00, @idr_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Bali Villa Stay'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bali_package_id, 'Private Driver & Van', 'Private driver and van for sightseeing days.', 'Transport', 'per_unit', 650000.00, @idr_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Private Driver & Van'
);

INSERT INTO `costing_package_items` (`package_id`, `name`, `description`, `category`, `cost_type`, `default_unit_price`, `currency_id`)
SELECT @bali_package_id, 'Ubud & Kintamani Tour', 'Full day private tour.', 'Tour', 'per_pax', 320000.00, @idr_id
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Ubud & Kintamani Tour'
);

INSERT INTO `costing_bookings` (`package_id`, `travel_date`, `adult_count`, `child_count`, `total_pax`, `status`)
SELECT @bangkok_package_id, '2026-08-15', 6, 2, 8, 'active'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_bookings`
  WHERE `package_id` = @bangkok_package_id AND `travel_date` = '2026-08-15' AND `adult_count` = 6 AND `child_count` = 2
);

INSERT INTO `costing_bookings` (`package_id`, `travel_date`, `adult_count`, `child_count`, `total_pax`, `status`)
SELECT @bali_package_id, '2026-09-10', 4, 0, 4, 'draft'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_bookings`
  WHERE `package_id` = @bali_package_id AND `travel_date` = '2026-09-10' AND `adult_count` = 4 AND `child_count` = 0
);

SET @bangkok_booking_id = (
  SELECT `id` FROM `costing_bookings`
  WHERE `package_id` = @bangkok_package_id AND `travel_date` = '2026-08-15' AND `adult_count` = 6 AND `child_count` = 2
  LIMIT 1
);
SET @bali_booking_id = (
  SELECT `id` FROM `costing_bookings`
  WHERE `package_id` = @bali_package_id AND `travel_date` = '2026-09-10' AND `adult_count` = 4 AND `child_count` = 0
  LIMIT 1
);

SET @bangkok_hotel_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Bangkok Hotel Twin Sharing' LIMIT 1);
SET @bangkok_transfer_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Airport Return Transfer' LIMIT 1);
SET @bangkok_tour_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Half Day City Tour' LIMIT 1);
SET @bangkok_leader_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bangkok_package_id AND `name` = 'Tour Leader Allowance' LIMIT 1);
SET @bali_villa_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Bali Villa Stay' LIMIT 1);
SET @bali_driver_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Private Driver & Van' LIMIT 1);
SET @bali_tour_item_id = (SELECT `id` FROM `costing_package_items` WHERE `package_id` = @bali_package_id AND `name` = 'Ubud & Kintamani Tour' LIMIT 1);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bangkok_booking_id, @bangkok_hotel_item_id, 'Bangkok Hotel Twin Sharing', 'Accommodation', '', 8.00, 1.00, 4200.00, @thb_id, 33600.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bangkok_booking_id AND `name` = 'Bangkok Hotel Twin Sharing'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bangkok_booking_id, @bangkok_transfer_item_id, 'Airport Return Transfer', 'Transport', '', 1.00, 1.00, 1800.00, @thb_id, 1800.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bangkok_booking_id AND `name` = 'Airport Return Transfer'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bangkok_booking_id, @bangkok_tour_item_id, 'Half Day City Tour', 'Tour', '', 8.00, 1.00, 950.00, @thb_id, 7600.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bangkok_booking_id AND `name` = 'Half Day City Tour'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bangkok_booking_id, @bangkok_leader_item_id, 'Tour Leader Allowance', 'Staff', '', 4.00, 1.00, 180.00, @myr_id, 720.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bangkok_booking_id AND `name` = 'Tour Leader Allowance'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bali_booking_id, @bali_villa_item_id, 'Bali Villa Stay', 'Accommodation', '', 4.00, 1.00, 1850000.00, @idr_id, 7400000.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bali_booking_id AND `name` = 'Bali Villa Stay'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bali_booking_id, @bali_driver_item_id, 'Private Driver & Van', 'Transport', '', 4.00, 1.00, 650000.00, @idr_id, 2600000.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bali_booking_id AND `name` = 'Private Driver & Van'
);

INSERT INTO `costing_booking_items` (`booking_id`, `package_item_id`, `name`, `category`, `pax_type`, `quantity`, `unit_count`, `unit_price`, `currency_id`, `total_amount`, `remark`)
SELECT @bali_booking_id, @bali_tour_item_id, 'Ubud & Kintamani Tour', 'Tour', '', 4.00, 1.00, 320000.00, @idr_id, 1280000.00, 'Seed snapshot row'
WHERE NOT EXISTS (
  SELECT 1 FROM `costing_booking_items` WHERE `booking_id` = @bali_booking_id AND `name` = 'Ubud & Kintamani Tour'
);

INSERT INTO `costing_booking_financials` (
  `booking_id`, `margin_percentage`, `commissionable_per_pax`, `ad_hoc_per_pax`, `total_cost`, `cost_per_pax`,
  `markup_amount_total`, `price_per_pax`, `total_per_pax`, `selling_price_per_pax`, `total_revenue`, `gross_profit`, `total_profit`
)
VALUES
  (@bangkok_booking_id, 18.00, 35.00, 20.00, 6240.40, 780.05, 1123.27, 920.46, 975.46, 975.46, 7803.68, 1563.28, 1563.28),
  (@bali_booking_id, 20.00, 50.00, 25.00, 3248.64, 812.16, 649.73, 974.59, 1049.59, 1049.59, 4198.36, 949.72, 949.72)
ON DUPLICATE KEY UPDATE
  `margin_percentage` = VALUES(`margin_percentage`),
  `commissionable_per_pax` = VALUES(`commissionable_per_pax`),
  `ad_hoc_per_pax` = VALUES(`ad_hoc_per_pax`),
  `total_cost` = VALUES(`total_cost`),
  `cost_per_pax` = VALUES(`cost_per_pax`),
  `markup_amount_total` = VALUES(`markup_amount_total`),
  `price_per_pax` = VALUES(`price_per_pax`),
  `total_per_pax` = VALUES(`total_per_pax`),
  `selling_price_per_pax` = VALUES(`selling_price_per_pax`),
  `total_revenue` = VALUES(`total_revenue`),
  `gross_profit` = VALUES(`gross_profit`),
  `total_profit` = VALUES(`total_profit`);
