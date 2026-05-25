<?php
/**
 * Run with: php tests/helpers/SalesVsTargetPercentTest.php
 *
 * Locks the percentage-vs-target arithmetic used by the TC
 * "Total Sales (Month)" card and the derived "Week Pace" subtitle.
 *
 * Rules:
 *   - target_amount = 0 -> percent is null (rendered as "—"); never crash.
 *   - target_amount > 0 -> round((actual / target) * 100, 1).
 *   - actual > target  -> percent > 100 allowed (over-achievement).
 *   - Week pace expected revenue = target * (days_elapsed / days_in_month).
 *     Week pace percent = round((week_actual / expected) * 100, 1) when
 *     expected > 0; null otherwise.
 *   - days_elapsed clamps to >= 1 so day-1 of the month does not divide by 0.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function vs_target_percent($actual, $target)
{
    $target = (float)$target;
    if ($target <= 0) { return null; }
    return round(((float)$actual / $target) * 100, 1);
}

function week_pace_percent($week_actual, $monthly_target, $days_elapsed, $days_in_month)
{
    $monthly_target = (float)$monthly_target;
    $days_in_month  = max(1, (int)$days_in_month);
    $days_elapsed   = max(1, (int)$days_elapsed);
    if ($monthly_target <= 0) { return null; }
    $expected = $monthly_target * ($days_elapsed / $days_in_month);
    if ($expected <= 0) { return null; }
    return round(((float)$week_actual / $expected) * 100, 1);
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- vs-target percent ----------------------------------------------------
assert_eq('target=0 -> null',              null, vs_target_percent(5000, 0));
assert_eq('actual=0, target=10000 -> 0%',  0.0,  vs_target_percent(0, 10000));
assert_eq('halfway',                        50.0, vs_target_percent(5000, 10000));
assert_eq('exact',                          100.0, vs_target_percent(10000, 10000));
assert_eq('over-achievement',               125.0, vs_target_percent(12500, 10000));
assert_eq('rounds to 1 dp',                 33.3, vs_target_percent(1000, 3000));
assert_eq('negative target treated as 0',  null, vs_target_percent(1000, -500));

// ---- week pace percent ----------------------------------------------------
// 31-day month, 7 days elapsed, monthly target 10000
//   expected = 10000 * 7/31 = 2258.0645...
//   actual 2258 -> 100.0 (approximately)
$exp = 10000 * (7 / 31);
assert_eq('on-pace mid-week',  100.0, week_pace_percent(round($exp, 2), 10000, 7, 31));
assert_eq('half-pace',         50.0,  week_pace_percent(round($exp / 2, 2), 10000, 7, 31));
assert_eq('ahead of pace',     200.0, week_pace_percent(round($exp * 2, 2), 10000, 7, 31));
assert_eq('zero target -> null', null, week_pace_percent(1000, 0, 7, 31));
// day 1 must not divide by zero
$d1_exp = 10000 * (1 / 31);
assert_eq('day 1 of month',    100.0, week_pace_percent(round($d1_exp, 2), 10000, 1, 31));
// days_elapsed=0 should be clamped to 1
assert_eq('clamps day 0',      100.0, week_pace_percent(round($d1_exp, 2), 10000, 0, 31));

echo "\nAll assertions passed.\n";
