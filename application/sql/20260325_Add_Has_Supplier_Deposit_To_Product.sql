ALTER TABLE `product` ADD COLUMN `has_supplier_deposit` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_child_or_infant`;

-- Backfill: Set has_supplier_deposit = 1 for products used in bookings with supplier deposit dates
UPDATE product p
SET p.has_supplier_deposit = 1
WHERE p.ProductID IN (
    SELECT DISTINCT bp.ProductID
    FROM booking_product bp
    WHERE bp.PaymentOutSupplierDeposit IS NOT NULL
);
