-- 2026-08-12  Frozen per-costing currency snapshot.
-- When a costing scenario (costing_bookings row) is saved, each distinct
-- currency used gets a frozen "1 <CUR> = rate_to_myr MYR" row plus an optional
-- remark. Conversions for a saved scenario read THIS table, never the live feed,
-- so a saved quotation's numbers never move. MYR is always 1.0.
CREATE TABLE IF NOT EXISTS `costing_snapshot_rates` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_booking_id` BIGINT UNSIGNED NOT NULL,
  `currency_id`        BIGINT UNSIGNED NOT NULL,
  `currency_code`      VARCHAR(3) NOT NULL,
  `rate_to_myr`        DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
  `remark`             VARCHAR(255) NULL DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_costing_snapshot_scenario_currency` (`costing_booking_id`, `currency_id`),
  KEY `idx_costing_snapshot_currency` (`currency_id`),
  CONSTRAINT `fk_costing_snapshot_booking`
    FOREIGN KEY (`costing_booking_id`) REFERENCES `costing_bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_costing_snapshot_currency`
    FOREIGN KEY (`currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
