-- Migration: Create booking_customer_intake and booking_customer_intake_room tables
-- Date: 2026-05-23
-- Description: Stores customer-submitted intake data (from the shareable customer intake link)
--              that pre-populates the booking form, and the time of submission so that the
--              response time to PBC ("Pending BC Confirmation") can be computed.

CREATE TABLE IF NOT EXISTS `booking_customer_intake` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL COMMENT 'Foreign key to booking.BookingID',
  `booking_name` VARCHAR(255) NOT NULL COMMENT 'Full name as per IC/Passport',
  `contact_number` VARCHAR(50) NOT NULL,
  `ic_passport_no` VARCHAR(50) NOT NULL,
  `travel_date` DATE NOT NULL,
  `special_remarks` TEXT NULL DEFAULT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the customer submitted the form',
  `locked` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'One-shot lock: 1 = link no longer editable',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_booking` (`booking_id`),
  INDEX `idx_intake_submitted_at` (`submitted_at`),
  CONSTRAINT `fk_intake_booking`
    FOREIGN KEY (`booking_id`)
    REFERENCES `booking` (`BookingID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Customer-supplied booking intake data (one row per booking)';

CREATE TABLE IF NOT EXISTS `booking_customer_intake_room` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `intake_id` INT(11) NOT NULL COMMENT 'Foreign key to booking_customer_intake.id',
  `room_type` VARCHAR(255) NOT NULL,
  `adult_count` INT(11) NOT NULL DEFAULT 0,
  `child_ages` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Comma-separated child ages, e.g. "5,7"',
  `baby_ages` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Comma-separated baby ages, e.g. "1,2"',
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_intake_room_intake` (`intake_id`),
  CONSTRAINT `fk_intake_room_intake`
    FOREIGN KEY (`intake_id`)
    REFERENCES `booking_customer_intake` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rooms attached to a customer intake submission (with per-child/baby ages)';
