-- =============================================
-- COMBINED MIGRATION: master-v2.1 (new from autocount-cloud-api)
-- Generated: 2026-03-26
-- Run this file once on the main database after merging
-- =============================================

-- =============================================
-- 1. 20250128_Create_Remark_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `remark` (
  `RemarkID` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_type` VARCHAR(50) NOT NULL COMMENT 'Type of owner (e.g., booking, customer, etc.)',
  `owner_id` INT(11) NOT NULL COMMENT 'ID of the owner record',
  `commenter_id` INT(11) NOT NULL COMMENT 'ID of the admin/user who made the comment',
  `content` TEXT NOT NULL COMMENT 'Comment content',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RemarkID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 2. 20250130_Footer_Add_Key_Contacts_And_Special_Remarks
-- =============================================
ALTER TABLE footer
  ADD COLUMN KeyContacts TEXT NULL AFTER TravelVoucherContent,
  ADD COLUMN SpecialRemarks TEXT NULL AFTER KeyContacts;

-- =============================================
-- 3. 20250131_Create_Custom_Upload_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `custom_upload` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `upload_name` VARCHAR(255) NOT NULL,
  `upload_content` VARCHAR(500) NOT NULL COMMENT 'File path',
  `created_by` INT(11) NOT NULL COMMENT 'AdminID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_custom_upload_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`BookingID`) ON DELETE CASCADE,
  CONSTRAINT `fk_custom_upload_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`AdminID`) ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 4. 20250131_Create_Guest_List_Room_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `guest_list_room` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `room_name` VARCHAR(255) NOT NULL,
  `Status` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `InsertBy` INT UNSIGNED NULL DEFAULT NULL,
  `InsertDate` DATETIME NULL DEFAULT NULL,
  `UpdateBy` INT UNSIGNED NULL DEFAULT NULL,
  `UpdateDate` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest list room assignments';

-- =============================================
-- 5. 20250131_Guest_List_Add_Room_Column
-- =============================================
ALTER TABLE guest_list
  ADD COLUMN guest_list_room_id INT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Reference to guest_list_room table'
  AFTER BookingID,
  ADD KEY `idx_guest_list_room_id` (`guest_list_room_id`);

-- =============================================
-- 6. 20251224_Add_Allow_Review_To_Booking_Table
-- =============================================
ALTER TABLE booking
  ADD COLUMN AllowReview BOOLEAN DEFAULT TRUE AFTER AutocountSyncMessage,
  ADD COLUMN CustomerReview TEXT NULL AFTER AllowReview,
  ADD COLUMN CustomerReviewTimestamp DATETIME NULL AFTER CustomerReview;

-- =============================================
-- 7. 20251225_Create_Package_Checklist_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `package_checklist` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_required` TINYINT(1) DEFAULT 0 COMMENT '1 = required, 0 = not required',
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  `UpdateBy` INT(11) DEFAULT NULL,
  `UpdateDate` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `package_checklist` (`name`, `is_required`) VALUES ('Payment Out To Supplier', 1);

-- =============================================
-- 8. 20251225_Create_Product_Package_Checklist_Table
-- =============================================
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

-- =============================================
-- 9. 20251226_Create_Guest_List_Locks_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `guest_list_locks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_list_hash` VARCHAR(64) NOT NULL COMMENT 'The gl parameter hash from URL',
  `lock_token` VARCHAR(64) NOT NULL COMMENT 'UUID token for non-logged-in users',
  `lock_owner_type` ENUM('user','guest') NOT NULL DEFAULT 'guest' COMMENT 'user = logged in, guest = anonymous',
  `lock_owner_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID if logged in, NULL for guests',
  `ip_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'MD5 hash of IP (secondary signal only)',
  `user_agent_hash` VARCHAR(64) NULL DEFAULT NULL COMMENT 'MD5 hash of User-Agent (secondary signal only)',
  `last_heartbeat_at` DATETIME NOT NULL COMMENT 'Last heartbeat timestamp',
  `lock_expires_at` DATETIME NOT NULL COMMENT 'When the lock expires (10 minutes from creation, or extended)',
  `extension_used` TINYINT(1) DEFAULT 0 COMMENT 'Whether the one-time extension has been used',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_guest_list_hash` (`guest_list_hash`),
  KEY `idx_last_heartbeat` (`last_heartbeat_at`),
  KEY `idx_lock_token` (`lock_token`),
  KEY `idx_owner` (`lock_owner_type`, `lock_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest list editing locks with heartbeat system';

-- =============================================
-- 10. 20251229_Create_Booking_Checklist_Completion_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `booking_checklist_completion` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `package_checklist_id` INT(11) NOT NULL COMMENT 'Foreign key to package_checklist.ID',
  `created_by` INT(11) NOT NULL COMMENT 'Foreign key to admin.AdminID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_package_checklist_id` (`package_checklist_id`),
  INDEX `idx_created_by` (`created_by`),
  UNIQUE KEY `unique_booking_checklist` (`booking_id`, `package_checklist_id`),
  CONSTRAINT `fk_booking_checklist_completion_booking` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`BookingID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_checklist_completion_package_checklist` FOREIGN KEY (`package_checklist_id`) REFERENCES `package_checklist` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_checklist_completion_admin` FOREIGN KEY (`created_by`) REFERENCES `admin` (`AdminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 11. 20260103_Create_Notifications_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `notification` (
  `NotificationID` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Admin ID who should receive this notification',
  `type` VARCHAR(50) NOT NULL DEFAULT 'remark' COMMENT 'Type of notification (e.g., remark)',
  `owner_type` VARCHAR(50) NOT NULL COMMENT 'Type of owner (e.g., booking)',
  `owner_id` INT(11) NOT NULL COMMENT 'ID of the owner record (e.g., BookingID)',
  `remark_id` INT(11) NULL DEFAULT NULL COMMENT 'Related remark ID if applicable',
  `message` TEXT NOT NULL COMMENT 'Notification message/content',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification has been read',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME NULL DEFAULT NULL COMMENT 'When notification was marked as read',
  PRIMARY KEY (`NotificationID`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_owner` (`owner_type`, `owner_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications for booking remarks';

-- =============================================
-- 12. 20260117_Add_Remark_Type_To_Remark_Table
-- =============================================
ALTER TABLE remark
ADD COLUMN type INT DEFAULT 1;

-- =============================================
-- 13. 20260120_Add_PBC_PBO_To_Booking_Status
-- =============================================
ALTER TABLE `booking`
  MODIFY COLUMN `Status` ENUM('Y','N','P','PP','PT','OG','PTV','PBC','PBO') NOT NULL DEFAULT 'PBC' COLLATE 'utf8mb3_general_ci'
  COMMENT 'Booking status: PBC=PENDING BC CONFIRMATION, P=PENDING PAYMENT, PBO=PENDING BOOKING OPERATION, PP=PARTIAL PAYMENT, PTV=PENDING TRAVEL VOUCHER, PGL=PENDING GUEST LIST (derived), PT=PENDING TRAVEL, OG=ON-GOING, Y=COMPLETED, C=CANCELLED (via CancelStatus), N=LOST';

-- =============================================
-- 14. 20260120_Create_Booking_Status_Log_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `booking_status_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL COMMENT 'Foreign key to booking.BookingID',
  `from_status` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Previous status code (NULL for initial creation)',
  `to_status` VARCHAR(10) NOT NULL COMMENT 'New status code',
  `created_by` INT(11) NOT NULL DEFAULT 0 COMMENT 'AdminID who made the change, 0 = system',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when status changed',
  `description` TEXT NULL DEFAULT NULL COMMENT 'Optional description of the status change',
  `show_to_customer` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = show in customer portal, 0 = hide',
  PRIMARY KEY (`id`),
  INDEX `idx_booking_id` (`booking_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_show_to_customer` (`show_to_customer`),
  CONSTRAINT `fk_booking_status_log_booking`
    FOREIGN KEY (`booking_id`)
    REFERENCES `booking` (`BookingID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks booking status changes for timeline display';

-- =============================================
-- 15. 20260125_Add_Deposit_Percentage_And_Amount_To_Booking
-- =============================================
ALTER TABLE booking
  ADD COLUMN DepositPercentage INT NOT NULL DEFAULT 0
  COMMENT 'Deposit percentage (0-100)'
  AFTER NetTotal;

-- =============================================
-- 16. 20260125_Guest_List_Add_Passport_And_Dietary_Fields
-- =============================================
ALTER TABLE guest_list
  ADD COLUMN PassportIssueDate DATE NULL
  COMMENT 'Passport issue date'
  AFTER PassportNumber;

ALTER TABLE guest_list
  ADD COLUMN PassportExpiryDate DATE NULL
  COMMENT 'Passport expiry date'
  AFTER PassportIssueDate;

ALTER TABLE guest_list
  ADD COLUMN PassportCopy VARCHAR(255) NULL
  COMMENT 'Passport copy file path/name'
  AFTER PassportExpiryDate;

ALTER TABLE guest_list
  ADD COLUMN DietaryRequirement TEXT NULL
  COMMENT 'Dietary requirements and restrictions'
  AFTER PassportCopy;

CREATE INDEX IX_guest_list_PassportIssueDate ON guest_list (PassportIssueDate);
CREATE INDEX IX_guest_list_PassportExpiryDate ON guest_list (PassportExpiryDate);

-- =============================================
-- 17. 20260126_Add_BC_Approved_Fields_To_Booking
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `bc_approved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'BC approval status: 0 = not approved, 1 = approved',
  ADD COLUMN `bc_approval_admin_id` INT(11) NULL DEFAULT NULL COMMENT 'Admin ID who approved the BC',
  ADD INDEX `idx_bc_approved` (`bc_approved`),
  ADD INDEX `idx_bc_approval_admin_id` (`bc_approval_admin_id`);

-- =============================================
-- 18. 20260126_Add_BC_Approval_Date_To_Booking
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `bc_approval_date` DATETIME NULL DEFAULT NULL COMMENT 'Date and time BC was approved' AFTER `bc_approved`;

-- =============================================
-- 19. 20260126_Add_PGL_To_Booking_Status
-- =============================================
ALTER TABLE `booking`
  MODIFY COLUMN `Status` ENUM('Y','N','P','PP','PT','OG','PTV','PBC','PBO','PGL') NOT NULL DEFAULT 'PBC' COLLATE 'utf8mb3_general_ci'
  COMMENT 'Booking status: PBC=PENDING BC CONFIRMATION, P=PENDING PAYMENT, PBO=PENDING BOOKING OPERATION, PP=PARTIAL PAYMENT, PTV=PENDING TRAVEL VOUCHER, PGL=PENDING GUEST LIST, PT=PENDING TRAVEL, OG=ON-GOING, Y=COMPLETED, C=CANCELLED (via CancelStatus), N=LOST';

-- =============================================
-- 20. 20260126_Update_Admin_Access_Control_And_BC_Approved
-- =============================================
UPDATE `admin`
SET `AccessControl` = 'AB, VB'
WHERE `Level` = '20'
  AND `AccessControl` = 'VB';

UPDATE `booking`
SET `bc_approved` = 1
WHERE `bc_approved` = 0;

-- =============================================
-- 21. 20260212_Guest_List_Add_NomineeContactNumber (SKIPPED - already exists in autocount-cloud-api)
-- =============================================

-- =============================================
-- 22. 20260227_Add_TeamLeadID_To_Admin
-- =============================================
ALTER TABLE `admin` ADD COLUMN `TeamLeadID` INT NULL DEFAULT NULL AFTER `AccessControl`;

ALTER TABLE `admin` MODIFY COLUMN `Level` ENUM('10','20','25','30','40','50') NOT NULL;

-- =============================================
-- 23. 20260227_Invoice_Split_Pax
-- =============================================
CREATE TABLE IF NOT EXISTS `invoice_split_pax` (
    `InvoiceSplitPaxID` INT(11) NOT NULL AUTO_INCREMENT,
    `BookingID` INT(11) NOT NULL,
    `PaxName` VARCHAR(255) NOT NULL,
    `TIN` VARCHAR(50) NOT NULL,
    `SubtotalAmount` DECIMAL(10,2) DEFAULT 0.00,
    `DiscountAmount` DECIMAL(10,2) DEFAULT 0.00,
    `NetAmount` DECIMAL(10,2) DEFAULT 0.00,
    `SortOrder` INT(11) DEFAULT 0,
    `Status` CHAR(1) DEFAULT 'Y',
    `InsertDate` DATETIME DEFAULT NULL,
    `UpdateDate` DATETIME DEFAULT NULL,
    PRIMARY KEY (`InvoiceSplitPaxID`),
    KEY `idx_booking_id` (`BookingID`),
    KEY `idx_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoice_split_pax_product` (
    `InvoiceSplitPaxProductID` INT(11) NOT NULL AUTO_INCREMENT,
    `InvoiceSplitPaxID` INT(11) NOT NULL,
    `BookingProductID` INT(11) NOT NULL,
    `Quantity` DECIMAL(10,2) DEFAULT 0.00,
    `UnitPrice` DECIMAL(10,2) DEFAULT 0.00,
    `Amount` DECIMAL(10,2) DEFAULT 0.00,
    `Status` CHAR(1) DEFAULT 'Y',
    PRIMARY KEY (`InvoiceSplitPaxProductID`),
    KEY `idx_invoice_split_pax_id` (`InvoiceSplitPaxID`),
    KEY `idx_booking_product_id` (`BookingProductID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 24. 20260305_Add_BookingOP_To_Booking
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `BookingOP` INT(11) NULL DEFAULT NULL COMMENT 'Booking operation admin ID' AFTER `SalesAgent`,
  ADD INDEX `idx_booking_op` (`BookingOP`);

ALTER TABLE `booking`
  ADD CONSTRAINT `fk_booking_booking_op_admin`
  FOREIGN KEY (`BookingOP`) REFERENCES `admin` (`AdminID`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

-- =============================================
-- 25. 20260305_Add_Customer_Portal_Visible_To_Booking
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `customer_portal_visible` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Customer portal visibility: 0 = hidden, 1 = visible once BC approved'
  AFTER `bc_approval_date`;

CREATE INDEX `idx_customer_portal_visible` ON `booking` (`customer_portal_visible`);

UPDATE `booking`
SET `customer_portal_visible` = 1
WHERE `bc_approved` = 1;

-- =============================================
-- 26. 20260305_Add_Is_Submitted_To_Booking
-- =============================================
ALTER TABLE `booking`
  ADD COLUMN `is_submitted` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Guest list submitted flag: 0 = not submitted, 1 = submitted'
  AFTER `LockStatus`;

-- =============================================
-- 27. 20260305_Migration (complex)
-- =============================================
ALTER TABLE `booking_checklist_completion` ADD COLUMN `product_id` INT(11) NOT NULL DEFAULT 0 AFTER `booking_id`;

ALTER TABLE `booking_checklist_completion` DROP INDEX `unique_booking_checklist`;

ALTER TABLE `booking_checklist_completion` ADD UNIQUE KEY `unique_booking_product_checklist` (`booking_id`, `product_id`, `package_checklist_id`);

UPDATE package_checklist SET name = 'Payment Out To Supplier (full)' WHERE ID = 1;

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

-- =============================================
-- 28. 20260310_Create_Remark_Read_Status_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `remark_read_status` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `remark_type` TINYINT(1) NOT NULL COMMENT '1=INTERNAL, 2=CUSTOMER',
  `last_read_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_type` (`user_id`, `remark_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 29. 20260311_Create_Remark_User_Read_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `remark_user_read` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `remark_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_remark_user` (`remark_id`, `user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 30. 20260312_Create_Cancellation_Reason_Table
-- =============================================
CREATE TABLE IF NOT EXISTS `cancellation_reason` (
  `CancellationReasonID` INT(11) NOT NULL AUTO_INCREMENT,
  `Name` VARCHAR(255) NOT NULL,
  `Status` CHAR(1) NOT NULL DEFAULT 'Y',
  `InsertBy` INT(11) DEFAULT NULL,
  `InsertDate` DATETIME DEFAULT NULL,
  `UpdateBy` INT(11) DEFAULT NULL,
  `UpdateDate` DATETIME DEFAULT NULL,
  PRIMARY KEY (`CancellationReasonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- 31. 20260312_Add_CancellationReasonID_To_Booking
-- =============================================
ALTER TABLE `booking` ADD COLUMN `CancellationReasonID` INT(11) DEFAULT NULL AFTER `CancelStatus`;

-- =============================================
-- 32. 20260312_Add_Include_Booking_Filter_To_Package_Checklist
-- =============================================
ALTER TABLE `package_checklist`
ADD COLUMN `include_booking_filter` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '1 = show as booking filter option, 0 = do not show'
AFTER `is_required`;

-- =============================================
-- 33. 20260312_Add_Is_Child_Or_Infant_To_Product
-- =============================================
ALTER TABLE `product` ADD COLUMN `is_child_or_infant` TINYINT(1) NOT NULL DEFAULT 0 AFTER `SupplierPrice`;

-- =============================================
-- 34. 20260312_Fix_Customer_Phone_Number_Prefix
-- =============================================
UPDATE customer
SET phone_number = CONCAT('+60', SUBSTRING(phone_number, 5))
WHERE phone_number LIKE '+600%';

-- =============================================
-- 35. 20260312_Move_PaymentOutSupplier_To_Booking_Product
-- =============================================
ALTER TABLE `booking_product`
ADD COLUMN `PaymentOutSupplierFull` DATE NULL AFTER `Total`,
ADD COLUMN `PaymentOutSupplierDeposit` DATE NULL AFTER `PaymentOutSupplierFull`;

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

-- =============================================
-- 36. 20260313_Add_BookingProductID_To_Payment
-- =============================================
ALTER TABLE `payment`
ADD COLUMN `BookingProductID` INT(11) NULL AFTER `SupplierID`;

-- =============================================
-- 37. 20260325_Add_Disable_Checklist_Payment_Out
-- =============================================
ALTER TABLE `booking_product` ADD COLUMN `disable_checklist_payment_out` TINYINT(1) NOT NULL DEFAULT 0 AFTER `PaymentOutSupplierDeposit`;

-- =============================================
-- 38. 20260325_Add_Has_Supplier_Deposit_To_Product
-- =============================================
ALTER TABLE `product` ADD COLUMN `has_supplier_deposit` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_child_or_infant`;

UPDATE product p
SET p.has_supplier_deposit = 1
WHERE p.ProductID IN (
    SELECT DISTINCT bp.ProductID
    FROM booking_product bp
    WHERE bp.PaymentOutSupplierDeposit IS NOT NULL
);

-- =============================================
-- 39. 20260326_Add_SnapshotPDF_To_Booking_Log
-- =============================================
ALTER TABLE `booking_log` ADD COLUMN `SnapshotPDF` VARCHAR(255) DEFAULT NULL AFTER `NewData`;

-- =============================================
-- 40. 20260329_Payment_Add_Agent_Commission_From_Supplier_Type
-- =============================================
ALTER TABLE payment
  MODIFY COLUMN Type ENUM(
    'ADDITIONAL PAYMENT','AGENT COMMISSION','AGENT COMMISSION FROM SUPPLIER',
    'BANK CHARGES','CUSTOMER REFUND','CREDIT CARD CHARGES','DEPOSIT','FULL',
    'ONE-TIME PAYMENT','SUPPLIER PAYMENT','SUPPLIER REFUND'
  ) NULL;
