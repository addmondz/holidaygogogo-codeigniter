-- Per-FAQ unique page: each FAQ gets its own URL at /faq/<slug>.
-- Slug is a URL-safe, lowercased form of Title (see Faq_Model::Slugify), unique
-- across FAQs. Nullable so the column can be added without a value; MySQL allows
-- multiple NULLs under a UNIQUE key. Existing rows are backfilled lazily by
-- Faq_Model::Backfill_Slugs() (invoked from the FAQ listing), which stamps a
-- unique slug onto any row whose Slug is still NULL/empty.

ALTER TABLE `faq`
  ADD COLUMN `Slug` VARCHAR(255) NULL AFTER `Title`,
  ADD UNIQUE KEY `idx_faq_slug` (`Slug`);
