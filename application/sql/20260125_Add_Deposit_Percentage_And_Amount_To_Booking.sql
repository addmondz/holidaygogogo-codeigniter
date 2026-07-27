-- Add DepositPercentage column to booking table
ALTER TABLE booking
  ADD COLUMN DepositPercentage INT NOT NULL DEFAULT 0
  COMMENT 'Deposit percentage (0-100)'
  AFTER NetTotal;