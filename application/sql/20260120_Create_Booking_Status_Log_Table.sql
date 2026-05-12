-- Migration: Create booking_status_log table
-- Date: 2026-01-20
-- Description: Tracks booking status changes with timeline for customer visibility

CREATE TABLE IF NOT EXISTS `booking_status_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL COMMENT 'Foreign key to booking.BookingID',
  `from_status` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Previous status code (NULL for initial creation)',
  `to_status` VARCHAR(10) NOT NULL COMMENT 'New status code',
  `created_by` INT(11) NOT NULL DEFAULT 0 COMMENT 'AdminID who made the change, 0 = system',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when status changed',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Optional description of the status change',
  `show_to_customer` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = show in customer portal, 0 = hide',
  PRIMARY KEY (`id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_show_to_customer` (`show_to_customer`),
  CONSTRAINT `fk_booking_status_log_booking` 
    FOREIGN KEY (`booking_id`) 
    REFERENCES `booking` (`BookingID`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks booking status changes for timeline display';
