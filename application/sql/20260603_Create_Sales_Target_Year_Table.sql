-- Per-admin YEARLY sales target.
-- Companion to sales_target (which is monthly). Stores the annual
-- target_amount the TC (admin.Level 20 or 50) is expected to bring in for the
-- given target_year. The Booking dashboard "Year Sales vs Target" card reads
-- the selected year's row for the logged-in TC to compute "actual vs target"
-- as a percentage.
--
-- Kept in its own table rather than as a month=0 sentinel in sales_target so
-- the monthly logic (Admin_Model::_Sync_Sales_Targets validates month 1-12,
-- the admin saved-targets table renders months) stays untouched. One row per
-- TC per year, mirroring the monthly table's one-row-per-TC-per-month shape.

CREATE TABLE IF NOT EXISTS `sales_target_year` (
  `id`            INT           NOT NULL AUTO_INCREMENT,
  `AdminID`       INT           NOT NULL,
  `target_year`   SMALLINT      NOT NULL,
  `target_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_year` (`AdminID`, `target_year`),
  KEY `idx_admin` (`AdminID`),
  CONSTRAINT `fk_sales_target_year_admin` FOREIGN KEY (`AdminID`)
    REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
