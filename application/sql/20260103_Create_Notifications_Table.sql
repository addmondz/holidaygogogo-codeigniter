-- Create notifications table for booking remarks
CREATE TABLE IF NOT EXISTS `notification` (
  `NotificationID` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Admin ID who should receive this notification',
  `type` VARCHAR(50) NOT NULL DEFAULT 'remark' COMMENT 'Type of notification (e.g., remark)',
  `owner_type` VARCHAR(50) NOT NULL COMMENT 'Type of owner (e.g., booking)',
  `owner_id` INT(11) NOT NULL COMMENT 'ID of the owner record (e.g., BookingID)',
  `remark_id` INT(11) NULL DEFAULT NULL COMMENT 'Related remark ID if applicable',
  `message` TEXT NOT NULL COMMENT 'Notification message/content',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification has been read',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME NULL DEFAULT NULL COMMENT 'When notification was marked as read',
  PRIMARY KEY (`NotificationID`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_owner` (`owner_type`, `owner_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications for booking remarks';

