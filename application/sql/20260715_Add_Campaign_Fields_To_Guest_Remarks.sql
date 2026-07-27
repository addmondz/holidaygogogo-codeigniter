-- Guest List remarks now log a Campaign Date + Destination + Follow Date
-- instead of a plain "date & time" stamp. Add the three columns, backfill the
-- Campaign Date from the old RemarkAt for any existing rows, then drop RemarkAt.
-- CreatedAt still records when the note was actually written (audit trail).

ALTER TABLE `guest_remarks`
  ADD COLUMN `CampaignDate`  DATE NULL AFTER `dedup_key`,
  ADD COLUMN `DestinationID` INT  NULL AFTER `CampaignDate`,
  ADD COLUMN `FollowDate`    DATE NULL AFTER `Remark`;

-- Existing notes: treat their old timestamp's date as the campaign date.
UPDATE `guest_remarks` SET `CampaignDate` = DATE(`RemarkAt`) WHERE `CampaignDate` IS NULL;

ALTER TABLE `guest_remarks` MODIFY `CampaignDate` DATE NOT NULL;
ALTER TABLE `guest_remarks` DROP COLUMN `RemarkAt`;

-- The Guest List can now be filtered by a Campaign / Follow date range, so
-- index both dates for the EXISTS look-up.
ALTER TABLE `guest_remarks`
  ADD INDEX `idx_campaign_date` (`CampaignDate`),
  ADD INDEX `idx_follow_date`   (`FollowDate`);
