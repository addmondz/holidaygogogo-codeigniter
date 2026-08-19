-- 2026-08-19  Costing category master (make categories dynamic).
-- The five categories were hardcoded in costing_calc_helper::costing_categories()
-- and pinned by the costing_items.category ENUM. This table lets Owner/costing
-- admin add/rename/soft-delete categories. Rows keep their stable `code` slug so
-- existing costing_items/package_items/booking_items keep resolving. Seeded with
-- the original five for backward compatibility. Soft-deleted via Status='N'.
CREATE TABLE IF NOT EXISTS `costing_categories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(100) NOT NULL,
  `name`       VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `Status`     ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_costing_categories_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the original five (idempotent on the unique code).
INSERT IGNORE INTO `costing_categories` (`code`, `name`, `sort_order`) VALUES
  ('flight',        'Flight',        1),
  ('accommodation', 'Accommodation', 2),
  ('other',         'Other',         3),
  ('tour_leader',   'Tour Leader',   4),
  ('miscellaneous', 'Miscellaneous', 5);

-- Drop the ENUM lock so items can use any active category code.
ALTER TABLE `costing_items` MODIFY `category` VARCHAR(100) NOT NULL;
