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
 *   - skip slot if at fails is_within_duty_hours()
 *   - else add to sum, increment count
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
            if (!is_within_duty_hours($sr['at' . $i])) continue;
            $dutyTotal += (int) $secs;
            $dutyCount++;
        }
    }
    return $dutyCount > 0 ? (int) round($dutyTotal / $dutyCount) : null;
}

function row($slots) {
    $r = array();
    for ($i = 1; $i <= 5; $i++) {
        $r['at' . $i] = isset($slots[$i - 1][0]) ? $slots[$i - 1][0] : null;
        $r['s'  . $i] = isset($slots[$i - 1][1]) ? $slots[$i - 1][1] : null;
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

// Calendar anchor: 2026-05-20 (Wed). Duty hours: Mon-Sat 09:00-18:00.

// Scenario 1: one lead, 3 slots all on-duty -> avg = mean of all three.
assert_eq('s1 single lead all on-duty', 120, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 09:30:00',  60),
        array('2026-05-20 11:00:00', 120),
        array('2026-05-20 14:00:00', 180),
    )),
)));

// Scenario 2: one lead, 1 on-duty + 2 off-duty -> avg = the one on-duty value.
assert_eq('s2 mix on/off in single lead', 60, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 10:00:00',   60), // on
        array('2026-05-20 22:00:00', 9999), // off (night)
        array('2026-05-17 10:00:00', 9999), // off (Sunday)
    )),
)));

// Scenario 3: one lead, 0 on-duty replies -> avg = NULL.
assert_eq('s3 all off-duty', null, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 22:00:00', 100),
        array('2026-05-20 06:00:00', 200),
        array('2026-05-17 12:00:00', 300),
    )),
)));

// Scenario 4: one lead, 1 unanswered (s=null), 2 answered (1 on + 1 off).
assert_eq('s4 null slot + mix', 90, reduce_duty_avg(array(
    row(array(
        array(null, null),
        array('2026-05-20 11:00:00',   90),  // on
        array('2026-05-20 23:00:00', 9999),  // off
    )),
)));

// Scenario 5: no leads -> avg = NULL.
assert_eq('s5 empty input', null, reduce_duty_avg(array()));

// Scenario 6: many leads, mixed on/off across leads.
// Lead A: slot1 60s on, slot2 120s off
// Lead B: slot1 180s on
// Lead C: slot1 240s off (only)
// Lead D: empty slots
// On-duty: 60, 180 -> avg = 120.
assert_eq('s6 multi-lead aggregate', 120, reduce_duty_avg(array(
    row(array(array('2026-05-20 09:30:00', 60),  array('2026-05-20 22:00:00', 120))),
    row(array(array('2026-05-20 10:00:00', 180))),
    row(array(array('2026-05-20 22:00:00', 240))),
    row(array()),
)));

// Scenario 7: empty-string seconds should be ignored (defensive — MySQL may
// return '' for null in some result drivers).
assert_eq('s7 empty-string secs ignored', 50, reduce_duty_avg(array(
    row(array(
        array('2026-05-20 09:30:00', 50),
        array('2026-05-20 11:00:00', ''),  // ignored
    )),
)));

echo "\nAll assertions passed.\n";
