-- Create guest_list_room table
CREATE TABLE IF NOT EXISTS `guest_list_room` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `room_name` VARCHAR(255) NOT NULL,
  `Status` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `InsertBy` INT UNSIGNED NULL DEFAULT NULL,
  `InsertDate` DATETIME NULL DEFAULT NULL,
  `UpdateBy` INT UNSIGNED NULL DEFAULT NULL,
  `UpdateDate` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest list room assignments';

