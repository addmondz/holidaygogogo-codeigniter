-- Add BookingOP column to booking table and link it to admin table
ALTER TABLE `booking`
  ADD COLUMN `BookingOP` INT(11) NULL DEFAULT NULL COMMENT 'Booking operation admin ID' AFTER `SalesAgent`,
  ADD INDEX `idx_booking_op` (`BookingOP`);

ALTER TABLE `booking`
  ADD CONSTRAINT `fk_booking_booking_op_admin`
  FOREIGN KEY (`BookingOP`) REFERENCES `admin` (`AdminID`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
