CREATE TABLE IF NOT EXISTS `category_product` (
  `CategoryProductID` INT(11) NOT NULL AUTO_INCREMENT,
  `CategoryID` INT(11) NOT NULL,
  `ProductID` INT(11) NOT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT(11) NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT(11) NULL,
  `UpdateDate` DATETIME NULL,
  PRIMARY KEY (`CategoryProductID`),
  KEY `idx_category_product_category` (`CategoryID`),
  KEY `idx_category_product_product` (`ProductID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
