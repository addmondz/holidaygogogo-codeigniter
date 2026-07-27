ALTER TABLE `faq`
  ADD COLUMN `Slug` VARCHAR(255) NULL AFTER `Title`,
  ADD UNIQUE KEY `idx_faq_slug` (`Slug`);
