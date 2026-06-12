<?php
/**
 * Run with: php tests/helpers/ResponseAvgCombinedDutyTest.php
 *
 * Locks the query-time duty-hour reducer that powers the TC "My Response Time"
 * card -- the COMBINED variant that merges the first-5 reply slots with the
 * most-recent-5 reply slots (Report_Model::Lead_Dashboard_Summary,
 * avg_combined_response_time_seconds).
 *
 * Input shape mirrors the per-slot SELECT: one array entry per
 * ghl_processed_leads row, carrying both the first-5 keys
 *   (ct1..ct5, at1..at5, s1..s5, aid1..aid5)
 * and the recent-5 keys
 *   (rct1..rct5, rat1..rat5, rs1..rs5, raid1..raid5).
 *
 * Rule:
 *   - walk first-5 slots, then recent-5 slots
 *   - skip a slot when its seconds is null/empty
 *   - DEDUP by agent_message_id: a message already counted from the first-5
 *     set is not counted again from the recent-5 set (short leads overlap)
 *   - duty-adjust each surviving gap, add to sum, increment count
 *   - avg = sum / count, or NULL when count == 0
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/duty_hours_helper.php';

function reduce_combined_duty_avg(array $slotRows) {
    $total = 0;
    $count = 0;
    foreach ($slotRows as $sr) {
        $seen = array();
        for ($i = 1; $i <= 5; $i++) {
            $secs = $sr['s' . $i];
            if ($secs === null || $secs === '') continue;
            $aid = isset($sr['aid' . $i]) ? $sr['aid' . $i] : null;
            if ($aid !== null && $aid !== '') {
                if (isset($seen[$aid])) continue;
                $seen[$aid] = true;
            }
            $dutySeconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i]);
            if ($dutySeconds === null) continue;
            $total += (int) $dutySeconds;
            $count++;
        }
        for ($i = 1; $i <= 5; $i++) {
            $secs = $sr['rs' . $i];
            if ($secs === null || $secs === '') continue;
            $aid = isset($sr['raid' . $i]) ? $sr['raid' . $i] : null;
            if ($aid !== null && $aid !== '') {
                if (isset($seen[$aid])) continue;
                $seen[$aid] = true;
            }
            $dutySeconds = calculate_duty_response_seconds($sr['rct' . $i], $sr['rat' . $i]);
            if ($dutySeconds === null) continue;
            $total += (int) $dutySeconds;
            $count++;
        }
    }
    return $count > 0 ? (int) round($total / $count) : null;
}

/**
 * Build a row from a flat list of responses. Each response is
 * array($agentId, $customerAt, $agentAt, $seconds). The first 5 fill the
 * first-5 slots; the last 5 (reversed, mirroring the cron's recent ordering)
 * fill the recent-5 slots.
 */
function combined_row(array $responses) {
    $r = array();
    for ($i = 1; $i <= 5; $i++) {
        $r['aid' . $i] = null; $r['ct' . $i] = null; $r['at' . $i] = null; $r['s' . $i] = null;
        $r['raid' . $i] = null; $r['rct' . $i] = null; $r['rat' . $i] = null; $r['rs' . $i] = null;
    }
    $first = array_slice($responses, 0, 5);
    foreach ($first as $idx => $resp) {
        $slot = $idx + 1;
        $r['aid' . $slot] = $resp[0];
        $r['ct' . $slot]  = $resp[1];
        $r['at' . $slot]  = $resp[2];
        $r['s' . $slot]   = $resp[3];
    }
    $recent = array_reverse(array_slice($responses, -5)); // most-recent first
    foreach ($recent as $idx => $resp) {
        $slot = $idx + 1;
        $r['raid' . $slot] = $resp[0];
        $r['rct' . $slot]  = $resp[1];
        $r['rat' . $slot]  = $resp[2];
        $r['rs' . $slot]   = $resp[3];
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
// On-duty gaps below resolve to their raw second count.

// Scenario 1: short lead (3 replies). first-5 and recent-5 hold the SAME three
// messages -> dedup must count each once. avg of 60/120/180 = 120, not double.
assert_eq('s1 short lead dedup', 120, reduce_combined_duty_avg(array(
    combined_row(array(
        array('m1', '2026-05-20 09:29:00', '2026-05-20 09:30:00',  60),
        array('m2', '2026-05-20 10:58:00', '2026-05-20 11:00:00', 120),
        array('m3', '2026-05-20 13:57:00', '2026-05-20 14:00:00', 180),
    )),
)));

// Scenario 2: 7-reply lead. first-5 = m1..m5, recent-5 = m3..m7. Union = m1..m7.
// All 60s on-duty -> avg 60 across 7 distinct messages.
assert_eq('s2 partial overlap union', 60, reduce_combined_duty_avg(array(
    combined_row(array(
        array('m1', '2026-05-20 09:29:00', '2026-05-20 09:30:00', 60),
        array('m2', '2026-05-20 09:39:00', '2026-05-20 09:40:00', 60),
        array('m3', '2026-05-20 09:49:00', '2026-05-20 09:50:00', 60),
        array('m4', '2026-05-20 09:59:00', '2026-05-20 10:00:00', 60),
        array('m5', '2026-05-20 10:09:00', '2026-05-20 10:10:00', 60),
        array('m6', '2026-05-20 10:19:00', '2026-05-20 10:20:00', 60),
        array('m7', '2026-05-20 10:29:00', '2026-05-20 10:30:00', 60),
    )),
)));

// Scenario 3: 12-reply lead. first-5 = m1..m5, recent-5 = m8..m12. No overlap ->
// 10 distinct slots counted. first-5 are 60s, recent-5 are 120s -> avg 90.
assert_eq('s3 no overlap both sets', 90, reduce_combined_duty_avg(array(
    combined_row(array(
        array('m1',  '2026-05-20 09:00:00', '2026-05-20 09:01:00',  60),
        array('m2',  '2026-05-20 09:02:00', '2026-05-20 09:03:00',  60),
        array('m3',  '2026-05-20 09:04:00', '2026-05-20 09:05:00',  60),
        array('m4',  '2026-05-20 09:06:00', '2026-05-20 09:07:00',  60),
        array('m5',  '2026-05-20 09:08:00', '2026-05-20 09:09:00',  60),
        array('m6',  '2026-05-20 09:10:00', '2026-05-20 09:11:00', 999),
        array('m7',  '2026-05-20 09:12:00', '2026-05-20 09:13:00', 999),
        array('m8',  '2026-05-20 09:14:00', '2026-05-20 09:16:00', 120),
        array('m9',  '2026-05-20 09:18:00', '2026-05-20 09:20:00', 120),
        array('m10', '2026-05-20 09:22:00', '2026-05-20 09:24:00', 120),
        array('m11', '2026-05-20 09:26:00', '2026-05-20 09:28:00', 120),
        array('m12', '2026-05-20 09:30:00', '2026-05-20 09:32:00', 120),
    )),
)));

// Scenario 4: recent-5 gap lands after-hours -> duty clip applies to the merged
// value too. m1 on-duty 60s; m2 spans 21:30->22:30 (30 min on-duty = 1800s).
// Single lead, 2 replies (so first/recent share both) -> avg (60+1800)/2 = 930.
assert_eq('s4 duty clip on merged', 930, reduce_combined_duty_avg(array(
    combined_row(array(
        array('m1', '2026-05-20 09:59:00', '2026-05-20 10:00:00',   60),
        array('m2', '2026-05-20 21:30:00', '2026-05-20 22:30:00', 3600),
    )),
)));

// Scenario 5: empty input -> NULL.
assert_eq('s5 empty input', null, reduce_combined_duty_avg(array()));

// Scenario 6: multi-lead. Lead A short (m1=60,m2=120 -> deduped). Lead B 6 replies
// (b1..b6, all 60). Counted: A {60,120}=2 slots, B {b1..b6}=6 slots.
// sum = 60+120 + 60*6 = 540 over 8 -> 68 (round).
assert_eq('s6 multi-lead', 68, reduce_combined_duty_avg(array(
    combined_row(array(
        array('a1', '2026-05-20 09:29:00', '2026-05-20 09:30:00',  60),
        array('a2', '2026-05-20 10:58:00', '2026-05-20 11:00:00', 120),
    )),
    combined_row(array(
        array('b1', '2026-05-20 09:00:00', '2026-05-20 09:01:00', 60),
        array('b2', '2026-05-20 09:02:00', '2026-05-20 09:03:00', 60),
        array('b3', '2026-05-20 09:04:00', '2026-05-20 09:05:00', 60),
        array('b4', '2026-05-20 09:06:00', '2026-05-20 09:07:00', 60),
        array('b5', '2026-05-20 09:08:00', '2026-05-20 09:09:00', 60),
        array('b6', '2026-05-20 09:10:00', '2026-05-20 09:11:00', 60),
    )),
)));

// Scenario 7: empty-string seconds ignored on both sets (defensive).
assert_eq('s7 empty-string secs ignored', 50, reduce_combined_duty_avg(array(
    combined_row(array(
        array('m1', '2026-05-20 09:29:10', '2026-05-20 09:30:00', 50),
        array('m2', '2026-05-20 10:59:00', '2026-05-20 11:00:00', ''), // ignored both sets
    )),
)));

echo "\nAll assertions passed.\n";
