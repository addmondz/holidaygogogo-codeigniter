-- Denormalise each customer's "self guest" attributes onto the customer row so
-- the Customer List no longer joins / EXISTS-scans the multi-million-row
-- guest_list table on every page load (that was timing out, especially with the
-- Date-of-Birth filter, because guest_list.DateOfBirth had no usable index and
-- the guest-tier filters ran a correlated EXISTS per customer).
--
-- The "self guest record" for a customer = the active guest_list row whose
-- dedup_key (last 9 digits of the guest's Mobile, a STORED generated column)
-- equals the last 9 digits of the customer's phone_number, lowest GuestListID.
-- These four columns are kept in sync in real time by
-- Customer_Model::Refresh_Snapshot_* (called from the guest_list + customer
-- write paths), and can be fully rebuilt any time by the backfill below or the
-- `customer_snapshot_resync` CLI command.
--
-- Column types mirror guest_list exactly (Gender enum, DateOfBirth date,
-- Nationality int = CountryCodeID, Type/GuestType varchar) so values copy across
-- without coercion.

-- 1. Add the four snapshot columns. INSTANT = metadata-only, no table rebuild.
--    No indexes: the customer table is small (~10k rows), so the Customer List's
--    guest-tier filters read these columns fast without them.
ALTER TABLE customer
  ADD COLUMN Gender      ENUM('F','M') NULL,
  ADD COLUMN DateOfBirth DATE          NULL,
  ADD COLUMN Nationality INT           NULL,
  ADD COLUMN GuestType   VARCHAR(95)   NULL,
  ALGORITHM=INSTANT;

-- 2. One-time backfill from guest_list. The derived table picks each dedup_key's
--    self record (lowest GuestListID among active rows) once, then copies it onto
--    every customer whose phone key matches. Customers with no matching active
--    guest row are left NULL (LEFT JOIN). Run off-peak on very large tables.
UPDATE customer c
LEFT JOIN (
  SELECT dedup_key, Gender, DateOfBirth, Nationality, Type FROM (
    SELECT gl.dedup_key, gl.Gender, gl.DateOfBirth, gl.Nationality, gl.Type,
      ROW_NUMBER() OVER (PARTITION BY gl.dedup_key ORDER BY gl.GuestListID ASC) AS rn
    FROM guest_list gl
    WHERE gl.Status = 'Y' AND gl.dedup_key IS NOT NULL
  ) z WHERE z.rn = 1
) g ON g.dedup_key = NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '')
SET c.Gender      = g.Gender,
    c.DateOfBirth = g.DateOfBirth,
    c.Nationality = g.Nationality,
    c.GuestType   = g.Type
WHERE NULLIF(RIGHT(REGEXP_REPLACE(IFNULL(c.phone_number, ''), '[^0-9]', ''), 9), '') IS NOT NULL;
