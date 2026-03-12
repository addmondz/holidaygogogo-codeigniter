-- Move PaymentOutSupplierFull and PaymentOutSupplierDeposit from booking to booking_product
ALTER TABLE `booking_product`
ADD COLUMN `PaymentOutSupplierFull` DATE NULL AFTER `Total`,
ADD COLUMN `PaymentOutSupplierDeposit` DATE NULL AFTER `PaymentOutSupplierFull`;

-- Migrate existing data: copy booking-level dates to all active products of that booking
UPDATE booking_product bp
INNER JOIN booking b ON bp.BookingID = b.BookingID
SET bp.PaymentOutSupplierFull = b.PaymentOutSupplierFull
WHERE b.PaymentOutSupplierFull IS NOT NULL
  AND bp.Status = 'Y';

UPDATE booking_product bp
INNER JOIN booking b ON bp.BookingID = b.BookingID
SET bp.PaymentOutSupplierDeposit = b.PaymentOutSupplierDeposit
WHERE b.PaymentOutSupplierDeposit IS NOT NULL
  AND bp.Status = 'Y';
