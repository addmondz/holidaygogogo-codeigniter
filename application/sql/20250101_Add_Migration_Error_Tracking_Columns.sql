-- Migration: Add duplicate field error tracking columns to migrations table

ALTER TABLE `migrations`
  ADD COLUMN `has_duplicate_field_error` TINYINT(1) NOT NULL DEFAULT 0 AFTER `migration`,
  ADD COLUMN `error_message` TEXT NULL AFTER `has_duplicate_field_error`;
