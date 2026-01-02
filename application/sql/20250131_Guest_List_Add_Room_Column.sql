-- Add guest_list_room_id column to guest_list table
ALTER TABLE guest_list
  ADD COLUMN guest_list_room_id INT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Reference to guest_list_room table'
  AFTER BookingID,
  ADD KEY `idx_guest_list_room_id` (`guest_list_room_id`);

