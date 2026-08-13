-- Manual Leads: drop the single "current status" field (ghl_contacts.lead_status).
-- A lead's status now lives ONLY in the dated lead_status_log; the latest active
-- entry is the lead's current status (see Guests_Model listing/filter/export).
--
-- Step 1 — backfill: preserve any existing current-status value by inserting it as
-- one dated log entry, but ONLY for manual leads that have NO log entries yet (this
-- mirrors the old fallback: the field was shown only when the log was empty, so we
-- never duplicate a status the lead already logged). Dated the lead's created date,
-- keyed by the SAME dedup_key the listing uses (COALESCE(dedup_key,'ghl:'||id)).
INSERT INTO lead_status_log (dedup_key, StatusDate, LeadStatus, Note, Status, CreatedBy, CreatedAt)
SELECT COALESCE(gc.dedup_key, CONCAT('ghl:', gc.id)),
       DATE(COALESCE(gc.date_added, gc.created_at)),
       gc.lead_status, NULL, 'Y', gc.created_by, NOW()
FROM ghl_contacts gc
WHERE gc.lead_source = 'manual'
  AND gc.lead_status IS NOT NULL
  AND TRIM(gc.lead_status) <> ''
  AND NOT EXISTS (
      SELECT 1 FROM lead_status_log lsl
      WHERE lsl.Status = 'Y'
        AND lsl.dedup_key = COALESCE(gc.dedup_key, CONCAT('ghl:', gc.id))
  );

-- Step 2 — drop the now-unused column.
ALTER TABLE `ghl_contacts` DROP COLUMN `lead_status`;
