-- Many-to-many map linking each FAQ to the destination categories selected on
-- the FAQ form. Destinations are category rows where IsDestination = 'YES'
-- (same source the booking form uses).

CREATE TABLE IF NOT EXISTS `faq_destination_map` (
  `FAQDestinationMapID` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `FAQID`               INT(11) UNSIGNED NOT NULL,
  `CategoryID`          INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`FAQDestinationMapID`),
  UNIQUE KEY `uq_faq_destination_map` (`FAQID`, `CategoryID`),
  KEY `idx_faq_destination_map_faq` (`FAQID`),
  KEY `idx_faq_destination_map_category` (`CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
