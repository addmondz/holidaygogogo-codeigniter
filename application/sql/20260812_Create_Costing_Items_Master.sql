-- 2026-08-12  Global costing item master.
-- Reusable cost items grouped into the five fixed costing categories
-- (flight, accommodation, other, tour leader, miscellaneous). Picked when
-- building a package cost template. Soft-deleted via Status='N'.
CREATE TABLE IF NOT EXISTS `costing_items` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                VARCHAR(255) NOT NULL,
  `category`            ENUM('flight','accommodation','other','tour_leader','miscellaneous') NOT NULL,
  `default_currency_id` BIGINT UNSIGNED NOT NULL,
  `default_unit_cost`   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `Status`              ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_items_category` (`category`),
  KEY `idx_costing_items_currency` (`default_currency_id`),
  CONSTRAINT `fk_costing_items_currency`
    FOREIGN KEY (`default_currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
