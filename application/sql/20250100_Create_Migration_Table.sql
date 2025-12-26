-- =============================================
-- MIGRATIONS TABLE (Laravel-compatible)
-- =============================================

CREATE TABLE `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Insert the previous migrations
INSERT INTO `migrations` (`migration`) VALUES
  ('20250100_Create_Migration_Table.sql'),
  ('20250101_Guest_List_Add_LastName_Column.sql'),
  ('20250928_Booking_Alter_Column.sql'),
  ('20250928_Payment_Alter_Column.sql'),
  ('20251016_Payment_Alter_Column.sql'),
  ('20251024_Supplier_Booking_Alter_Column.sql'),
  ('20251027_Customer_Table.sql'),
  ('20251117_Payment_Alter_Column.sql');