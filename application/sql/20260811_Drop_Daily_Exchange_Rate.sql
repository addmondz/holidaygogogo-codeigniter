-- 2026-08-11  Drop the redundant daily_exchange_rate table.
-- The MYR->USD history is captured per-run in exchange_rate_run_log, and the
-- rates the app actually uses live in costing_exchange_rates. This standalone
-- store had no downstream consumer, so it's removed. IF EXISTS keeps it safe on
-- environments that never created it.
DROP TABLE IF EXISTS `daily_exchange_rate`;
