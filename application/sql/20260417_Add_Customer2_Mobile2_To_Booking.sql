-- Add optional secondary contact (Customer 2 / Mobile 2) to booking table.
-- CustomerID2 must be INT UNSIGNED to match customer.CustomerID for the FK.
ALTER TABLE `booking`
  ADD COLUMN `Customer2` VARCHAR(255) NULL DEFAULT NULL AFTER `Customer`,
  ADD COLUMN `CustomerID2` INT UNSIGNED NULL DEFAULT NULL AFTER `CustomerID`,
  ADD COLUMN `Mobile2` VARCHAR(50) NULL DEFAULT NULL AFTER `Mobile`,
  ADD COLUMN `CountryCodeID2` INT(11) NULL DEFAULT NULL AFTER `CountryCodeID`,
  ADD INDEX `idx_booking_customer_id_2` (`CustomerID2`),
  ADD INDEX `idx_booking_country_code_id_2` (`CountryCodeID2`);

ALTER TABLE `booking`
  ADD CONSTRAINT `fk_booking_customer_id_2`
  FOREIGN KEY (`CustomerID2`) REFERENCES `customer` (`CustomerID`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
