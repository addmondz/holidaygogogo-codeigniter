<?php
/**
 * Run with: php tests/helpers/GhlMessagesLogQueryTest.php
 *
 * Locks the SQL contract behind Report_Model::Ghl_Messages_Log() and
 * Ghl_Messages_Log_Count(). Those run on MySQL; here we mirror their intent in
 * portable SQLite so the rules are pinned without a live DB:
 *
 *   - rows are filtered to the inclusive [start 00:00:00, end 23:59:59] window,
 *   - ordered newest first (date_added DESC, id DESC as a stable tiebreak),
 *   - windowed by LIMIT/OFFSET for the requested page,
 *   - each row exposes exactly the four display fields: date_added, from_number,
 *     to_number, body.
 *   - the COUNT query counts the same filtered window (drives pagination).
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

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY,
    from_number TEXT,
    to_number TEXT,
    body TEXT,
    date_added TEXT
)");

// 5 rows over 2026-06-14..16; one (id 99) is OUTSIDE the test window (06-13).
$pdo->exec("INSERT INTO ghl_messages (id, from_number, to_number, body, date_added) VALUES
    (10, '+60123', '+60999', 'first',  '2026-06-14 08:00:00'),
    (11, '+60124', '+60999', 'second', '2026-06-15 09:00:00'),
    (12, '+60999', '+60125', 'third',  '2026-06-15 09:00:00'),
    (13, '+60126', '+60999', 'fourth', '2026-06-16 23:59:59'),
    (99, '+60127', '+60999', 'before', '2026-06-13 10:00:00')
");

$start = '2026-06-14 00:00:00';
$end   = '2026-06-16 23:59:59';

// COUNT mirror.
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ghl_messages WHERE date_added >= :s AND date_added <= :e"
);
$countStmt->execute(array(':s' => $start, ':e' => $end));
$total = (int) $countStmt->fetchColumn();
assert_eq('count excludes out-of-window row', 4, $total);

// Page mirror: newest first, stable tiebreak, windowed.
$pageSql = "SELECT date_added, from_number, to_number, body
            FROM ghl_messages
            WHERE date_added >= :s AND date_added <= :e
            ORDER BY date_added DESC, id DESC
            LIMIT :lim OFFSET :off";

$pg = ghl_messages_log_pagination($total, 1, 2);
$stmt = $pdo->prepare($pageSql);
$stmt->bindValue(':s', $start);
$stmt->bindValue(':e', $end);
$stmt->bindValue(':lim', $pg['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$page1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

assert_eq('page1 size (limit 2)', 2, count($page1));
// Newest first: id 13 (06-16) then the 06-15 pair, DESC id -> id 12 before id 11.
assert_eq('row0 newest body', 'fourth', $page1[0]['body']);
assert_eq('row1 tiebreak body (id12 before id11)', 'third', $page1[1]['body']);
// Exactly the four display fields are present.
assert_eq('row exposes 4 fields', array('date_added', 'from_number', 'to_number', 'body'), array_keys($page1[0]));

// Page 2 continues the order without overlap.
$pg2 = ghl_messages_log_pagination($total, 2, 2);
$stmt->bindValue(':s', $start);
$stmt->bindValue(':e', $end);
$stmt->bindValue(':lim', $pg2['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pg2['offset'], PDO::PARAM_INT);
$stmt->execute();
$page2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

assert_eq('page2 size', 2, count($page2));
assert_eq('page2 row0 body', 'second', $page2[0]['body']); // id 11
assert_eq('page2 row1 body', 'first',  $page2[1]['body']); // id 10 (06-14)
assert_eq('no overlap with page1',
    true,
    count(array_intersect(
        array_column($page1, 'body'),
        array_column($page2, 'body')
    )) === 0
);

echo "\nAll assertions passed.\n";
