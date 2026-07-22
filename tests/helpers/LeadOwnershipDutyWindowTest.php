<?php
/**
 * Run with: php tests/helpers/LeadOwnershipDutyWindowTest.php
 *
 * Locks the Lead Ownership "Average Response" card + column behaviour:
 * the avg-response figures are recomputed at query time from the first-5 and
 * last-5 reply slots, restricted to a DEDICATED duty window of 7AM-10PM,
 * everyday (kept independent of the global constants so it can be tuned
 * on its own).
 *
 * Two things are locked here:
 *   1. calculate_duty_response_seconds() honours an optional $opts override
 *      (days / start_hour / end_hour) while keeping the global default intact.
 *   2. The ownership reducer produces first / recent / combined averages, where
 *      - first   = the first-5 reply slots
 *      - recent  = the most-recent-5 reply slots (counted on their own)
 *      - combined = first-5 merged with recent-5, deduped by agent_message_id
 *      every surviving gap duty-adjusted through the 7AM-10PM everyday window.
 *
 * Calendar anchors:
 *   2026-05-20 (Wed), 2026-05-23 (Sat), 2026-05-24 (Sun)
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/duty_hours_helper.php';

// The Lead Ownership window: everyday, 07:00 inclusive -> 22:00 exclusive.
$OWNERSHIP_WINDOW = array('days' => array(1, 2, 3, 4, 5, 6, 7), 'start_hour' => 7, 'end_hour' => 22);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

/**
 * Mirror of Report_Model::calculate_ownership_duty_response_averages() for a
 * single bucket: returns array('first' => ..., 'recent' => ..., 'combined' => ...).
 */
function reduce_ownership_duty_avgs(array $slotRows, array $window) {
    $firstTotal = 0; $firstCount = 0;
    $recentTotal = 0; $recentCount = 0;
    $combinedTotal = 0; $combinedCount = 0;

    foreach ($slotRows as $sr) {
        $seen = array();

        for ($i = 1; $i <= 5; $i++) {
            $secs = isset($sr['s' . $i]) ? $sr['s' . $i] : null;
            if ($secs === null || $secs === '') continue;
            $seconds = calculate_duty_response_seconds($sr['ct' . $i], $sr['at' . $i], $window);
            if ($seconds === null) continue;
            $aid = isset($sr['aid' . $i]) ? $sr['aid' . $i] : null;
            if ($aid !== null && $aid !== '') $seen[$aid] = true;
            $firstTotal += (int) $seconds; $firstCount++;
            $combinedTotal += (int) $seconds; $combinedCount++;
        }

        for ($i = 1; $i <= 5; $i++) {
            $secs = isset($sr['rs' . $i]) ? $sr['rs' . $i] : null;
            if ($secs === null || $secs === '') continue;
            $seconds = calculate_duty_response_seconds($sr['rct' . $i], $sr['rat' . $i], $window);
            if ($seconds === null) continue;
            // Last-5 average counts every recent slot regardless of overlap.
            $recentTotal += (int) $seconds; $recentCount++;
            // Combined dedupes against the first-5 set.
            $aid = isset($sr['raid' . $i]) ? $sr['raid' . $i] : null;
            if ($aid !== null && $aid !== '') {
                if (isset($seen[$aid])) continue;
                $seen[$aid] = true;
            }
            $combinedTotal += (int) $seconds; $combinedCount++;
        }
    }

    return array(
        'first' => $firstCount > 0 ? (int) round($firstTotal / $firstCount) : null,
        'recent' => $recentCount > 0 ? (int) round($recentTotal / $recentCount) : null,
        'combined' => $combinedCount > 0 ? (int) round($combinedTotal / $combinedCount) : null,
    );
}

function ownership_row(array $responses) {
    $r = array();
    for ($i = 1; $i <= 5; $i++) {
        $r['aid' . $i] = null; $r['ct' . $i] = null; $r['at' . $i] = null; $r['s' . $i] = null;
        $r['raid' . $i] = null; $r['rct' . $i] = null; $r['rat' . $i] = null; $r['rs' . $i] = null;
    }
    $first = array_slice($responses, 0, 5);
    foreach ($first as $idx => $resp) {
        $slot = $idx + 1;
        $r['aid' . $slot] = $resp[0]; $r['ct' . $slot] = $resp[1]; $r['at' . $slot] = $resp[2]; $r['s' . $slot] = $resp[3];
    }
    $recent = array_reverse(array_slice($responses, -5));
    foreach ($recent as $idx => $resp) {
        $slot = $idx + 1;
        $r['raid' . $slot] = $resp[0]; $r['rct' . $slot] = $resp[1]; $r['rat' . $slot] = $resp[2]; $r['rs' . $slot] = $resp[3];
    }
    return $r;
}

// ---- Part 1: calculate_duty_response_seconds() honours $opts override ----

assert_eq('win 07:00 start inclusive', 3600,
    calculate_duty_response_seconds('2026-05-20 07:00:00', '2026-05-20 08:00:00', $OWNERSHIP_WINDOW));
assert_eq('win clips before 07:00', 1800,
    calculate_duty_response_seconds('2026-05-20 06:30:00', '2026-05-20 07:30:00', $OWNERSHIP_WINDOW));
assert_eq('win clips after 22:00', 1800,
    calculate_duty_response_seconds('2026-05-20 21:30:00', '2026-05-20 22:30:00', $OWNERSHIP_WINDOW));
assert_eq('win Sun included', 3600,
    calculate_duty_response_seconds('2026-05-24 10:00:00', '2026-05-24 11:00:00', $OWNERSHIP_WINDOW));
assert_eq('win Sat included', 7200,
    calculate_duty_response_seconds('2026-05-23 10:00:00', '2026-05-23 12:00:00', $OWNERSHIP_WINDOW));
assert_eq('win overnight Wed->Thu', 25200,
    calculate_duty_response_seconds('2026-05-20 18:00:00', '2026-05-21 10:00:00', $OWNERSHIP_WINDOW));

// Backward compatibility: no $opts -> global 7AM-10PM everyday unchanged.
assert_eq('global default 08:00 start', 3600,
    calculate_duty_response_seconds('2026-05-20 08:00:00', '2026-05-20 09:00:00'));
assert_eq('global default Sat included', 7200,
    calculate_duty_response_seconds('2026-05-23 10:00:00', '2026-05-23 12:00:00'));

// ---- Part 2: ownership first / recent / combined reducer ----

// Short lead (3 replies). first-5 and recent-5 hold the same 3 messages.
// combined dedups -> avg(60,120,180)=120. first = same 120. recent = same 120.
$short = reduce_ownership_duty_avgs(array(ownership_row(array(
    array('m1', '2026-05-20 09:29:00', '2026-05-20 09:30:00',  60),
    array('m2', '2026-05-20 10:58:00', '2026-05-20 11:00:00', 120),
    array('m3', '2026-05-20 13:57:00', '2026-05-20 14:00:00', 180),
))), $OWNERSHIP_WINDOW);
assert_eq('short first', 120, $short['first']);
assert_eq('short recent', 120, $short['recent']);
assert_eq('short combined', 120, $short['combined']);

// 12-reply lead. first-5 = m1..m5 (60s each), recent-5 = m8..m12 (120s each),
// no overlap. first=60, recent=120, combined avg(5x60 + 5x120)/10 = 90.
$long = reduce_ownership_duty_avgs(array(ownership_row(array(
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
))), $OWNERSHIP_WINDOW);
assert_eq('long first', 60, $long['first']);
assert_eq('long recent', 120, $long['recent']);
assert_eq('long combined', 90, $long['combined']);

// After-hours gap is duty-clipped through the 22:00 boundary on the merged value.
// m1 on-duty 60s; m2 spans 21:30->22:30 -> only 1800s on-duty. 2-reply lead, so
// first/recent share both. combined avg(60+1800)/2 = 930.
$clip = reduce_ownership_duty_avgs(array(ownership_row(array(
    array('m1', '2026-05-20 09:59:00', '2026-05-20 10:00:00',   60),
    array('m2', '2026-05-20 21:30:00', '2026-05-20 22:30:00', 3600),
))), $OWNERSHIP_WINDOW);
assert_eq('clip combined', 930, $clip['combined']);

// A Saturday lead now falls inside the everyday window -> gaps count in full.
$sat = reduce_ownership_duty_avgs(array(ownership_row(array(
    array('m1', '2026-05-23 10:00:00', '2026-05-23 10:30:00', 1800),
    array('m2', '2026-05-23 11:00:00', '2026-05-23 11:30:00', 1800),
))), $OWNERSHIP_WINDOW);
assert_eq('sat first', 1800, $sat['first']);
assert_eq('sat combined', 1800, $sat['combined']);

// Empty input -> all NULL.
$empty = reduce_ownership_duty_avgs(array(), $OWNERSHIP_WINDOW);
assert_eq('empty first', null, $empty['first']);
assert_eq('empty recent', null, $empty['recent']);
assert_eq('empty combined', null, $empty['combined']);

echo "\nAll assertions passed.\n";
