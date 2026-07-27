-- =============================================
-- GUEST_LIST TABLE - ADD LASTNAME COLUMN
-- =============================================

-- Add LastName column
ALTER TABLE guest_list
  ADD COLUMN IF NOT EXISTS LastName VARCHAR(255) NULL
  COMMENT 'Guest last name'
  AFTER Name;

CREATE INDEX IF NOT EXISTS IX_guest_list_LastName
  ON guest_list (LastName);
