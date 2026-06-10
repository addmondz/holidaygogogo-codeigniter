-- FAQ display ordering is no longer used (FAQs are listed by FAQID and reached
-- via their own /faq/<slug> page). Drop the column; its single-column index
-- idx_faq_order is dropped automatically with it.

ALTER TABLE `faq` DROP COLUMN `DisplayOrder`;
