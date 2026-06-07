<?php
/**
 * Run with: php tests/helpers/SummaryResolveMonthTest.php
 *
 * Locks summary_resolve_month() — the resolver behind the Booking summary
 * cards' month filter. It turns a `?month=YYYY-MM` query value into the month
 * and year calendar ranges every "(Month)" / "(Year)" TC card is scoped to.
 *
 * Rules:
 *   - Valid YYYY-MM -> that month; ranges cover 1st..last of the month and
 *     Jan 1..Dec 31 of the year.
 *   - Missing / malformed / out-of-range -> fall back to $today's month
 *     (a bad query string must never break the dashboard).
 *   - month_end respects month length (Feb leap vs non-leap, 30 vs 31).
 *   - value is zero-padded YYYY-MM so it round-trips back into the picker.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/summary_period_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$today = '2026-06-15';

// ---- valid selection ------------------------------------------------------
$r = summary_resolve_month('2026-03', $today);
assert_eq('valid year',        2026,         $r['year']);
assert_eq('valid month',       3,            $r['month']);
assert_eq('month_start',       '2026-03-01', $r['month_start']);
assert_eq('month_end (Mar)',   '2026-03-31', $r['month_end']);
assert_eq('year_start',        '2026-01-01', $r['year_start']);
assert_eq('year_end',          '2026-12-31', $r['year_end']);
assert_eq('value padded',      '2026-03',    $r['value']);
assert_eq('label',             'March 2026', $r['label']);

// ---- month-length edge cases ----------------------------------------------
assert_eq('Feb leap 2024',     '2024-02-29', summary_resolve_month('2024-02', $today)['month_end']);
assert_eq('Feb non-leap 2026', '2026-02-28', summary_resolve_month('2026-02', $today)['month_end']);
assert_eq('Apr 30 days',       '2026-04-30', summary_resolve_month('2026-04', $today)['month_end']);

// ---- a different year ------------------------------------------------------
$r = summary_resolve_month('2025-12', $today);
assert_eq('cross-year year',   2025,         $r['year']);
assert_eq('cross-year y_start','2025-01-01', $r['year_start']);
assert_eq('cross-year y_end',  '2025-12-31', $r['year_end']);

// ---- fallbacks (must default to $today's month) ---------------------------
assert_eq('null -> today month',    '2026-06', summary_resolve_month(null, $today)['value']);
assert_eq('empty -> today month',   '2026-06', summary_resolve_month('', $today)['value']);
assert_eq('garbage -> today month', '2026-06', summary_resolve_month('not-a-month', $today)['value']);
assert_eq('month 13 -> today',      '2026-06', summary_resolve_month('2026-13', $today)['value']);
assert_eq('month 00 -> today',      '2026-06', summary_resolve_month('2026-00', $today)['value']);
assert_eq('year 1999 -> today',     '2026-06', summary_resolve_month('1999-05', $today)['value']);
assert_eq('unpadded -> today',      '2026-06', summary_resolve_month('2026-6', $today)['value']);

echo "\nAll assertions passed.\n";
