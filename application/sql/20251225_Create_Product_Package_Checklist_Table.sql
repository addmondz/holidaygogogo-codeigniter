-- Create product_package_checklist table
CREATE TABLE IF NOT EXISTS `product_package_checklist` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL COMMENT 'Foreign key to product.ProductID',
  `package_checklist_json` JSON NULL COMMENT 'JSON array of package checklist IDs in order of display',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product` (`product_id`),
  INDEX `idx_product_id` (`product_id`),
  CONSTRAINT `fk_product_package_checklist_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`ProductID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

