CREATE TABLE IF NOT EXISTS `faq` (
  `FAQID`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `Title`        VARCHAR(255) NOT NULL,
  `Description`  TEXT NULL,
  `Type`         ENUM('internal','external') NOT NULL DEFAULT 'internal',
  `DisplayOrder` INT(11) NOT NULL DEFAULT 0,
  `Status`       ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `InsertBy`     INT(11) NULL,
  `InsertDate`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdateBy`     INT(11) NULL,
  `UpdateDate`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`FAQID`),
  KEY `idx_faq_type` (`Type`),
  KEY `idx_faq_status` (`Status`),
  KEY `idx_faq_order` (`DisplayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
