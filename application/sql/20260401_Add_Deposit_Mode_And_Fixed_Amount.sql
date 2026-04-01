-- Add DepositMode and DepositFixedAmount columns to booking table
ALTER TABLE booking
  ADD COLUMN DepositMode VARCHAR(10) NOT NULL DEFAULT 'percentage'
  COMMENT 'Deposit mode: percentage or fixed'
  AFTER DepositPercentage,
  ADD COLUMN DepositFixedAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00
  COMMENT 'Fixed deposit amount'
  AFTER DepositMode;
