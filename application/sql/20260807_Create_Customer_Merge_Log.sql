-- Undo log for the owner-only Merge Duplicate Customers tool. Each merge writes
-- ONE row here holding an undo payload (JSON) captured inside the merge
-- transaction, so a merge can be reverted: re-point the moved bookings back to
-- their original customers, reactivate the deactivated losers, and restore any
-- overwritten booking.CustomerCode.
--
-- Revert is GUARDED (see Customer_Model::Revert_Merge): a booking is only
-- reverted if it still points to the keeper, so edits made after the merge are
-- not clobbered. Local only — AutoCount is not touched.
--
-- undo_payload shape:
--   {
--     "losers":  [9428, 9639],
--     "cust_id":  [{"BookingID": 2, "old": 9428}, ...],   -- moved via booking.CustomerID
--     "cust_id2": [{"BookingID": 4, "old": 9428}, ...],   -- moved via booking.CustomerID2
--     "codes":    [{"BookingID": 2, "old": "303-S056"}]   -- overwritten booking.CustomerCode
--   }

CREATE TABLE customer_merge_log (
  MergeID      INT AUTO_INCREMENT PRIMARY KEY,
  keeper_id    INT NOT NULL,
  admin_id     INT NULL,                                   -- who performed the merge
  undo_payload JSON NOT NULL,
  status       ENUM('MERGED','REVERTED') NOT NULL DEFAULT 'MERGED',
  created_at   DATETIME NOT NULL,
  reverted_at  DATETIME NULL,
  KEY idx_cml_status (status),
  KEY idx_cml_keeper (keeper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
