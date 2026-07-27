-- Extend campaign_ghl_sync_log for the new workflow_enrolled action +
-- per-run enrolment counter. The historic 'tag_applied' value is retained
-- so prior log rows still parse, even though new runs no longer emit it.

ALTER TABLE `campaign_ghl_sync_log`
  MODIFY `Action` ENUM(
    'matched','created','tag_applied','workflow_enrolled',
    'skipped','failed','run_summary'
  ) NOT NULL;

ALTER TABLE `campaign_ghl_sync_log`
  ADD COLUMN `EnrolledCount` INT(11) NOT NULL DEFAULT 0 AFTER `TaggedCount`;
