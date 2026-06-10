-- FAQ tags moved from the whole-FAQ level to each sub-Q&A item: tag ids now
-- live inside the per-item JSON in faq.Description (see Faq_Model::Build_Items /
-- Decode_Items). The whole-FAQ junction table is therefore no longer used.
--
-- The faq_tag master list (the tag names, managed under Settings >> FAQ Tag)
-- stays - only the FAQID<->FAQTagID map is dropped. Any existing whole-FAQ tag
-- assignments are NOT auto-migrated onto items; re-tag the affected FAQs in the
-- form. (The FAQ tag feature is one day old, so this is dev/test data only.)

DROP TABLE IF EXISTS `faq_tag_map`;
