ALTER TABLE `product` ADD COLUMN `is_child_or_infant` TINYINT(1) NOT NULL DEFAULT 0 AFTER `SupplierPrice`;
