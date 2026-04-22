CREATE TABLE `customer_type` (
  `CustomerTypeID` INT AUTO_INCREMENT PRIMARY KEY,
  `Name` VARCHAR(50) NOT NULL,
  `Status` ENUM('Y','N') DEFAULT 'Y',
  `InsertBy` INT NULL,
  `InsertDate` DATETIME NULL,
  `UpdateBy` INT NULL,
  `UpdateDate` DATETIME NULL,
  UNIQUE KEY `uq_customer_type_name` (`Name`)
);

INSERT INTO `customer_type` (`Name`, `InsertDate`) VALUES
  ('Company',   NOW()),
  ('Chinese',   NOW()),
  ('Malay',     NOW()),
  ('Indian',    NOW()),
  ('Foreigner', NOW());

CREATE INDEX `idx_customer_customer_type` ON `customer` (`customer_type`);
