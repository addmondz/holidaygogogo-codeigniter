CREATE TABLE IF NOT EXISTS `costing_currencies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(3) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `symbol` VARCHAR(10) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_costing_currency_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_exchange_rates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_currency_id` BIGINT UNSIGNED NOT NULL,
  `to_currency_id` BIGINT UNSIGNED NOT NULL,
  `unit_amount` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
  `rate` DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
  `bank_charges_myr` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `updated_by_admin_id` INT(15) NULL DEFAULT NULL,
  `valid_from` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_exchange_lookup` (`from_currency_id`, `to_currency_id`, `valid_from`),
  CONSTRAINT `fk_costing_exchange_from_currency`
    FOREIGN KEY (`from_currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_costing_exchange_to_currency`
    FOREIGN KEY (`to_currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_packages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 1,
  `duration_nights` INT UNSIGNED NOT NULL DEFAULT 0,
  `description` TEXT NULL DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_package_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL DEFAULT NULL,
  `category` VARCHAR(100) NOT NULL,
  `cost_type` VARCHAR(20) NOT NULL,
  `default_unit_price` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `currency_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_package_items_package_id` (`package_id`),
  KEY `idx_costing_package_items_currency_id` (`currency_id`),
  CONSTRAINT `fk_costing_package_items_package`
    FOREIGN KEY (`package_id`) REFERENCES `costing_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_costing_package_items_currency`
    FOREIGN KEY (`currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` BIGINT UNSIGNED NOT NULL,
  `travel_date` DATE NOT NULL,
  `adult_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `child_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_pax` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_bookings_package_id` (`package_id`),
  CONSTRAINT `fk_costing_bookings_package`
    FOREIGN KEY (`package_id`) REFERENCES `costing_packages` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_booking_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `package_item_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `pax_type` VARCHAR(20) NULL DEFAULT NULL,
  `quantity` DECIMAL(18,2) NOT NULL DEFAULT 1.00,
  `unit_count` DECIMAL(18,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `currency_id` BIGINT UNSIGNED NOT NULL,
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `bank_charges_myr` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `remark` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_costing_booking_items_booking_id` (`booking_id`),
  KEY `idx_costing_booking_items_package_item_id` (`package_item_id`),
  KEY `idx_costing_booking_items_currency_id` (`currency_id`),
  CONSTRAINT `fk_costing_booking_items_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `costing_bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_costing_booking_items_package_item`
    FOREIGN KEY (`package_item_id`) REFERENCES `costing_package_items` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_costing_booking_items_currency`
    FOREIGN KEY (`currency_id`) REFERENCES `costing_currencies` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costing_booking_financials` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `margin_percentage` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `commissionable_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `ad_hoc_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `cost_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `markup_amount_total` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `price_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `selling_price_per_pax` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_revenue` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `gross_profit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_profit` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_costing_booking_financials_booking_id` (`booking_id`),
  CONSTRAINT `fk_costing_booking_financials_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `costing_bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
