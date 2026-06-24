<?php
/**
 * Run with: php tests/helpers/GhlMessageLogReplyTimeTest.php
 *
 * Locks the "Time Taken" feature of the Message Log -- only meaningful when the
 * list is filtered to a single agent, so the consecutive-message gap measures
 * how fast THAT agent moves from one message to the next.
 *
 * Rules pinned here:
 *   - per-row gap = (this message time) - (previous/older message time), but only
 *     within the SAME calendar day (an overnight jump is not "reply time"),
 *   - gaps are formatted compactly (4s, 1m 5s, 1h 2m),
 *   - the daily average is the mean consecutive same-day gap, derived by
 *     telescoping: within a day the gaps sum to (max - min), over (count - 1)
 *     intervals, so avg = SUM(max-min) / SUM(count-1) across days with >= 2 msgs.
 *     The GROUP-BY-DATE shape that feeds it is mirrored here in portable SQLite.
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

// --- Consecutive gap: same-day difference in seconds, else null. ---
assert_eq('gap within a day',
    4, ghl_message_log_consecutive_gap_seconds('2026-06-19 09:19:58', '2026-06-19 09:19:54'));
assert_eq('gap across midnight is null (not a reply)',
    null, ghl_message_log_consecutive_gap_seconds('2026-06-20 00:00:05', '2026-06-19 23:59:59'));
assert_eq('gap with unparseable time is null',
    null, ghl_message_log_consecutive_gap_seconds('2026-06-19 09:19:58', ''));

// --- Attach gaps to a newest-first page (extra trailing row supplies the
//     bottom row's predecessor; cross-day predecessor yields a blank gap). ---
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

// --- Daily average from per-day GROUP BY rows. ---
$dayRows = array(
    array('count' => 3, 'min_ts' => '2026-06-19 09:19:40', 'max_ts' => '2026-06-19 09:19:58'), // span 18s / 2 gaps
    array('count' => 1, 'min_ts' => '2026-06-18 17:00:00', 'max_ts' => '2026-06-18 17:00:00'), // single msg -> ignored
);
assert_eq('average gap seconds (18s over 2 intervals)', 9.0, ghl_message_log_average_gap_seconds($dayRows));
assert_eq('average null when no day has >= 2 messages',
    null, ghl_message_log_average_gap_seconds(array(array('count' => 1, 'min_ts' => 'x', 'max_ts' => 'x'))));

// --- SQLite mirror of the per-day aggregate query that feeds the average. ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_messages (id INTEGER PRIMARY KEY, user_id TEXT, date_added TEXT)");
$pdo->exec("INSERT INTO ghl_messages (id, user_id, date_added) VALUES
    (1, 'u-1', '2026-06-19 09:19:40'),
    (2, 'u-1', '2026-06-19 09:19:54'),
    (3, 'u-1', '2026-06-19 09:19:58'),
    (4, 'u-1', '2026-06-18 17:00:00')
");
// Same GROUP BY DATE / MIN / MAX shape the model issues; HAVING drops lone days.
$agg = $pdo->query(
    "SELECT DATE(date_added) AS d, COUNT(*) AS count, MIN(date_added) AS min_ts, MAX(date_added) AS max_ts
       FROM ghl_messages
      GROUP BY DATE(date_added)
     HAVING COUNT(*) >= 2"
)->fetchAll(PDO::FETCH_ASSOC);
$avg = ghl_message_log_average_gap_seconds($agg);
assert_eq('mirror: only the 3-message day counts', 9.0, $avg);
assert_eq('mirror: formats to 9s', '9s', ghl_message_log_format_duration($avg));

echo "\nAll assertions passed.\n";
