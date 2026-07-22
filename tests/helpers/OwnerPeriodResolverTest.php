<?php
/**
 * Run with: php tests/helpers/OwnerPeriodResolverTest.php
 *
 * Locks summary_resolve_owner_period() — the resolver behind the OWNER
 * dashboard's global Day / Week / Month / Year toggle. It turns the
 * `?owner_period=day|week|month|year` query value into the calendar range the
 * whole owner per-agent matrix is scoped to.
 *
 * Rules:
 *   - day   -> start == end == today.
 *   - week  -> Monday..Sunday of $today's ISO week.
 *   - month -> 1st..last of $today's month (respects month length).
 *   - year  -> Jan 1..Dec 31 of $today's year.
 *   - missing / malformed / unknown -> default to MONTH (a bad query string
 *     must never break the dashboard).
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

// $today is a Monday-anchored mid-week reference (2026-06-15 is a Monday).
$today = '2026-06-15';

// ---- day ------------------------------------------------------------------
$r = summary_resolve_owner_period('day', $today);
assert_eq('day period',     'day',        $r['period']);
assert_eq('day start',      '2026-06-15', $r['start_date']);
assert_eq('day end',        '2026-06-15', $r['end_date']);

// ---- week (Mon..Sun) ------------------------------------------------------
$r = summary_resolve_owner_period('week', $today);
assert_eq('week period',    'week',       $r['period']);
assert_eq('week start Mon', '2026-06-15', $r['start_date']);
assert_eq('week end Sun',   '2026-06-21', $r['end_date']);

// Week resolves the SAME Mon..Sun when the reference is a Sunday in that week.
$r = summary_resolve_owner_period('week', '2026-06-21');
assert_eq('week from Sunday start', '2026-06-15', $r['start_date']);
assert_eq('week from Sunday end',   '2026-06-21', $r['end_date']);

// ---- month (respects length) ----------------------------------------------
$r = summary_resolve_owner_period('month', $today);
assert_eq('month period',   'month',      $r['period']);
assert_eq('month start',    '2026-06-01', $r['start_date']);
assert_eq('month end Jun',  '2026-06-30', $r['end_date']);

// 28-day February and a 31-day month seeded from a 31-day reference (overflow guard).
assert_eq('Feb non-leap end', '2026-02-28', summary_resolve_owner_period('month', '2026-02-10')['end_date']);
assert_eq('Feb leap end',     '2024-02-29', summary_resolve_owner_period('month', '2024-02-10')['end_date']);
assert_eq('Jan31 from 31st',  '2026-01-31', summary_resolve_owner_period('month', '2026-01-31')['end_date']);
assert_eq('Jan31 start',      '2026-01-01', summary_resolve_owner_period('month', '2026-01-31')['start_date']);

// ---- year -----------------------------------------------------------------
$r = summary_resolve_owner_period('year', $today);
assert_eq('year period',    'year',       $r['period']);
assert_eq('year start',     '2026-01-01', $r['start_date']);
assert_eq('year end',       '2026-12-31', $r['end_date']);

// ---- fallbacks (default to MONTH) -----------------------------------------
assert_eq('null -> month',    'month', summary_resolve_owner_period(null, $today)['period']);
assert_eq('empty -> month',   'month', summary_resolve_owner_period('', $today)['period']);
assert_eq('garbage -> month', 'month', summary_resolve_owner_period('quarter', $today)['period']);
assert_eq('case-insensitive', 'week',  summary_resolve_owner_period('WEEK', $today)['period']);
assert_eq('trims whitespace', 'day',   summary_resolve_owner_period('  day ', $today)['period']);
// Fallback resolves to $today's month range, not just the label.
assert_eq('null range start', '2026-06-01', summary_resolve_owner_period(null, $today)['start_date']);
assert_eq('null range end',   '2026-06-30', summary_resolve_owner_period(null, $today)['end_date']);

// ---- yesterday (single day, base = day) -----------------------------------
$r = summary_resolve_owner_period('yesterday', $today);
assert_eq('yesterday period', 'yesterday',  $r['period']);
assert_eq('yesterday base',   'day',        $r['base']);
assert_eq('yesterday start',  '2026-06-14', $r['start_date']);
assert_eq('yesterday end',    '2026-06-14', $r['end_date']);
assert_eq('yesterday label',  'Yesterday',  $r['label']);

// ---- lastyear (this year's to-date span, one year back; base = year) -------
$r = summary_resolve_owner_period('lastyear', $today);
assert_eq('lastyear period', 'lastyear',   $r['period']);
assert_eq('lastyear base',   'year',       $r['base']);
assert_eq('lastyear start',  '2025-01-01', $r['start_date']);
assert_eq('lastyear end',    '2025-06-15', $r['end_date']);

// base defaults to the period for the existing toggles.
assert_eq('day base',   'day',   summary_resolve_owner_period('day',   $today)['base']);
assert_eq('week base',  'week',  summary_resolve_owner_period('week',  $today)['base']);
assert_eq('month base', 'month', summary_resolve_owner_period('month', $today)['base']);
assert_eq('year base',  'year',  summary_resolve_owner_period('year',  $today)['base']);

// ---- labels ---------------------------------------------------------------
assert_eq('day label',   'Today',     summary_resolve_owner_period('day',   $today)['label']);
assert_eq('week label',  'This Week', summary_resolve_owner_period('week',  $today)['label']);
assert_eq('month label', 'June 2026', summary_resolve_owner_period('month', $today)['label']);
assert_eq('year label',  '2026',      summary_resolve_owner_period('year',  $today)['label']);

echo "\nAll assertions passed.\n";
