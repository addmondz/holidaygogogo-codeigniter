-- =============================================
-- CREATE CUSTOM UPLOAD TABLE
-- =============================================

CREATE TABLE IF NOT EXISTS `custom_upload` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `upload_name` VARCHAR(255) NOT NULL,
  `upload_content` VARCHAR(500) NOT NULL COMMENT 'File path',
  `created_by` INT(11) NOT NULL COMMENT 'AdminID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_custom_upload_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`BookingID`) ON DELETE CASCADE,
  CONSTRAINT `fk_custom_upload_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`AdminID`) ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

