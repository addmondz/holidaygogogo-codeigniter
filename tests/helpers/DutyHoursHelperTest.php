<?php
/**
 * Run with: php tests/helpers/DutyHoursHelperTest.php
 *
 * Unit test of is_within_duty_hours().
 *
 * Calendar anchors used:
 *   2026-05-20 (Wed)
 *   2026-05-17 (Sun)
 *   2026-05-23 (Sat)
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/duty_hours_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('Wed 09:00 (start, inclusive)',  true,  is_within_duty_hours('2026-05-20 09:00:00'));
assert_eq('Wed 17:59 (within)',            true,  is_within_duty_hours('2026-05-20 17:59:59'));
assert_eq('Wed 18:00 (end, exclusive)',    false, is_within_duty_hours('2026-05-20 18:00:00'));
assert_eq('Wed 08:59 (before start)',      false, is_within_duty_hours('2026-05-20 08:59:00'));
assert_eq('Sun 10:00 (off day)',           false, is_within_duty_hours('2026-05-17 10:00:00'));
assert_eq('Sat 10:00 (Sat included)',      true,  is_within_duty_hours('2026-05-23 10:00:00'));
assert_eq('Sat 23:00 (Sat, off hours)',    false, is_within_duty_hours('2026-05-23 23:00:00'));
assert_eq('empty string',                  false, is_within_duty_hours(''));
assert_eq('garbage string',                false, is_within_duty_hours('not-a-date'));
assert_eq('null',                          false, is_within_duty_hours(null));

echo "\nAll assertions passed.\n";
