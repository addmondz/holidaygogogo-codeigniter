-- Quotation System feedback (9 Sep 2026, screenshot layout) hotel pricing table
-- + flight schedule for the customer Quotation PDF. This was DEFERRED in the
-- 20260909 feedback build and is now implemented.
--
-- Plain ALTER/CREATE only. MySQL 9 rejects IF NOT EXISTS on ALTER as 1064. The
-- patch runner swallows re-run 1060/1061/1091 and CREATE-already-exists as
-- non-fatal. CREATE TABLE keeps IF NOT EXISTS (allowed for CREATE).

-- Per-package hotel pricing rows (per person): twin/triple and single supplement.
CREATE TABLE IF NOT EXISTS `costing_quote_hotels` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`        BIGINT UNSIGNED NOT NULL,
  `sort_order`        INT UNSIGNED NOT NULL DEFAULT 0,
  `hotel_name`        VARCHAR(255) NULL DEFAULT NULL,
  `twin_triple_price` DECIMAL(12,2) NULL DEFAULT NULL,
  `single_supp_price` DECIMAL(12,2) NULL DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_quote_hotels_package` (`package_id`),
  CONSTRAINT `fk_costing_quote_hotels_package`
    FOREIGN KEY (`package_id`) REFERENCES `costing_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-package flight schedule rows. Travel date/timing/duration are kept as free
-- text so the exact wording on the quote ("15 Jan 2027", "1250 - 1355") is
-- preserved verbatim.
CREATE TABLE IF NOT EXISTS `costing_quote_flights` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id`  BIGINT UNSIGNED NOT NULL,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `travel_date` VARCHAR(100) NULL DEFAULT NULL,
  `sector`      VARCHAR(150) NULL DEFAULT NULL,
  `flight_no`   VARCHAR(60)  NULL DEFAULT NULL,
  `timing`      VARCHAR(120) NULL DEFAULT NULL,
  `duration`    VARCHAR(120) NULL DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_quote_flights_package` (`package_id`),
  CONSTRAINT `fk_costing_quote_flights_package`
    FOREIGN KEY (`package_id`) REFERENCES `costing_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package-level free-text fields shown around the hotel/flight tables on the quote.
ALTER TABLE costing_packages ADD COLUMN quote_pricing_basis VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_travel_date_note VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_hotel_note TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_flight_title VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_flight_price DECIMAL(12,2) NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_flight_fare_note TEXT NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_flight_expiry VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE costing_packages ADD COLUMN quote_footer_notes TEXT NULL DEFAULT NULL;
