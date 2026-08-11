-- 2026-08-11  Audit trail for the exchange-rate cron (Cron::fetchExchangeRates).
-- One row per run so you can answer "did it run last night, and what happened?":
-- when it started/finished, whether it succeeded, the headline MYR->USD rate,
-- how many /Costing/Currency rates it updated vs skipped, and any error.
-- Mirrors the ghl_sync_run_log pattern.
CREATE TABLE IF NOT EXISTS `exchange_rate_run_log` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`             VARCHAR(120) NOT NULL,
  `source`             VARCHAR(20)  NULL DEFAULT NULL,   -- cli | web
  `status`             ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
  `base_code`          VARCHAR(3)   NULL DEFAULT NULL,
  `quote_code`         VARCHAR(3)   NULL DEFAULT NULL,
  `rate`               DECIMAL(18,8) NULL DEFAULT NULL,  -- headline MYR->USD
  `rate_date`          DATE NULL DEFAULT NULL,
  `currencies_updated` INT NOT NULL DEFAULT 0,
  `currencies_skipped` INT NOT NULL DEFAULT 0,
  `updated_codes`      TEXT NULL DEFAULT NULL,           -- csv, e.g. USD,SGD,THB
  `skipped_codes`      TEXT NULL DEFAULT NULL,           -- csv
  `error`              TEXT NULL DEFAULT NULL,
  `started_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at`        DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_exchange_rate_run_id` (`run_id`),
  KEY `idx_exchange_rate_run_status_started` (`status`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
