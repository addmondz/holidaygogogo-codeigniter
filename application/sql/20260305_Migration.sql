-- Add product_id column to booking_checklist_completion table
-- This fixes checklist completions leaking across products that share the same checklist IDs

ALTER TABLE `booking_checklist_completion` ADD COLUMN `product_id` INT(11) NOT NULL DEFAULT 0 AFTER `booking_id`;

ALTER TABLE `booking_checklist_completion` DROP INDEX `unique_booking_checklist`;

ALTER TABLE `booking_checklist_completion` ADD UNIQUE KEY `unique_booking_product_checklist` (`booking_id`, `product_id`, `package_checklist_id`);

-- Rename existing "Payment Out To Supplier" to clarify it's for full payment
UPDATE package_checklist SET name = 'Payment Out To Supplier (full)' WHERE ID = 1;

-- Add deposit variant (not required - won't auto-add to all products)
INSERT INTO package_checklist (name, is_required) VALUES ('Payment Out To Supplier (deposit)', 0);

ALTER TABLE `booking`
ADD COLUMN `PaymentOutSupplierFull` DATE NULL AFTER `AdditionalPaymentDeadline`,
ADD COLUMN `PaymentOutSupplierDeposit` DATE NULL AFTER `PaymentOutSupplierFull`;

ALTER TABLE `invoice_split_pax`
    ADD COLUMN `Email` VARCHAR(255) NOT NULL DEFAULT '' AFTER `TIN`,
    ADD COLUMN `Address` TEXT NOT NULL AFTER `Email`,
    ADD COLUMN `PhoneNumber` VARCHAR(50) NOT NULL DEFAULT '' AFTER `Address`;

ALTER TABLE `guest_list_room` ADD COLUMN `adult_count` INT UNSIGNED DEFAULT 0 AFTER `room_name`;
ALTER TABLE `guest_list_room` ADD COLUMN `child_count` INT UNSIGNED DEFAULT 0 AFTER `adult_count`;
ALTER TABLE `guest_list_room` ADD COLUMN `infant_count` INT UNSIGNED DEFAULT 0 AFTER `child_count`;

ALTER TABLE `guest_list` ADD COLUMN `NomineeContact` VARCHAR(50) NULL AFTER `Relationship`;

ALTER TABLE `booking` ADD COLUMN `KeyContacts` TEXT NULL AFTER `TravelVoucherFooter`;
ALTER TABLE `booking` ADD COLUMN `SpecialRemarks` TEXT NULL AFTER `KeyContacts`;
