ALTER TABLE `booking_product` ADD COLUMN `disable_checklist_payment_out` TINYINT(1) NOT NULL DEFAULT 0 AFTER `PaymentOutSupplierDeposit`;
