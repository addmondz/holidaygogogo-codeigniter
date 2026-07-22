<?php
/**
 * Run with: php tests/helpers/SummaryPriorYearWindowTest.php
 *
 * Locks summary_prior_year_window() — the "same period last year" comparison
 * window behind the Booking sales cards (Month/Year sales vs same period last
 * year).
 *
 * Rules:
 *   - The current window is clamped to today (min(end, today)) so a partway-
 *     through month/year compares against the SAME number of days last year,
 *     not the full one.
 *   - The last-year window keeps the month/day and subtracts 1 from the year.
 *   - Feb 29 (absent the prior year) clamps to Feb 28.
 *   - A fully-past period compares against the full same period last year.
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

$today = '2026-07-03';

// ---- current month (July), partway through -> comparable to-date ----------
$w = summary_prior_year_window('2026-07-01', '2026-07-31', $today);
assert_eq('month start last year', '2025-07-01', $w['start']);
assert_eq('month end clamped to today last year', '2025-07-03', $w['end']);

// ---- current year (2026), partway through ---------------------------------
$w = summary_prior_year_window('2026-01-01', '2026-12-31', $today);
assert_eq('year start last year', '2025-01-01', $w['start']);
assert_eq('year end clamped to today last year', '2025-07-03', $w['end']);

// ---- a fully-past month (May) -> full same month last year ----------------
$w = summary_prior_year_window('2026-05-01', '2026-05-31', $today);
assert_eq('past month start last year', '2025-05-01', $w['start']);
assert_eq('past month end full last year', '2025-05-31', $w['end']);

// ---- a single picked day --------------------------------------------------
$w = summary_prior_year_window('2026-06-15', '2026-06-15', $today);
assert_eq('single day start last year', '2025-06-15', $w['start']);
assert_eq('single day end last year',   '2025-06-15', $w['end']);

// ---- Feb 29 clamps to Feb 28 the prior (non-leap) year --------------------
// Today inside a leap year; the current YTD reaches Feb 29 -> last year Feb 28.
$w = summary_prior_year_window('2024-01-01', '2024-12-31', '2024-02-29');
assert_eq('leap year start', '2023-01-01', $w['start']);
assert_eq('Feb 29 clamps to Feb 28', '2023-02-28', $w['end']);

// ---- shift helper directly ------------------------------------------------
assert_eq('shift normal',  '2025-03-10', summary_shift_back_one_year('2026-03-10'));
assert_eq('shift Feb 29',  '2023-02-28', summary_shift_back_one_year('2024-02-29'));

echo "ALL PASS\n";
