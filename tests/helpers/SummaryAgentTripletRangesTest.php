<?php
/**
 * Run with: php tests/helpers/SummaryAgentTripletRangesTest.php
 *
 * Locks summary_agent_triplet_ranges() — the resolver behind the sales-agent
 * summary cards' Today / Week / Month triplet columns. Given an anchor day it
 * returns the ranges those three columns are scoped to:
 *   - Today = the anchor day itself
 *   - Week  = Monday..Sunday of the anchor day's week
 *   - Month = 1st..last day of the anchor day's month
 *
 * The controller passes the day picker's selected day when one is active, so
 * the triplet re-scopes to that day; otherwise it passes today and the triplet
 * stays live.
 *
 * Rules:
 *   - Week is ISO Monday..Sunday and stays the same anywhere inside the week
 *     (mid-week or the trailing Sunday resolve to the same Monday).
 *   - month_end respects month length (Feb leap vs non-leap, 30 vs 31).
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

// ---- mid-week anchor (Mon 2026-06-15 is itself a Monday) -------------------
$r = summary_agent_triplet_ranges('2026-06-17'); // Wednesday
assert_eq('mid-week day',        '2026-06-17', $r['day']);
assert_eq('mid-week week_start', '2026-06-15', $r['week_start']); // Monday
assert_eq('mid-week week_end',   '2026-06-21', $r['week_end']);   // Sunday
assert_eq('mid-week month_start','2026-06-01', $r['month_start']);
assert_eq('mid-week month_end',  '2026-06-30', $r['month_end']);

// ---- Sunday anchor resolves to the SAME Mon..Sun week ----------------------
$r = summary_agent_triplet_ranges('2026-06-21'); // Sunday
assert_eq('sunday week_start',   '2026-06-15', $r['week_start']);
assert_eq('sunday week_end',     '2026-06-21', $r['week_end']);

// ---- Monday anchor is its own week start -----------------------------------
$r = summary_agent_triplet_ranges('2026-06-15'); // Monday
assert_eq('monday week_start',   '2026-06-15', $r['week_start']);
assert_eq('monday week_end',     '2026-06-21', $r['week_end']);

// ---- week straddles a month boundary (still whole Mon..Sun) ----------------
$r = summary_agent_triplet_ranges('2026-07-01'); // Wednesday; week starts in June
assert_eq('cross-month week_start', '2026-06-29', $r['week_start']);
assert_eq('cross-month week_end',   '2026-07-05', $r['week_end']);
assert_eq('cross-month month_start','2026-07-01', $r['month_start']);
assert_eq('cross-month month_end',  '2026-07-31', $r['month_end']);

// ---- month-length edge cases ----------------------------------------------
assert_eq('Feb leap 2024',     '2024-02-29', summary_agent_triplet_ranges('2024-02-10')['month_end']);
assert_eq('Feb non-leap 2026', '2026-02-28', summary_agent_triplet_ranges('2026-02-10')['month_end']);
assert_eq('Apr 30 days',       '2026-04-30', summary_agent_triplet_ranges('2026-04-10')['month_end']);
assert_eq('Dec 31 days',       '2026-12-31', summary_agent_triplet_ranges('2026-12-10')['month_end']);

echo "\nAll assertions passed.\n";
