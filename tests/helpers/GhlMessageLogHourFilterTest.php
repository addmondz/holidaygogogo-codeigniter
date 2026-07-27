<?php
/**
 * Run with: php tests/helpers/GhlMessageLogHourFilterTest.php
 *
 * Covers the hour-of-day filter that powers the "Leads Handled" drill-down from
 * Lead Reply Hourly into the Message Log:
 *
 *   1. ghl_message_log_normalize_hour() — sanitise the `hour` URL param to 0-23
 *      or null (no filter), so a stray value can never reach the HOUR() clause.
 *   2. ghl_message_log_hour_label()     — 0-23 -> "12 AM" .. "11 PM" for the
 *      on-screen active-filter chip (matches the hourly table's labels).
 *   3. A portable-SQLite mirror of the hour-narrowed Message Log query
 *      (Report_Model::Ghl_Messages_Log adds ` AND HOUR(gm.<col>) = ?`), proving
 *      the agent + hour filters compose to a single hour's rows.
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

// 1) normalize_hour --------------------------------------------------------
assert_eq('normalize "11" => 11', 11, ghl_message_log_normalize_hour('11'));
assert_eq('normalize 0 => 0', 0, ghl_message_log_normalize_hour(0));
assert_eq('normalize "0" => 0', 0, ghl_message_log_normalize_hour('0'));
assert_eq('normalize "07" (leading zero) => 7', 7, ghl_message_log_normalize_hour('07'));
assert_eq('normalize "23" => 23', 23, ghl_message_log_normalize_hour('23'));
assert_eq('normalize "24" (out of range) => null', null, ghl_message_log_normalize_hour('24'));
assert_eq('normalize "-1" => null', null, ghl_message_log_normalize_hour('-1'));
assert_eq('normalize "" => null', null, ghl_message_log_normalize_hour(''));
assert_eq('normalize null => null', null, ghl_message_log_normalize_hour(null));
assert_eq('normalize "9 OR 1=1" (injection) => null', null, ghl_message_log_normalize_hour('9 OR 1=1'));
assert_eq('normalize "7.5" => null', null, ghl_message_log_normalize_hour('7.5'));

// 2) hour_label ------------------------------------------------------------
assert_eq('label 0 => "12 AM"', '12 AM', ghl_message_log_hour_label(0));
assert_eq('label 11 => "11 AM"', '11 AM', ghl_message_log_hour_label(11));
assert_eq('label 12 => "12 PM"', '12 PM', ghl_message_log_hour_label(12));
assert_eq('label 13 => "1 PM"', '1 PM', ghl_message_log_hour_label(13));
assert_eq('label 23 => "11 PM"', '11 PM', ghl_message_log_hour_label(23));
assert_eq('label "07" => "7 AM"', '7 AM', ghl_message_log_hour_label('07'));
assert_eq('label invalid => ""', '', ghl_message_log_hour_label(24));

// 3) SQL mirror: agent + hour compose to a single hour's rows --------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY,
    conversation_id TEXT,
    from_number TEXT,
    to_number TEXT,
    user_id TEXT,
    direction TEXT,
    body TEXT,
    date_added TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");
$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES ('u-1', 'Agent Alice'), ('u-2', 'Agent Bob')");
$pdo->exec("CREATE TABLE ghl_conversations (conversation_id TEXT, contact_id TEXT, contact_name TEXT, full_name TEXT, assigned_to TEXT)");
$pdo->exec("INSERT INTO ghl_conversations (conversation_id, contact_id, contact_name, full_name, assigned_to) VALUES
    ('cv-a', 'ct-1', 'Lead One', '', 'u-1'),
    ('cv-b', 'ct-2', '', 'Lead Two', 'u-2')
");

// Alice: two messages at 11:xx (ids 10,11) and one at 12:xx (id 12).
// Bob: one message at 11:xx (id 20) -- same hour, different agent.
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, from_number, to_number, user_id, direction, body, date_added) VALUES
    (10, 'cv-a', '+60111', '+60999', 'u-1', 'outbound', 'alice 11a', '2026-07-15 11:05:00'),
    (11, 'cv-a', '+60999', '+60111', NULL,  'inbound',  'alice 11b', '2026-07-15 11:40:00'),
    (12, 'cv-a', '+60111', '+60999', 'u-1', 'outbound', 'alice 12',  '2026-07-15 12:10:00'),
    (20, 'cv-b', '+60222', '+60999', 'u-2', 'outbound', 'bob 11',    '2026-07-15 11:20:00')
");

$start = '2026-07-15 00:00:00';
$end   = '2026-07-15 23:59:59';

// The model uses MySQL HOUR(); SQLite's portable equivalent is
// CAST(strftime('%H', ...) AS INTEGER). Same integer 0-23 comparison.
$hourSql = "SELECT gm.id
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
            LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
            WHERE gm.date_added >= :s AND gm.date_added <= :e
              AND COALESCE(NULLIF(gu.Name, ''), NULLIF(gu_assigned.Name, '')) = :a
              AND CAST(strftime('%H', gm.date_added) AS INTEGER) = :h
            ORDER BY gm.date_added DESC, gm.id DESC";
$stmt = $pdo->prepare($hourSql);

// Alice at 11 AM: ids 11, 10 -- the 12 PM row (12) and Bob's row (20) drop out.
$stmt->execute(array(':s' => $start, ':e' => $end, ':a' => 'Agent Alice', ':h' => 11));
$alice11 = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('Alice + 11AM keeps only her 11 AM rows', array(11, 10), $alice11);

// Alice at 12 PM: only id 12.
$stmt->execute(array(':s' => $start, ':e' => $end, ':a' => 'Agent Alice', ':h' => 12));
$alice12 = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('Alice + 12PM keeps only her 12 PM row', array(12), $alice12);

// Same hour, other agent stays isolated: Bob + 11 AM -> only id 20.
$stmt->execute(array(':s' => $start, ':e' => $end, ':a' => 'Agent Bob', ':h' => 11));
$bob11 = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('Bob + 11AM isolated from Alice same hour', array(20), $bob11);

// Empty hour returns nothing.
$stmt->execute(array(':s' => $start, ':e' => $end, ':a' => 'Agent Alice', ':h' => 3));
$alice3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
assert_eq('Alice + empty hour returns nothing', 0, count($alice3));

echo "\nAll assertions passed.\n";
