ALTER TABLE supplier
  ADD COLUMN IF NOT EXISTS AutocountSyncAction CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status' AFTER Status,
  ADD COLUMN IF NOT EXISTS AutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed' AFTER AutocountSyncAction,
  ADD COLUMN IF NOT EXISTS AutocountSyncMessage TEXT AFTER AutocountSyncStatus,
  ADD COLUMN IF NOT EXISTS SupplierCode VARCHAR(255) NULL AFTER Phone;

CREATE INDEX IF NOT EXISTS IX_supplier_AutocountSyncAction ON supplier (AutocountSyncAction);
CREATE INDEX IF NOT EXISTS IX_supplier_AutocountSyncStatus ON supplier (AutocountSyncStatus);
CREATE INDEX IF NOT EXISTS IX_supplier_SupplierCode ON supplier (SupplierCode);


ALTER TABLE booking
  ADD COLUMN IF NOT EXISTS CustomerAutocountSyncAction CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status' AFTER Status,
  ADD COLUMN IF NOT EXISTS CustomerAutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed' AFTER CustomerAutocountSyncAction,
  ADD COLUMN IF NOT EXISTS CustomerAutocountSyncMessage TEXT AFTER CustomerAutocountSyncStatus,
  ADD COLUMN IF NOT EXISTS CustomerCode VARCHAR(255) NULL AFTER Customer;

CREATE INDEX IF NOT EXISTS IX_booking_customer_AutocountSyncAction ON booking (CustomerAutocountSyncAction);
CREATE INDEX IF NOT EXISTS IX_booking_customer_AutocountSyncStatus ON booking (CustomerAutocountSyncStatus);
CREATE INDEX IF NOT EXISTS IX_booking_customer_CustomerCode ON booking (CustomerCode);
