-- 2026-08-12  Per-day itinerary for a costing package/tour.
-- Drives the customer-facing Quotation PDF (day number, title, description).
-- Attached to the package so it is shared across that tour's cost scenarios.
CREATE TABLE IF NOT EXISTS `costing_itinerary_days` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  BIGINT UNSIGNED NOT NULL,
  `day_number`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_itinerary_package` (`package_id`),
  CONSTRAINT `fk_costing_itinerary_package`
    FOREIGN KEY (`package_id`) REFERENCES `costing_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
