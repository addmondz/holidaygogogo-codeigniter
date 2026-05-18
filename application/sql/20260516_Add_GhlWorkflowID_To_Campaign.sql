-- Per-campaign GHL workflow target. The sync button enrols each guest into
-- this workflow via POST /contacts/{contactId}/workflow/{workflowId}, which
-- triggers the workflow's WhatsApp send action directly (instead of relying
-- on a tag-trigger that wasn't firing reliably for API-applied tags).

ALTER TABLE `campaign`
  ADD COLUMN `GhlWorkflowID` VARCHAR(64) NULL DEFAULT NULL AFTER `Description`;
