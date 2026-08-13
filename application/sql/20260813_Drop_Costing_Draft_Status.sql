-- Costing packages/bookings no longer use the "draft" status — only
-- active/inactive remain. Flip the column default to 'active' and convert any
-- existing 'draft' rows to 'active'.

ALTER TABLE `costing_packages`
    ALTER `status` SET DEFAULT 'active';

ALTER TABLE `costing_bookings`
    ALTER `status` SET DEFAULT 'active';

UPDATE `costing_packages` SET `status` = 'active' WHERE `status` = 'draft';
UPDATE `costing_bookings` SET `status` = 'active' WHERE `status` = 'draft';
