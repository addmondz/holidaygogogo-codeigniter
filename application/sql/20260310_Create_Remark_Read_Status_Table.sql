CREATE TABLE IF NOT EXISTS `remark_read_status` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `remark_type` TINYINT(1) NOT NULL COMMENT '1=INTERNAL, 2=CUSTOMER',
  `last_read_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_type` (`user_id`, `remark_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
