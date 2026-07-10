-- Indexes for the "View message log" feature.
--
-- The Guest List / GHL Leads / Booking listings look up whether a contact has a
-- stored WhatsApp conversation by matching the row's phone against
-- ghl_messages.from_number / to_number (exact IN, both "6012..." and "+6012..."
-- forms). These indexes let that per-page lookup and the modal's per-contact
-- fetch use an index instead of scanning the whole messages table.

ALTER TABLE `ghl_messages`
  ADD INDEX `idx_from_number` (`from_number`),
  ADD INDEX `idx_to_number` (`to_number`);
