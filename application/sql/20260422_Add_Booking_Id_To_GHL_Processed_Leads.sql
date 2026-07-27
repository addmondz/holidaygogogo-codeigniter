ALTER TABLE `ghl_processed_leads`
ADD COLUMN `booking_id` INT(11) NULL DEFAULT NULL AFTER `is_converted`,
ADD KEY `idx_booking_id` (`booking_id`);
