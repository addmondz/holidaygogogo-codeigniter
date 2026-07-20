<?php
/**
 * Run with: php tests/helpers/LeadReplyHourlyResponderAttributionTest.php
 *
 * Locks the SQL contract behind Report_Model::Lead_Reply_Activity_Hourly_By_Agent()
 * (the "Lead Reply Hourly — All Agents" matrix). That query runs on MySQL; here we
 * mirror its intent in portable SQLite so the rule is pinned without a live DB.
 *
 * The bug this guards against: ghl_lead_ownership stores ONE row per agent who ever
 * replied to a conversation, all flagged is_reply_owner = 1. The old query counted
 * every customer (inbound) message once PER co-owner, so an agent who merely shares
 * ownership of a thread another agent is actually working got credited with that
 * thread's whole inbound stream (Simon Lee's morning "In" was inflated by messages
 * Chen/Nur/Natasha answered).
 *
 * The contract: an inbound message is attributed to exactly ONE agent -- the
 * RESPONDER, i.e. whoever sent the next outbound reply in that conversation. Outbound
 * stays attributed to its own sender. Inbound nobody answered is counted for no one.
 * Bot-bounce lead windows are excluded.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

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

$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");
$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES ('u-simon', 'Simon Lee'), ('u-chen', 'Chen OP Team')");

$pdo->exec("CREATE TABLE ghl_lead_ownership (
    conversation_id TEXT, owner_user_id TEXT, is_reply_owner INTEGER,
    is_bot_bounce INTEGER, lead_started_at TEXT, lead_ended_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, conversation_id TEXT, user_id TEXT,
    direction TEXT, date_added TEXT
)");

// cv-1: a live, non-bounce lead co-owned by BOTH Simon and Chen (both replied to it
// at some point, so both carry a reply-owner row -- exactly the shape that inflated
// the old count).
$pdo->exec("INSERT INTO ghl_lead_ownership VALUES
    ('cv-1', 'u-simon', 1, 0, '2026-07-17 00:00:00', NULL),
    ('cv-1', 'u-chen',  1, 0, '2026-07-17 00:00:00', NULL)
");
// cv-2: a bot-bounce window owned by Simon -- its traffic must be excluded entirely.
$pdo->exec("INSERT INTO ghl_lead_ownership VALUES
    ('cv-2', 'u-simon', 1, 1, '2026-07-17 00:00:00', NULL)
");

$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    -- 09:00 customer msg; next reply (09:05) is CHEN's -> inbound belongs to Chen, not Simon
    (1, 'cv-1', '',       'inbound',  '2026-07-17 09:00:00'),
    (2, 'cv-1', 'u-chen', 'outbound', '2026-07-17 09:05:00'),
    -- 14:00 customer msg; next reply (14:05) is SIMON's -> inbound belongs to Simon
    (3, 'cv-1', '',       'inbound',  '2026-07-17 14:00:00'),
    (4, 'cv-1', 'u-simon','outbound', '2026-07-17 14:05:00'),
    -- 16:00 customer msg with no later reply -> unanswered, belongs to no one
    (5, 'cv-1', '',       'inbound',  '2026-07-17 16:00:00'),
    -- bot-bounce window traffic -> excluded
    (6, 'cv-2', '',       'inbound',  '2026-07-17 09:00:00'),
    (7, 'cv-2', 'u-simon','outbound', '2026-07-17 09:05:00')
");

// SQLite mirror of the fixed Lead_Reply_Activity_Hourly_By_Agent SQL. HOUR() ->
// strftime('%H'); the responder is the earliest later outbound (by time, id tiebreak).
$sql = "
    SELECT glo.owner_user_id AS owner_user_id,
           CAST(strftime('%H', gm.date_added) AS INTEGER) AS hour_of_day,
           COUNT(DISTINCT CASE WHEN gm.direction = 'inbound'
                AND glo.owner_user_id = (
                    SELECT o.user_id FROM ghl_messages o
                    WHERE o.conversation_id = gm.conversation_id
                      AND o.direction = 'outbound' AND o.user_id IS NOT NULL AND o.user_id <> ''
                      AND (o.date_added > gm.date_added
                           OR (o.date_added = gm.date_added AND o.id > gm.id))
                    ORDER BY o.date_added ASC, o.id ASC LIMIT 1)
                THEN gm.id END) AS inbound_count,
           COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id
                THEN gm.id END) AS outbound_count
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
      ON gm.conversation_id = glo.conversation_id
     AND gm.date_added >= glo.lead_started_at
     AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE glo.is_reply_owner = 1 AND glo.is_bot_bounce = 0
      AND gm.date_added BETWEEN '2026-07-17 00:00:00' AND '2026-07-17 23:59:59'
      AND gm.direction IN ('inbound','outbound')
    GROUP BY glo.owner_user_id, hour_of_day
    ORDER BY owner_user_id, hour_of_day
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Index rows by "owner@hour" for assertions.
$cell = array();
foreach ($rows as $r) {
    $cell[$r['owner_user_id'] . '@' . $r['hour_of_day']] = array(
        'in' => (int) $r['inbound_count'], 'out' => (int) $r['outbound_count']
    );
}
$in  = function($k) use ($cell) { return isset($cell[$k]) ? $cell[$k]['in']  : 0; };
$out = function($k) use ($cell) { return isset($cell[$k]) ? $cell[$k]['out'] : 0; };

// The 09:00 inbound is Chen's (she answered it) -- Simon is NOT credited.
assert_eq('Simon inbound @9 (Chen answered it)', 0, $in('u-simon@9'));
assert_eq('Chen inbound @9 (she answered it)',   1, $in('u-chen@9'));

// The 14:00 inbound is Simon's (he answered it).
assert_eq('Simon inbound @14 (he answered it)', 1, $in('u-simon@14'));
assert_eq('Chen inbound @14 (not hers)',        0, $in('u-chen@14'));

// Outbound stays attributed to its own sender.
assert_eq('Chen outbound @9',   1, $out('u-chen@9'));
assert_eq('Simon outbound @14', 1, $out('u-simon@14'));
assert_eq('Simon outbound @9 (none)', 0, $out('u-simon@9'));

// The 16:00 inbound was never answered -> credited to no one.
assert_eq('unanswered inbound @16 not credited to Simon', 0, $in('u-simon@16'));
assert_eq('unanswered inbound @16 not credited to Chen',  0, $in('u-chen@16'));

// Bot-bounce window is excluded entirely.
$simonTotalIn = $in('u-simon@9') + $in('u-simon@14') + $in('u-simon@16');
assert_eq('Simon total inbound = only the one he answered', 1, $simonTotalIn);

echo "\nAll assertions passed.\n";
