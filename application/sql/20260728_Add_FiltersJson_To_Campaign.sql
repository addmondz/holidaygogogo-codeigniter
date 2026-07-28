-- Snapshot the guest-picker filters that were applied when the campaign was
-- built. Stored as a JSON blob so the edit screen can re-populate the same
-- filter inputs (audience the roster was drawn from) without re-deriving them.

ALTER TABLE `campaign`
  ADD COLUMN `FiltersJson` TEXT NULL DEFAULT NULL AFTER `GhlWorkflowID`;
