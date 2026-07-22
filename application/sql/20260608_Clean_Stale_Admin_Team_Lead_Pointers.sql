-- Resets stale team-lead pointers in `admin` that the UI hides but the checklist
-- authorization used to honour, silently granting edit rights.
--   * admin.TeamLeadID   must point to an ACTIVE Level-25 sales lead, else NULL.
--   * admin.OpTeamLeadID  must point to an ACTIVE Level-45 OP TEAM LEAD, else NULL.
-- Idempotent: re-running is a no-op once all pointers are clean.

UPDATE admin a
LEFT JOIN admin tl ON a.TeamLeadID = tl.AdminID AND tl.Level = '25' AND tl.Status = 'Y'
SET a.TeamLeadID = NULL
WHERE a.TeamLeadID IS NOT NULL AND tl.AdminID IS NULL;

UPDATE admin a
LEFT JOIN admin op ON a.OpTeamLeadID = op.AdminID AND op.Level = '45' AND op.Status = 'Y'
SET a.OpTeamLeadID = NULL
WHERE a.OpTeamLeadID IS NOT NULL AND op.AdminID IS NULL;
