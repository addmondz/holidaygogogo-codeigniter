ALTER TABLE `faq_tag`
  ADD COLUMN `IsDefault` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `Name`,
  ADD KEY `idx_faq_tag_is_default` (`IsDefault`);
