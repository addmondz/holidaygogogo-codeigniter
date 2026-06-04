<?php
/**
 * Run with: php tests/helpers/ResponseAvgDutyFilterTest.php
 *
 * Locks the query-time duty-hour reducer in
 * Report_Model::Lead_Dashboard_Summary().
 *
 * Input shape mirrors the per-slot SELECT (one array entry per ghl_processed_leads
 * row, with at1..at5 + s1..s5 keys). Reducer walks every slot across every row.
 *
 * Rule:
 *   - skip slot if s is null/empty
 *   - calculate seconds between customer and agent timestamps inside duty hours
 *   - add calculated duty seconds to sum, increment count
 *   - avg = sum / count, or NULL when count == 0
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/duty_hours_helper.php';

function reduce_duty_avg(array $slotRows) {
    $dutyTotal = 0;
    $dutyCount = 0;
    foreach ($slotRows as $sr) {
        for ($i = 1; $i <= 5; $i++) {
            $secs = $sr['s' . $i];
            if ($secs === null || $secs === '') continue;
            $dutySeconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i]);
            if ($dutySeconds === null) continue;
            $dutyTotal += (int) $dutySeconds;
            $dutyCount++;
        }
    }
    return $dutyCount > 0 ? (int) round($dutyTotal / $dutyCount) : null;
}

function row($slots) {
    $r = array();
    for ($i = 1; $i <= 5; $i++) {
        $r['ct' . $i] = isset($slots[$i - 1][0]) ? $slots[$i - 1][0] : null;
        $r['at' . $i] = isset($slots[$i - 1][1]) ? $slots[$i - 1][1] : null;
        $r['s'  . $i] = isset($slots[$i - 1][2]) ? $slots[$i - 1][2] : null;
    }
    return $r;
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Calendar anchor: 2026-05-20 (Wed). Duty hours: Mon-Sat 08:00-22:00.

// Scenario 1: one lead, 3 slots all on-duty -> avg = mean of all three.
assert_eq('s1 single lead all on-duty', 120, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 09:29:00', '2026-05-20 09:30:00',  60),
        array('2026-05-20 10:58:00', '2026-05-20 11:00:00', 120),
        array('2026-05-20 13:57:00', '2026-05-20 14:00:00', 180),
    )),
)));

// Scenario 2: one lead, in-window + clipped after-hours + off-day.
assert_eq('s2 mix clipped/off in single lead', 620, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 09:59:00', '2026-05-20 10:00:00',   60),
        array('2026-05-20 21:30:00', '2026-05-20 22:30:00', 3600),
        array('2026-05-17 09:00:00', '2026-05-17 10:00:00', 3600),
    )),
)));

// Scenario 3: one lead, 0 working seconds -> avg = 0.
assert_eq('s3 all off-duty', 0, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 22:00:00', '2026-05-20 22:30:00', 1800),
        array('2026-05-20 06:00:00', '2026-05-20 07:00:00', 3600),
        array('2026-05-17 10:00:00', '2026-05-17 12:00:00', 7200),
    )),
)));

// Scenario 4: one lead, 1 unanswered (s=null), 2 answered (1 on + 1 off).
assert_eq('s4 null slot + mix', 45, reduce_duty_avg(array(
    row(array(
        array(null, null, null),
        array('2026-05-20 10:58:30', '2026-05-20 11:00:00', 90),
        array('2026-05-20 23:00:00', '2026-05-20 23:30:00', 1800),
    )),
)));

// Scenario 5: no leads -> avg = NULL.
assert_eq('s5 empty input', null, reduce_duty_avg(array()));

// Scenario 6: many leads, mixed on/off across leads.
// Lead A: slot1 60s, slot2 0s off
// Lead B: slot1 180s
// Lead C: slot1 0s off (only)
// Lead D: empty slots
// Duty seconds: 60, 0, 180, 0 -> avg = 60.
assert_eq('s6 multi-lead aggregate', 60, reduce_duty_avg(array(
    row(array(array('2026-05-20 09:29:00', '2026-05-20 09:30:00', 60),  array('2026-05-20 22:00:00', '2026-05-20 22:02:00', 120))),
    row(array(array('2026-05-20 09:57:00', '2026-05-20 10:00:00', 180))),
    row(array(array('2026-05-20 22:00:00', '2026-05-20 22:04:00', 240))),
    row(array()),
)));

// Scenario 7: empty-string seconds should be ignored (defensive — MySQL may
// return '' for null in some result drivers).
assert_eq('s7 empty-string secs ignored', 50, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 09:29:10', '2026-05-20 09:30:00', 50),
        array('2026-05-20 10:59:00', '2026-05-20 11:00:00', ''),  // ignored
    )),
)));

echo "\nAll assertions passed.\n";
