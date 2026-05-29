-- 1. booking: covers the main filter (Status / CancelStatus) plus the InsertDate
--    range that the listing now applies by default (last 3 months).
ALTER TABLE booking
  ADD INDEX idx_booking_listing_scope (Status, CancelStatus, InsertDate);

-- 2. guest_list: the JOIN guest_list ON BookingID = b.BookingID AND Status = 'Y'
--    runs once per booking row. A compound index keeps it index-only.
ALTER TABLE guest_list
  ADD INDEX idx_guest_list_booking_status (BookingID, Status);

-- 3. guest_list: filter by Gender on the listing.
ALTER TABLE guest_list
  ADD INDEX idx_guest_list_gender (Gender);

-- 4. guest_list: filter by Nationality on the listing.
ALTER TABLE guest_list
  ADD INDEX idx_guest_list_nationality (Nationality);

-- 5. booking: SalesAgent filter (also used by level-20/50 scope).
ALTER TABLE booking
  ADD INDEX idx_booking_sales_agent (SalesAgent);

-- 6. booking: Source filter.
ALTER TABLE booking
  ADD INDEX idx_booking_source (Source);
