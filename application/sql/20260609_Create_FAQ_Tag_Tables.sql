-- FAQ Tag master list (managed under Settings >> FAQ Tag) and the
-- many-to-many map that links each FAQ to the tags selected on the FAQ form.

CREATE TABLE IF NOT EXISTS `faq_tag` (
  `FAQTagID`   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`       VARCHAR(100) NOT NULL,
  `Status`     ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `InsertBy`   INT(11) NULL,
  `InsertDate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `UpdateBy`   INT(11) NULL,
  `UpdateDate` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`FAQTagID`),
  KEY `idx_faq_tag_status` (`Status`),
  KEY `idx_faq_tag_name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faq_tag_map` (
  `FAQTagMapID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FAQID`       INT(11) UNSIGNED NOT NULL,
  `FAQTagID`    INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`FAQTagMapID`),
  UNIQUE KEY `uq_faq_tag_map` (`FAQID`, `FAQTagID`),
  KEY `idx_faq_tag_map_faq` (`FAQID`),
  KEY `idx_faq_tag_map_tag` (`FAQTagID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
