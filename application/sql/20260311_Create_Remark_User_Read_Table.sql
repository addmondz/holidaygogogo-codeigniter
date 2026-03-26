CREATE TABLE IF NOT EXISTS `remark_user_read` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `remark_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_remark_user` (`remark_id`, `user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;