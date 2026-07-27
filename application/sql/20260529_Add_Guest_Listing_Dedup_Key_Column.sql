-- Add a precomputed, indexed dedup_key column to guest_list and ghl_contacts.
--
-- The column mirrors Guests_Model::Dedup_Key_Expr() except for the per-row
-- auto-increment fallback: MySQL forbids generated columns from referencing
-- AUTO_INCREMENT columns, so when both Mobile/phone and Email are empty the
-- column is left NULL, and the model wraps it with COALESCE(..., CONCAT('row:'/'ghl:', id))
-- at query time so distinct nameless-but-contactless rows still get unique keys.
--
-- Why: the inline REGEXP_REPLACE + LOWER + COLLATE in the listing's window
-- function ran per row during PARTITION BY sort. On prod with concurrent load
-- this caused multi-minute hangs (134s and 842s observed on SHOW PROCESSLIST).
-- Precomputing the key at insert/update and indexing it turns the sort into a
-- sequential index read.
--
-- Collation note: guest_list source columns are utf8mb4_general_ci, ghl_contacts
-- are utf8mb4_unicode_ci. Both dedup_key columns are declared utf8mb4_unicode_ci
-- so the cross-table anti-join doesn't need per-row collation coercion.

-- 1. guest_list
ALTER TABLE guest_list
  ADD COLUMN dedup_key VARCHAR(190)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    GENERATED ALWAYS AS (
      COALESCE(
        NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(Mobile, ''), '[^0-9]', ''), 9), ''),
        LOWER(NULLIF(Email, ''))
      )
    ) STORED,
  ADD INDEX idx_guest_list_dedup_key (dedup_key);

-- 2. ghl_contacts
ALTER TABLE ghl_contacts
  ADD COLUMN dedup_key VARCHAR(190)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    GENERATED ALWAYS AS (
      COALESCE(
        NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(phone, ''), '[^0-9]', ''), 9), ''),
        LOWER(NULLIF(email, ''))
      )
    ) STORED,
  ADD INDEX idx_ghl_contacts_dedup_key (dedup_key);
