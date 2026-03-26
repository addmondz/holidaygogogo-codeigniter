ALTER TABLE payment
  ADD COLUMN IF NOT EXISTS AutocountSyncMessage TEXT AFTER AutocountSyncStatus;