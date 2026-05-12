-- 1. Drop the old column (only if it exists - remove manually if error)
-- ALTER TABLE booking DROP COLUMN AutocountSyncStatus;

-- 2. Add new column AutocountSyncAction
ALTER TABLE booking
  ADD COLUMN IF NOT EXISTS AutocountSyncAction CHAR(1) NULL
  COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status'
  AFTER Status;

-- 3. Add new column AutocountSyncStatus
ALTER TABLE booking
  ADD COLUMN IF NOT EXISTS AutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P'
  COMMENT 'P: Pending, S: Synced, F: Failed'
  AFTER AutocountSyncAction;

-- 4. Add new column AutocountSyncMessage
ALTER TABLE booking
  ADD COLUMN IF NOT EXISTS AutocountSyncMessage TEXT AFTER AutocountSyncStatus;

-- 5. Create indexes
CREATE INDEX IF NOT EXISTS IX_booking_AutocountSyncAction ON booking (AutocountSyncAction);
CREATE INDEX IF NOT EXISTS IX_booking_AutocountSyncStatus ON booking (AutocountSyncStatus);
