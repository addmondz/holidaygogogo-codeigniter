-- Create booking_checklist_completion table
CREATE TABLE IF NOT EXISTS `booking_checklist_completion` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `package_checklist_id` INT(11) NOT NULL COMMENT 'Foreign key to package_checklist.ID',
  `created_by` INT(11) NOT NULL COMMENT 'Foreign key to admin.AdminID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_package_checklist_id` (`package_checklist_id`),
  INDEX `idx_created_by` (`created_by`),
  UNIQUE KEY `unique_booking_checklist` (`booking_id`, `package_checklist_id`),
  CONSTRAINT `fk_booking_checklist_completion_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`BookingID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_checklist_completion_package_checklist` FOREIGN KEY (`package_checklist_id`) REFERENCES `package_checklist` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_checklist_completion_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

