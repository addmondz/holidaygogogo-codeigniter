-- Unit cost is no longer stored on the global item master — it is entered per
-- costing in the wizard cost table instead. Drop the now-unused column.

ALTER TABLE `costing_items`
    DROP COLUMN `default_unit_cost`;
