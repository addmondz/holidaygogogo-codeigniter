-- Per-admin monthly sales target.
-- Stores the target_amount the TC (admin.Level 20 or 50) is expected to bring
-- in for the given (target_year, target_month). The Booking dashboard
-- "Total Sales (Month)" card reads the current-month row for the logged-in
-- TC to compute "actual vs target" as a percentage. Owner / Team Lead set
-- and update targets via Admin / sales_targets, including months in the
-- future, so quotas can be planned ahead.
--
-- Weekly granularity is intentionally NOT stored: the dashboard derives a
-- weekly "pace" from the monthly target. This keeps maintenance simple
-- (one row per TC per month) and matches how the targets are reviewed.

CREATE TABLE IF NOT EXISTS `sales_target` (
  `id`            INT           NOT NULL AUTO_INCREMENT,
  `AdminID`       INT           NOT NULL,
  `target_year`   SMALLINT      NOT NULL,
  `target_month`  TINYINT       NOT NULL,
  `target_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_period` (`AdminID`, `target_year`, `target_month`),
  KEY `idx_admin` (`AdminID`),
  CONSTRAINT `fk_sales_target_admin` FOREIGN KEY (`AdminID`)
    REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
