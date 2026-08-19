-- 2026-08-19  Costing combinations -- customer-facing bundles.
-- The cost template above (costing_booking_items with combination_id NULL) stays
-- the internal cost/margin/profit basis. A costing scenario can also carry any
-- number of named combinations. Each combination groups its own cost items
-- (costing_booking_items rows tagged with this combination_id). The customer
-- Quotation PDF shows the combinations (name plus items plus price), not the
-- internal cost items. Combinations are additive -- their selling prices sum to
-- the total package price.
CREATE TABLE IF NOT EXISTS `costing_combinations` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_booking_id` BIGINT UNSIGNED NOT NULL,
  `name`               VARCHAR(255) NOT NULL,
  `sort_order`         INT NOT NULL DEFAULT 0,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_combination_booking` (`costing_booking_id`),
  CONSTRAINT `fk_costing_combination_booking`
    FOREIGN KEY (`costing_booking_id`) REFERENCES `costing_bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
