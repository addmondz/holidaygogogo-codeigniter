ALTER TABLE `package_checklist`
ADD COLUMN `include_booking_filter` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '1 = show as booking filter option, 0 = do not show'
AFTER `is_required`;
