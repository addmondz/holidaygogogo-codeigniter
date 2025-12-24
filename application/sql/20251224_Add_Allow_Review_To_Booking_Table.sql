ALTER TABLE booking
  ADD COLUMN AllowReview BOOLEAN DEFAULT TRUE AFTER AutocountSyncMessage,
  ADD COLUMN CustomerReview TEXT NULL AFTER AllowReview,
  Add COLUMN CustomerReviewTimestamp DATETIME NULL AFTER CustomerReview;