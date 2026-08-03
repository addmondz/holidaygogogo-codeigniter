-- Dated Lead Status log for Manual Leads: multiple (date + status + note) entries
-- per lead, a history alongside the single "current status" (ghl_contacts.lead_status).
-- Mirrors guest_remarks: keyed by dedup_key, soft-deleted via Status, author in
-- CreatedBy, author-only delete. LeadStatus stores the preset NAME (from the
-- lead_status settings table), same as the current-status field.
CREATE TABLE IF NOT EXISTS `lead_status_log` (
  `LogID`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dedup_key`  VARCHAR(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `StatusDate` DATE NOT NULL,
  `LeadStatus` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Note`       TEXT COLLATE utf8mb4_unicode_ci NULL,
  `Status`     CHAR(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',  -- 'Y' active, 'N' soft-deleted
  `CreatedBy`  INT NULL DEFAULT NULL,                                    -- author = admin.AdminID
  `CreatedAt`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `idx_lsl_dedup_status` (`dedup_key`, `Status`),
  KEY `idx_lsl_status_date` (`StatusDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
