<?php
/**
 * Run with: php tests/helpers/OwnerMatrixReplyOwnershipParityTest.php
 *
 * Locks the fix that makes the OWNER dashboard "Agent Performance" matrix REPLY
 * TIME column agree with the "Lead Reply Hourly" page's AVG RESPONSE TIME card.
 *
 * Both now read the SAME point-in-time lead-ownership source: a message counts
 * for the owner who held the lead at the moment it was sent, and only the
 * owner's OWN outbound replies pair with inbound customer messages. The report
 * (Lead_Reply_Activity_Hourly_Avg_Reply_Seconds_By_Owner) computes ONE owner at
 * a time via ghl_message_log_average_reply_seconds(); the matrix
 * (Ghl_Ownership_Avg_Reply_By_Owner) computes EVERY owner at once via
 * ghl_message_log_average_reply_seconds_by_group() grouped on owner_user_id.
 *
 * This test proves those two paths return the SAME per-owner seconds even when a
 * lead is reassigned mid-thread -- so one person shows one number on both pages.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Point-in-time owner attribution, exactly as the SQL join does it: each message
// row is tagged with the owner whose [started, ended) window contains it, and an
// outbound only survives when its sender IS that owner (the report's direction
// rule). Returns rows shaped for the grouping helper, ordered owner->conv->time.
function attribute_by_ownership($messages, $ownership) {
    $rows = array();
    foreach ($messages as $m) {
        foreach ($ownership as $o) {
            if ($m['conversation_id'] !== $o['conversation_id']) { continue; }
            if ($m['ts'] < $o['started']) { continue; }
            if ($o['ended'] !== null && $m['ts'] >= $o['ended']) { continue; }
            // inbound counts for the window owner; outbound only if the owner sent it.
            if ($m['direction'] === 'outbound' && $m['user_id'] !== $o['owner']) { continue; }
            $rows[] = array(
                'agent_id'        => $o['owner'],
                'conversation_id' => $m['conversation_id'],
                'direction'       => $m['direction'],
                'ts'              => $m['ts'],
            );
        }
    }
    usort($rows, function ($a, $b) {
        return array($a['agent_id'], $a['conversation_id'], $a['ts'])
           <=> array($b['agent_id'], $b['conversation_id'], $b['ts']);
    });
    return $rows;
}

// The report path: filter the attributed stream to one owner, average it alone.
function report_owner_seconds($rows, $owner) {
    $only = array_values(array_filter($rows, function ($r) use ($owner) {
        return $r['agent_id'] === $owner;
    }));
    return ghl_message_log_average_reply_seconds($only);
}

// --- Lead c1 reassigned from owner A to owner B mid-thread. ---
// A owns 09:00-10:30; B owns from 10:30. Each answers one inbound in their window.
$ownership = array(
    array('conversation_id' => 'c1', 'owner' => 'A', 'started' => '2026-07-08 09:00:00', 'ended' => '2026-07-08 10:30:00'),
    array('conversation_id' => 'c1', 'owner' => 'B', 'started' => '2026-07-08 10:30:00', 'ended' => null),
    array('conversation_id' => 'c2', 'owner' => 'A', 'started' => '2026-07-08 08:00:00', 'ended' => null),
);
$messages = array(
    // c1 while A owns it: 40s reply by A
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-08 09:10:00', 'user_id' => ''),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-08 09:10:40', 'user_id' => 'A'),
    // c1 after reassignment to B: 120s reply by B
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-08 11:00:00', 'user_id' => ''),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-08 11:02:00', 'user_id' => 'B'),
    // c2 owned by A throughout: 20s reply by A
    array('conversation_id' => 'c2', 'direction' => 'inbound',  'ts' => '2026-07-08 08:30:00', 'user_id' => ''),
    array('conversation_id' => 'c2', 'direction' => 'outbound', 'ts' => '2026-07-08 08:30:20', 'user_id' => 'A'),
    // a stray reply B sends INSIDE A's window (user_id=B != owner A): must NOT count for anyone
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-08 10:00:00', 'user_id' => ''),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-08 10:00:05', 'user_id' => 'B'),
);

$attributed = attribute_by_ownership($messages, $ownership);

// Matrix path: one grouped pass over EVERY owner.
$byGroup = ghl_message_log_average_reply_seconds_by_group($attributed, 'agent_id', 'ts');

// A: (40s from c1) + (20s from c2) => avg 30s over 2 conversations.
assert_eq('matrix A avg = (40+20)/2', 30.0, $byGroup['A']['avg_seconds']);
assert_eq('matrix A lead_count = 2',  2,     $byGroup['A']['lead_count']);
// B: only the 120s reply after reassignment (the stray 10:00 reply sat in A's
// window, so B never owned that inbound and it does not pair).
assert_eq('matrix B avg = 120',       120.0, $byGroup['B']['avg_seconds']);
assert_eq('matrix B lead_count = 1',  1,     $byGroup['B']['lead_count']);

// PARITY: the grouped matrix number equals the per-owner report number.
assert_eq('A: matrix == report', report_owner_seconds($attributed, 'A'), $byGroup['A']['avg_seconds']);
assert_eq('B: matrix == report', report_owner_seconds($attributed, 'B'), $byGroup['B']['avg_seconds']);

// The stray cross-owner reply is credited to NOBODY (not A, not B).
$strayCounted = false;
foreach ($byGroup as $stat) {
    // 10:00:05 - 10:00:00 = 5s; if it had counted for A it would drag the avg off 30.
    if ($stat['avg_seconds'] !== null && $stat['avg_seconds'] < 30.0) { $strayCounted = true; }
}
assert_eq('stray cross-owner reply ignored', false, $strayCounted);

echo "\nAll owner-matrix reply/ownership parity tests passed.\n";
