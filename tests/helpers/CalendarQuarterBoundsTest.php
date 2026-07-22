<?php
/**
 * Run with: php tests/helpers/CalendarQuarterBoundsTest.php
 *
 * Locks the (today → quarter_start, quarter_end) mapping used by the booking
 * summary cards for the OWNER role. Owner-level Cards 8 (Conversion &
 * Response) and 9 (Top Agents – Conversion) compute over the current
 * calendar quarter instead of the current month. The same expression is
 * inlined in Booking::ajax_summary_cards; if anyone "simplifies" it we want
 * a clear PASS/FAIL signal rather than a silently-wrong window.
 *
 * Quarter convention (calendar):
 *   Q1: Jan–Mar  · Q2: Apr–Jun  · Q3: Jul–Sep  · Q4: Oct–Dec
 *
 * Empty BETWEEN behaviour: as with month, future-dated rows can't exist yet
 * so a BETWEEN Q-start AND Q-end effectively returns quarter-start → today.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

/**
 * Mirrors the inline computation in Booking::ajax_summary_cards.
 * Pure function so we can drive it with arbitrary "today" values.
 */
function quarter_bounds_for($today) {
    $month         = (int) date('n', strtotime($today));
    $q_idx         = (int) ceil($month / 3);
    $q_start_month = ($q_idx - 1) * 3 + 1;
    $year          = date('Y', strtotime($today));
    $start         = $year . '-' . sprintf('%02d', $q_start_month) . '-01';
    $end           = date('Y-m-t', strtotime($start . ' +2 months'));
    return array($start, $end, $q_idx);
}

$cases = array(
    // today        => expected [start, end, q_idx]
    '2026-01-01' => array('2026-01-01', '2026-03-31', 1),
    '2026-01-15' => array('2026-01-01', '2026-03-31', 1),
    '2026-03-31' => array('2026-01-01', '2026-03-31', 1),
    '2026-04-01' => array('2026-04-01', '2026-06-30', 2),
    '2026-05-19' => array('2026-04-01', '2026-06-30', 2),
    '2026-06-30' => array('2026-04-01', '2026-06-30', 2),
    '2026-07-01' => array('2026-07-01', '2026-09-30', 3),
    '2026-09-30' => array('2026-07-01', '2026-09-30', 3),
    '2026-10-01' => array('2026-10-01', '2026-12-31', 4),
    '2026-12-31' => array('2026-10-01', '2026-12-31', 4),
    // Leap-year February sanity (Feb 29 in Q1 of 2024)
    '2024-02-29' => array('2024-01-01', '2024-03-31', 1),
);

$failed = false;
foreach ($cases as $today => $expected) {
    $got = quarter_bounds_for($today);
    $ok  = ($got === $expected);
    $label = "today={$today} → Q{$expected[2]} [{$expected[0]}..{$expected[1]}]";
    if ($ok) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: got Q{$got[2]} [{$got[0]}..{$got[1]}]\n";
        $failed = true;
    }
}

if ($failed) { exit(1); }

echo "\nAll assertions passed.\n";
