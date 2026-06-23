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
    conversation_id TEXT,
    from_number TEXT,
    to_number TEXT,
    user_id TEXT,
    body TEXT,
    date_added TEXT
)");

// Agent directory: outbound messages carry a user_id resolving to a name;
// inbound messages have no user_id and must resolve to a blank agent.
$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");
$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES ('u-1', 'Agent Alice')");

// Conversation directory: resolves a chatroom to its contact (lead).
$pdo->exec("CREATE TABLE ghl_conversations (conversation_id TEXT, contact_id TEXT, contact_name TEXT, full_name TEXT)");
$pdo->exec("INSERT INTO ghl_conversations (conversation_id, contact_id, contact_name, full_name) VALUES
    ('cv-a', 'ct-1', 'Lead One', ''),
    ('cv-b', 'ct-2', '', 'Lead Two')
");

// 5 rows over 2026-06-14..16; one (id 99) is OUTSIDE the test window (06-13).
// Two chatrooms interleave in time: cv-a (ids 10,12,13) and cv-b (id 11).
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, from_number, to_number, user_id, body, date_added) VALUES
    (10, 'cv-a', '+60123', '+60999', 'u-1', 'first',  '2026-06-14 08:00:00'),
    (11, 'cv-b', '+60124', '+60999', NULL,  'second', '2026-06-15 09:00:00'),
    (12, 'cv-a', '+60999', '+60125', 'u-1', 'third',  '2026-06-15 09:00:00'),
    (13, 'cv-a', '+60126', '+60999', NULL,  'fourth', '2026-06-16 23:59:59'),
    (99, 'cv-a', '+60127', '+60999', 'u-1', 'before', '2026-06-13 10:00:00')
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

// Page mirror: newest first, stable tiebreak, windowed, agent resolved.
$pageSql = "SELECT gm.date_added, gm.from_number, gm.to_number, gu.Name AS agent, gm.body
            FROM ghl_messages gm
            LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
            WHERE gm.date_added >= :s AND gm.date_added <= :e
            ORDER BY gm.date_added DESC, gm.id DESC
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
// The five display fields are present, including the resolved agent.
assert_eq('row exposes 5 fields',
    array('date_added', 'from_number', 'to_number', 'agent', 'body'),
    array_keys($page1[0]));
// Agent resolves: id 13 (inbound, no user_id) -> null; id 12 (u-1) -> Agent Alice.
assert_eq('inbound row has no agent', null,          $page1[0]['agent']);
assert_eq('outbound row resolves agent', 'Agent Alice', $page1[1]['agent']);

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

// --- Contact-number filter: returns the full two-way thread for a lead. ---
// '+60999' is the lead here: it appears as to_number on inbound rows (10,11,13)
// and as from_number on the outbound row (12). Filtering by it must return all
// four in-window rows -- both directions of the conversation.
$normExpr = function ($col) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
};

$contactDigits = ghl_message_log_normalize_contact('+60 999'); // formatting stripped
assert_eq('contact normalized to digits', '60999', $contactDigits);

$contactSql = "SELECT gm.id, gm.body
               FROM ghl_messages gm
               WHERE gm.date_added >= :s AND gm.date_added <= :e
                 AND ({$normExpr('gm.from_number')} LIKE :c OR {$normExpr('gm.to_number')} LIKE :c)
               ORDER BY gm.date_added DESC, gm.id DESC";
$cstmt = $pdo->prepare($contactSql);
$cstmt->execute(array(':s' => $start, ':e' => $end, ':c' => '%' . $contactDigits . '%'));
$threaded = $cstmt->fetchAll(PDO::FETCH_ASSOC);

assert_eq('contact filter returns both directions (4 rows)', 4, count($threaded));
assert_eq('contact thread ids (inbound + outbound)',
    array(13, 12, 11, 10),
    array_map('intval', array_column($threaded, 'id')));

// A different contact only matches its own thread (id 12 outbound to +60125).
$cstmt->execute(array(':s' => $start, ':e' => $end, ':c' => '%' . ghl_message_log_normalize_contact('60125') . '%'));
$other = $cstmt->fetchAll(PDO::FETCH_ASSOC);
assert_eq('narrow contact matches single row', 1, count($other));
assert_eq('narrow contact matches expected row', 12, (int) $other[0]['id']);

// --- Grouped export ordering: messages clustered by chatroom (contact/lead). ---
// The CSV export is meant to be analysed one chatroom at a time, so rows are
// grouped by the contact (then conversation) and read oldest-first within a
// thread -- NOT the newest-first flat order the on-screen table uses. Here the
// two chatrooms interleave in time (cv-b's id 11 sits between cv-a's ids 10 and
// 12) so a time-only sort would scatter them; the grouped sort must keep each
// thread contiguous.
$exportSql = "SELECT gm.id, gm.body,
                     COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), '') AS contact_name
              FROM ghl_messages gm
              LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
              LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
              WHERE gm.date_added >= :s AND gm.date_added <= :e
              ORDER BY COALESCE(gc.contact_id, gm.conversation_id, '') ASC,
                       gm.conversation_id ASC,
                       gm.date_added ASC,
                       gm.id ASC";
$estmt = $pdo->prepare($exportSql);
$estmt->execute(array(':s' => $start, ':e' => $end));
$export = $estmt->fetchAll(PDO::FETCH_ASSOC);

// cv-a (ct-1) first, oldest-first within it (10,12,13), then cv-b (ct-2): id 11.
assert_eq('export groups by chatroom, oldest-first within',
    array(10, 12, 13, 11),
    array_map('intval', array_column($export, 'id')));
// Contact label resolves from contact_name, falling back to full_name.
assert_eq('contact label on first chatroom', 'Lead One', $export[0]['contact_name']);
assert_eq('contact label falls back to full_name', 'Lead Two', $export[3]['contact_name']);

echo "\nAll assertions passed.\n";
