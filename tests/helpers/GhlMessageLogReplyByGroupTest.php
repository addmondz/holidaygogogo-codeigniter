<?php
/**
 * Run with: php tests/helpers/GhlMessageLogReplyByGroupTest.php
 *
 * Locks ghl_message_log_average_reply_seconds_by_group() -- the per-agent
 * variant that powers the dashboard's team-wide "Best: reply time" benchmark.
 * It must apply the SAME inbound->outbound pairing and in-hours rule as the
 * single-stream ghl_message_log_average_reply_seconds(), but tally per group:
 *   - a pair counts only when both rows share group AND conversation,
 *   - out-of-hours / overnight pairs are excluded (not clamped),
 *   - lead_count is the number of distinct conversations that contributed >=1
 *     pair (the sample used for the "minimum N leads" leaderboard threshold).
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

$row = function ($agent, $conv, $dir, $ts) {
    return array('agent_id' => $agent, 'conversation_id' => $conv, 'direction' => $dir, 'ts' => $ts);
};

// --- Two agents, in-hours pairs, kept separate. ---
$rows = array(
    // Agent A, conversation c1: inbound 10:00:00 -> outbound 10:00:30 (30s)
    $row('A', 'c1', 'inbound',  '2026-06-19 10:00:00'),
    $row('A', 'c1', 'outbound', '2026-06-19 10:00:30'),
    // Agent A, conversation c2: inbound 11:00:00 -> outbound 11:00:50 (50s)
    $row('A', 'c2', 'inbound',  '2026-06-19 11:00:00'),
    $row('A', 'c2', 'outbound', '2026-06-19 11:00:50'),
    // Agent B, conversation c3: inbound 09:00:00 -> outbound 09:02:00 (120s)
    $row('B', 'c3', 'inbound',  '2026-06-19 09:00:00'),
    $row('B', 'c3', 'outbound', '2026-06-19 09:02:00'),
);
$out = ghl_message_log_average_reply_seconds_by_group($rows);
assert_eq('A avg = (30+50)/2 = 40.0', 40.0, $out['A']['avg_seconds']);
assert_eq('A lead_count = 2 conversations', 2, $out['A']['lead_count']);
assert_eq('B avg = 120.0',               120.0, $out['B']['avg_seconds']);
assert_eq('B lead_count = 1',                 1, $out['B']['lead_count']);

// --- No phantom pair across a conversation boundary within the same agent. ---
$rows = array(
    $row('A', 'c1', 'inbound',  '2026-06-19 10:00:00'),
    // next row is a different conversation: the inbound above has no reply
    $row('A', 'c2', 'outbound', '2026-06-19 10:00:10'),
);
$out = ghl_message_log_average_reply_seconds_by_group($rows);
assert_eq('cross-conversation does not pair', array(), $out);

// --- Overnight / out-of-hours pair is excluded entirely. ---
$rows = array(
    $row('A', 'c1', 'inbound',  '2026-06-19 21:59:00'), // in hours
    $row('A', 'c1', 'outbound', '2026-06-20 08:00:00'), // next day -> excluded
);
$out = ghl_message_log_average_reply_seconds_by_group($rows);
assert_eq('overnight pair excluded', array(), $out);

// --- A reply before its inbound (clock skew) is not a valid pair. ---
$rows = array(
    $row('A', 'c1', 'inbound',  '2026-06-19 10:00:30'),
    $row('A', 'c1', 'outbound', '2026-06-19 10:00:00'),
);
$out = ghl_message_log_average_reply_seconds_by_group($rows);
assert_eq('reply before inbound excluded', array(), $out);

// --- Only the inbound->outbound transition counts; outbound->outbound ignored. ---
$rows = array(
    $row('A', 'c1', 'inbound',  '2026-06-19 10:00:00'),
    $row('A', 'c1', 'outbound', '2026-06-19 10:00:20'), // 20s pair
    $row('A', 'c1', 'outbound', '2026-06-19 10:05:00'), // agent double-texts, no pair
);
$out = ghl_message_log_average_reply_seconds_by_group($rows);
assert_eq('single pair only', 20.0, $out['A']['avg_seconds']);
assert_eq('single conversation', 1, $out['A']['lead_count']);

echo "\nAll ghl_message_log_average_reply_seconds_by_group tests passed.\n";
