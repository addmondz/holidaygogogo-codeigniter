-- =============================================
-- BOOKING TABLE PATCH FOR AUTOCOMMIT SYNC COLUMNS
-- =============================================

-- 1. Drop index on AutocountSyncStatus (if it exists)
DROP INDEX IF EXISTS AutocountSyncStatus ON booking;

-- 2. Drop old AutocountSyncStatus column (if it exists)
ALTER TABLE booking 
    DROP COLUMN IF EXISTS AutocountSyncStatus;

-- 3. Add AutocountSyncAction column
ALTER TABLE booking
ADD COLUMN IF NOT EXISTS AutocountSyncAction CHAR(1) NULL 
    COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status'
    AFTER Status;

-- 4. Add AutocountSyncStatus column
ALTER TABLE booking
ADD COLUMN IF NOT EXISTS AutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P'
    COMMENT 'P: Pending, S: Synced, F: Failed'
    AFTER AutocountSyncAction;

-- 5. Create index on AutocountSyncAction (if not exists)
CREATE INDEX IF NOT EXISTS IX_booking_AutocountSyncAction 
    ON booking (AutocountSyncAction);

-- 6. Create index on AutocountSyncStatus (if not exists)
CREATE INDEX IF NOT EXISTS IX_booking_AutocountSyncStatus 
    ON booking (AutocountSyncStatus);
