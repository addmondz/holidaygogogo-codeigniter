-- =============================================
-- GUEST_LIST TABLE - ADD PASSPORT AND DIETARY FIELDS
-- =============================================

-- Add PassportIssueDate column
ALTER TABLE guest_list
  ADD COLUMN PassportIssueDate DATE NULL
  COMMENT 'Passport issue date'
  AFTER PassportNumber;

-- Add PassportExpiryDate column
ALTER TABLE guest_list
  ADD COLUMN PassportExpiryDate DATE NULL
  COMMENT 'Passport expiry date'
  AFTER PassportIssueDate;

-- Add PassportCopy column (for file path/name)
ALTER TABLE guest_list
  ADD COLUMN PassportCopy VARCHAR(255) NULL
  COMMENT 'Passport copy file path/name'
  AFTER PassportExpiryDate;

-- Add DietaryRequirement column
ALTER TABLE guest_list
  ADD COLUMN DietaryRequirement TEXT NULL
  COMMENT 'Dietary requirements and restrictions'
  AFTER PassportCopy;

-- Create indexes for better performance
CREATE INDEX IX_guest_list_PassportIssueDate ON guest_list (PassportIssueDate);
CREATE INDEX IX_guest_list_PassportExpiryDate ON guest_list (PassportExpiryDate);
