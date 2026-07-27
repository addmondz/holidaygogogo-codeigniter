<?php
/**
 * Run with: php tests/helpers/LeadReplyHourlyAvgResponseTest.php
 *
 * Locks the "Avg Response Time" card added to the Lead Reply Hourly page. The
 * model feeds ghl_message_log_average_reply_seconds() a single owner's message
 * stream for one day -- every inbound customer message plus the owner's OWN
 * outbound replies, grouped by conversation and ascending in time -- and the
 * controller runs the result through ghl_message_log_format_duration() to build
 * the card label. This test pins that composition:
 *   - inbound->owner-outbound pairs in a thread average their in-hours gaps,
 *   - out-of-hours / cross-day / cross-conversation pairs never count,
 *   - a stream with no qualifying pair renders '' so the card shows a dash.
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

// Card label = format_duration(average_reply_seconds(stream)).
function hourly_avg_label($rows) {
    return ghl_message_log_format_duration(ghl_message_log_average_reply_seconds($rows));
}

// --- Two in-hours replies in one thread: (30s, 90s) -> avg 60s -> "1m". ---
$rows = array(
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-01 09:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-01 09:00:30'),
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-01 10:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-01 10:01:30'),
);
assert_eq('avg of 30s + 90s', 60.0, ghl_message_log_average_reply_seconds($rows));
assert_eq('card label formats to 1m', '1m', hourly_avg_label($rows));

// --- Out-of-hours reply (before 7AM) does not count; only the 09:00 pair does. ---
$rows = array(
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-01 06:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-01 06:00:10'),
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-01 09:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-07-01 09:00:45'),
);
assert_eq('only the in-hours reply counts', 45.0, ghl_message_log_average_reply_seconds($rows));
assert_eq('card label 45s', '45s', hourly_avg_label($rows));

// --- No inbound->outbound pair (owner never replied): null -> '' (card shows dash). ---
$rows = array(
    array('conversation_id' => 'c1', 'direction' => 'inbound', 'ts' => '2026-07-01 09:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'inbound', 'ts' => '2026-07-01 09:05:00'),
);
assert_eq('no reply -> null', null, ghl_message_log_average_reply_seconds($rows));
assert_eq('no reply -> blank label (dash)', '', hourly_avg_label($rows));

// --- A reply must stay within its own conversation thread. ---
$rows = array(
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-07-01 09:00:00'),
    array('conversation_id' => 'c2', 'direction' => 'outbound', 'ts' => '2026-07-01 09:00:05'),
);
assert_eq('cross-conversation does not pair', null, ghl_message_log_average_reply_seconds($rows));

echo "\nAll Lead Reply Hourly avg-response tests passed.\n";
