-- =============================================
-- PAYMENT TABLE PATCH FOR AUTOCOMMIT SYNC COLUMNS
-- =============================================

-- 1. Drop old column AutocountSyncStatus (run only if it exists)
-- ALTER TABLE payment DROP COLUMN AutocountSyncStatus;

-- 2. Add AutocountSyncAction column
ALTER TABLE payment
  ADD COLUMN IF NOT EXISTS AutocountSyncAction CHAR(1) NULL
  COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status'
  AFTER Status;

-- 3. Add AutocountSyncStatus column
ALTER TABLE payment
  ADD COLUMN IF NOT EXISTS AutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P'
  COMMENT 'P: Pending, S: Synced, F: Failed'
  AFTER AutocountSyncAction;

-- 4. Create indexes
CREATE INDEX IF NOT EXISTS IX_payment_AutocountSyncAction ON payment (AutocountSyncAction);
CREATE INDEX IF NOT EXISTS IX_payment_AutocountSyncStatus ON payment (AutocountSyncStatus);
