<?php
/**
 * Run with: php tests/helpers/GhlMessageLogTimeRangeTest.php
 *
 * Covers the "Time of Day" range filter on the Message Log, which narrows the
 * log to a daily clock window (e.g. 09:00-18:00) on top of the date range:
 *
 *   1. ghl_message_log_normalize_time() — sanitise a `time_from`/`time_to` URL
 *      param to a canonical 'HH:MM' or '' (no bound), so nothing but a real
 *      clock value can reach the TIME() clause.
 *   2. A portable-SQLite mirror of the time-narrowed query
 *      (Report_Model::ghl_message_time_range_clause adds a TIME(gm.<col>) test),
 *      proving the from-only, to-only, in-day and wrap-past-midnight windows all
 *      keep exactly the right rows.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// 1) normalize_time --------------------------------------------------------
assert_eq('normalize "9" => "09:00"', '09:00', ghl_message_log_normalize_time('9'));
assert_eq('normalize "09" => "09:00"', '09:00', ghl_message_log_normalize_time('09'));
assert_eq('normalize "09:00" => "09:00"', '09:00', ghl_message_log_normalize_time('09:00'));
assert_eq('normalize "9:30" => "09:30"', '09:30', ghl_message_log_normalize_time('9:30'));
assert_eq('normalize "18:05" => "18:05"', '18:05', ghl_message_log_normalize_time('18:05'));
assert_eq('normalize "23:59" => "23:59"', '23:59', ghl_message_log_normalize_time('23:59'));
assert_eq('normalize "0" => "00:00"', '00:00', ghl_message_log_normalize_time('0'));
assert_eq('normalize " 9:00 " (spaces) => "09:00"', '09:00', ghl_message_log_normalize_time(' 9:00 '));
assert_eq('normalize "24:00" (hour out of range) => ""', '', ghl_message_log_normalize_time('24:00'));
assert_eq('normalize "9:60" (minute out of range) => ""', '', ghl_message_log_normalize_time('9:60'));
assert_eq('normalize "" => ""', '', ghl_message_log_normalize_time(''));
assert_eq('normalize null => ""', '', ghl_message_log_normalize_time(null));
assert_eq('normalize "9 OR 1=1" (injection) => ""', '', ghl_message_log_normalize_time('9 OR 1=1'));
assert_eq('normalize "9am" => ""', '', ghl_message_log_normalize_time('9am'));
assert_eq('normalize "9:5" (short minute) => ""', '', ghl_message_log_normalize_time('9:5'));

// 2) SQL mirror: the TIME() range clause keeps exactly the right rows --------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_messages (id INTEGER PRIMARY KEY, body TEXT, date_added TEXT)");

// One row per hour of the day, plus a couple of edge minutes. The clock time is
// what matters; the two dates prove the window repeats across every day.
$pdo->exec("INSERT INTO ghl_messages (id, body, date_added) VALUES
    (1, '08:59', '2026-07-15 08:59:30'),
    (2, '09:00', '2026-07-15 09:00:00'),
    (3, '12:30', '2026-07-15 12:30:00'),
    (4, '18:00', '2026-07-15 18:00:45'),
    (5, '18:01', '2026-07-15 18:01:00'),
    (6, '23:30 day1', '2026-07-15 23:30:00'),
    (7, '01:00 day2', '2026-07-16 01:00:00'),
    (8, '09:15 day2', '2026-07-16 09:15:00')
");

// Mirror of ghl_message_time_range_clause(): MySQL TIME(col) BETWEEN 'HH:MM:00'
// AND 'HH:MM:59' becomes SQLite strftime('%H:%M:%S', col) with the same bounds.
// Zero-padded time strings compare lexicographically, so BETWEEN is exact.
$T = "strftime('%H:%M:%S', gm.date_added)";

$run = function ($sql, $params) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
};

// In-day window 09:00-18:00: keeps 09:00, 12:30, 18:00 (:45 within the :59
// grace) across both days; drops 08:59, 18:01, the 23:30 and 01:00 rows.
$between = "SELECT id FROM ghl_messages gm WHERE {$T} BETWEEN ? AND ? ORDER BY id";
assert_eq('09:00-18:00 keeps only in-window rows both days',
    array(2, 3, 4, 8), $run($between, array('09:00:00', '18:00:59')));

// From-only 18:00: TIME >= 18:00:00 keeps 18:00, 18:01, 23:30.
$from = "SELECT id FROM ghl_messages gm WHERE {$T} >= ? ORDER BY id";
assert_eq('from 18:00 keeps 18:00 onward', array(4, 5, 6), $run($from, array('18:00:00')));

// To-only 09:00: TIME <= 09:00:59 keeps 08:59, both 09:00/09:15... but 09:15 is
// past 09:00:59, so only 08:59, 09:00 and the 01:00 row.
$to = "SELECT id FROM ghl_messages gm WHERE {$T} <= ? ORDER BY id";
assert_eq('to 09:00 keeps up to 09:00:59', array(1, 2, 7), $run($to, array('09:00:59')));

// Wrap past midnight 22:00-02:00: TIME >= 22:00:00 OR TIME <= 02:00:59 keeps the
// late-night 23:30 and the early-morning 01:00 rows only.
$wrap = "SELECT id FROM ghl_messages gm WHERE ({$T} >= ? OR {$T} <= ?) ORDER BY id";
assert_eq('22:00-02:00 wraps midnight', array(6, 7), $run($wrap, array('22:00:00', '02:00:59')));

// Single-minute window 12:30-12:30 keeps just that minute.
assert_eq('12:30-12:30 keeps only that minute',
    array(3), $run($between, array('12:30:00', '12:30:59')));

echo "\nAll assertions passed.\n";
