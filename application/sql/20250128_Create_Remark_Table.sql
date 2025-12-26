-- Create remark table for internal comments
CREATE TABLE IF NOT EXISTS `remark` (
  `RemarkID` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_type` VARCHAR(50) NOT NULL COMMENT 'Type of owner (e.g., booking, customer, etc.)',
  `owner_id` INT(11) NOT NULL COMMENT 'ID of the owner record',
  `commenter_id` INT(11) NOT NULL COMMENT 'ID of the admin/user who made the comment',
  `content` TEXT NOT NULL COMMENT 'Comment content',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RemarkID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

