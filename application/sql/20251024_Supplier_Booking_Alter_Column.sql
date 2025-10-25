ALTER TABLE supplier
  ADD COLUMN AutocountSyncAction CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status' AFTER Status,
  ADD COLUMN AutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed' AFTER AutocountSyncAction,
  ADD COLUMN AutocountSyncMessage TEXT AFTER AutocountSyncStatus,
  ADD COLUMN SupplierCode VARCHAR(255) NULL AFTER Phone;

CREATE INDEX IX_supplier_AutocountSyncAction ON supplier (AutocountSyncAction);
CREATE INDEX IX_supplier_AutocountSyncStatus ON supplier (AutocountSyncStatus);
CREATE INDEX IX_supplier_SupplierCode ON supplier (SupplierCode);


ALTER TABLE booking
  ADD COLUMN CustomerAutocountSyncAction CHAR(1) NOT NULL DEFAULT 'C' COMMENT 'C: Create, U: Update, D: Delete, V: Void, S: Update_Status' AFTER Status,
  ADD COLUMN CustomerAutocountSyncStatus CHAR(1) NOT NULL DEFAULT 'P' COMMENT 'P: Pending, S: Synced, F: Failed' AFTER CustomerAutocountSyncAction,
  ADD COLUMN CustomerAutocountSyncMessage TEXT AFTER CustomerAutocountSyncStatus,
  ADD COLUMN CustomerCode VARCHAR(255) NULL AFTER Customer;

CREATE INDEX IX_booking_customer_AutocountSyncAction ON booking (CustomerAutocountSyncAction);
CREATE INDEX IX_booking_customer_AutocountSyncStatus ON booking (CustomerAutocountSyncStatus);
CREATE INDEX IX_booking_customer_CustomerCode ON booking (CustomerCode);
