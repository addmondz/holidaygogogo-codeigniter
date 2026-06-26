<?php
/**
 * Run with: php tests/helpers/GhlMessageLogReplyTimeTest.php
 *
 * Locks the "Time Taken" feature of the Message Log -- only meaningful when the
 * list is filtered to a single agent.
 *
 * Two distinct measures live here:
 *   - the per-row "Time Taken" COLUMN is the consecutive same-day gap to the
 *     message directly below, shown for EVERY row regardless of direction (an
 *     overnight jump is blank). It is a raw cadence read,
 *   - the "Avg time taken" SUMMARY is agent reply time: a customer INBOUND
 *     answered by the agent's next OUTBOUND in the SAME conversation, counted
 *     only when BOTH ends land on the same day within working
 *     hours 07:00:00-22:00:00. A pair that leaves the window (overnight,
 *     before 7AM / after 10PM) is EXCLUDED entirely, not clamped. The average is
 *     the mean of every qualifying gap; the conversation-ordered query shape that
 *     feeds it is mirrored here in portable SQLite.
 *
 * Gaps are formatted compactly (4s, 1m 5s, 1h 2m).
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

// --- Duration formatting: compact, no over-precision past minutes. ---
assert_eq('format 4s',          '4s',      ghl_message_log_format_duration(4));
assert_eq('format 0s',          '0s',      ghl_message_log_format_duration(0));
assert_eq('format 59s',         '59s',     ghl_message_log_format_duration(59));
assert_eq('format exactly 1m',  '1m',      ghl_message_log_format_duration(60));
assert_eq('format 1m 5s',       '1m 5s',   ghl_message_log_format_duration(65));
assert_eq('format exactly 1h',  '1h',      ghl_message_log_format_duration(3600));
assert_eq('format 1h 2m (drops seconds)', '1h 2m', ghl_message_log_format_duration(3725));
assert_eq('format null is blank',  '',     ghl_message_log_format_duration(null));
assert_eq('format negative blank', '',     ghl_message_log_format_duration(-5));

// --- Business hours window: everyday, 07:00:00-22:00:00 inclusive. ---
// 2026-06-19 = Friday, 2026-06-20 = Saturday, 2026-06-21 = Sunday.
assert_eq('mid-window is in hours',
    true, ghl_message_log_within_business_hours('2026-06-19 12:00:00'));
assert_eq('07:00:00 sharp is in hours',
    true, ghl_message_log_within_business_hours('2026-06-19 07:00:00'));
assert_eq('22:00:00 sharp is in hours',
    true, ghl_message_log_within_business_hours('2026-06-19 22:00:00'));
assert_eq('before 7AM is out of hours',
    false, ghl_message_log_within_business_hours('2026-06-19 06:59:59'));
assert_eq('after 10PM is out of hours',
    false, ghl_message_log_within_business_hours('2026-06-19 22:00:01'));
assert_eq('Saturday is in hours now',
    true, ghl_message_log_within_business_hours('2026-06-20 10:00:00'));
assert_eq('Sunday is in hours now',
    true, ghl_message_log_within_business_hours('2026-06-21 10:00:00'));
assert_eq('unparseable time is out of hours',
    false, ghl_message_log_within_business_hours(''));

// --- Reply pair: inbound -> outbound seconds, only when both endpoints are
//     same-day within working hours; else null. ---
assert_eq('reply within working hours',
    4, ghl_message_log_reply_pair_seconds('2026-06-19 09:19:54', '2026-06-19 09:19:58'));
assert_eq('reply before the inbound is null',
    null, ghl_message_log_reply_pair_seconds('2026-06-19 09:19:58', '2026-06-19 09:19:54'));
assert_eq('reply across days is null (overnight leaves window)',
    null, ghl_message_log_reply_pair_seconds('2026-06-19 18:00:00', '2026-06-22 09:00:00'));
assert_eq('weekend pair now counts (same-day, in hours)',
    30, ghl_message_log_reply_pair_seconds('2026-06-20 10:00:00', '2026-06-20 10:00:30'));
assert_eq('reply landing after 10PM is excluded',
    null, ghl_message_log_reply_pair_seconds('2026-06-19 21:50:00', '2026-06-19 22:30:00'));
assert_eq('inbound before 7AM is excluded',
    null, ghl_message_log_reply_pair_seconds('2026-06-19 06:50:00', '2026-06-19 07:10:00'));
assert_eq('unparseable pair is null',
    null, ghl_message_log_reply_pair_seconds('2026-06-19 09:19:58', ''));

// --- Per-row "Time Taken" column: consecutive same-day gap, shown for EVERY
//     row regardless of direction (deliberately simpler than the average). ---
assert_eq('gap within a day',
    4, ghl_message_log_consecutive_gap_seconds('2026-06-19 09:19:58', '2026-06-19 09:19:54'));
assert_eq('gap across midnight is null (not a reply)',
    null, ghl_message_log_consecutive_gap_seconds('2026-06-20 00:00:05', '2026-06-19 23:59:59'));
assert_eq('gap with unparseable time is null',
    null, ghl_message_log_consecutive_gap_seconds('2026-06-19 09:19:58', ''));

// Attach gaps to a newest-first page (extra trailing row supplies the bottom
// row's predecessor; a cross-day predecessor yields a blank gap).
$rows = array(
    array('message_timestamp' => '2026-06-19 09:19:58', 'body' => 'a'),
    array('message_timestamp' => '2026-06-19 09:19:54', 'body' => 'b'),
    array('message_timestamp' => '2026-06-19 09:19:40', 'body' => 'c'),
    array('message_timestamp' => '2026-06-18 17:00:00', 'body' => 'd'), // prev day -> no gap for row c
);
$withGaps = ghl_message_log_attach_reply_gaps($rows, 3);
assert_eq('display sliced to requested count', 3, count($withGaps));
assert_eq('row0 gap label', '4s',  $withGaps[0]['reply_gap_label']);
assert_eq('row1 gap label', '14s', $withGaps[1]['reply_gap_label']);
assert_eq('row2 gap blank across day', '', $withGaps[2]['reply_gap_label']);
assert_eq('row0 gap seconds', 4,  $withGaps[0]['reply_gap_seconds']);
assert_eq('row2 gap seconds null', null, $withGaps[2]['reply_gap_seconds']);

// Oldest row overall (no trailing predecessor) also gets a blank gap.
$last = ghl_message_log_attach_reply_gaps(array(
    array('message_timestamp' => '2026-06-19 09:19:58', 'body' => 'only'),
), 1);
assert_eq('lone/oldest row has blank gap', '', $last[0]['reply_gap_label']);

// --- Average reply time across conversation-ordered rows. ---
$ordered = array(
    // c1, Friday: two clean replies (4s, 10s).
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-06-19 09:00:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-06-19 09:00:04'),
    array('conversation_id' => 'c1', 'direction' => 'inbound',  'ts' => '2026-06-19 09:10:00'),
    array('conversation_id' => 'c1', 'direction' => 'outbound', 'ts' => '2026-06-19 09:10:10'),
    // c2, reply lands after 10PM: excluded.
    array('conversation_id' => 'c2', 'direction' => 'inbound',  'ts' => '2026-06-19 21:50:00'),
    array('conversation_id' => 'c2', 'direction' => 'outbound', 'ts' => '2026-06-19 22:30:00'),
    // c3, inbound before 7AM: excluded.
    array('conversation_id' => 'c3', 'direction' => 'inbound',  'ts' => '2026-06-19 06:50:00'),
    array('conversation_id' => 'c3', 'direction' => 'outbound', 'ts' => '2026-06-19 07:10:00'),
    // c4, overnight across days: excluded.
    array('conversation_id' => 'c4', 'direction' => 'inbound',  'ts' => '2026-06-19 18:00:00'),
    array('conversation_id' => 'c4', 'direction' => 'outbound', 'ts' => '2026-06-22 09:00:00'),
);
assert_eq('average of the two qualifying replies (4s, 10s)',
    7.0, ghl_message_log_average_reply_seconds($ordered));
assert_eq('average null when nothing qualifies',
    null, ghl_message_log_average_reply_seconds(array(
        array('conversation_id' => 'c2', 'direction' => 'inbound',  'ts' => '2026-06-19 23:00:00'),
        array('conversation_id' => 'c2', 'direction' => 'outbound', 'ts' => '2026-06-19 23:00:30'),
    )));

// An outbound paired with an inbound from a DIFFERENT conversation must not count.
assert_eq('cross-conversation does not pair in the average',
    null, ghl_message_log_average_reply_seconds(array(
        array('conversation_id' => 'a', 'direction' => 'inbound',  'ts' => '2026-06-19 09:00:00'),
        array('conversation_id' => 'b', 'direction' => 'outbound', 'ts' => '2026-06-19 09:00:05'),
    )));

// --- SQLite mirror of the conversation-ordered query that feeds the average. ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_messages (id INTEGER PRIMARY KEY, conversation_id TEXT, direction TEXT, date_added TEXT)");
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, direction, date_added) VALUES
    (1, 'c1', 'inbound',  '2026-06-19 09:00:00'),
    (2, 'c1', 'outbound', '2026-06-19 09:00:04'),
    (3, 'c1', 'inbound',  '2026-06-19 09:10:00'),
    (4, 'c1', 'outbound', '2026-06-19 09:10:10'),
    (5, 'c2', 'inbound',  '2026-06-19 21:50:00'),
    (6, 'c2', 'outbound', '2026-06-19 22:30:00')
");
// Same conversation-ordered shape the model issues so adjacent pairing is sound.
$ordered = $pdo->query(
    "SELECT conversation_id AS conversation_id, direction AS direction, date_added AS ts
       FROM ghl_messages
      ORDER BY conversation_id ASC, date_added ASC, id ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$avg = ghl_message_log_average_reply_seconds($ordered);
assert_eq('mirror: only the in-hours replies count', 7.0, $avg);
assert_eq('mirror: formats to 7s', '7s', ghl_message_log_format_duration($avg));

echo "\nAll assertions passed.\n";
