<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityAvgResponseTimeTest.php
 *
 * Pins the new "Avg Response Time" column on the Lead Reply Activity dashboard.
 * It reuses the SAME response-time figure the Lead Dashboard already reports
 * (ghl_processed_leads.avg_first_5_response_seconds) and averages it, per owner,
 * across the DISTINCT leads that owner replied to inside the reply-activity
 * universe (outbound reply, 07:00-22:00 everyday window).
 *
 * The averaging math is a pure helper (null/negative response times ignored, no
 * qualifying lead -> null). The DISTINCT-lead scoping is exercised against a
 * SQLite mirror of the model query so a lead replied to many times contributes
 * its response time ONCE (message rows must not weight the average).
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

// --- Pure averaging helper: mean of the per-lead response seconds. ---
assert_eq('mean of two leads',            200, lead_reply_activity_avg_response_seconds(array(100, 300)));
assert_eq('rounds to nearest second',      67, lead_reply_activity_avg_response_seconds(array(100, 100, 0)));
assert_eq('nulls ignored, not zero',      300, lead_reply_activity_avg_response_seconds(array(null, 300)));
assert_eq('negative gaps ignored',        300, lead_reply_activity_avg_response_seconds(array(-5, 300)));
assert_eq('no qualifying lead -> null',   null, lead_reply_activity_avg_response_seconds(array(null, -1)));
assert_eq('empty -> null',                null, lead_reply_activity_avg_response_seconds(array()));
assert_eq('coerces numeric strings',      150, lead_reply_activity_avg_response_seconds(array('100', '200')));

// --- SQLite mirror of the per-owner avg-response query. ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT, processed_lead_id INTEGER, conversation_id TEXT,
    lead_started_at TEXT, lead_ended_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, conversation_id TEXT, user_id TEXT,
    direction TEXT, date_added TEXT
)");
$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY, avg_first_5_response_seconds INTEGER
)");

// Owner u1:
//   lead 100 (conv a) response 100s, replied 3x in-hours -> counted ONCE (100).
//   lead 101 (conv b) response 300s, replied 1x in-hours  -> 300.  u1 avg = 200.
//   lead 102 (conv d) response NULL  -> excluded (no measured response time).
//   lead 103 (conv e) response 999s, replied only out-of-hours -> excluded.
// Owner u2:
//   lead 200 (conv c) response 50s, one in-hours reply -> u2 avg = 50.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, lead_started_at, lead_ended_at) VALUES
    ('u1', 100, 'a', '2026-06-19 00:00:00', NULL),
    ('u1', 101, 'b', '2026-06-19 00:00:00', NULL),
    ('u1', 102, 'd', '2026-06-19 00:00:00', NULL),
    ('u1', 103, 'e', '2026-06-19 00:00:00', NULL),
    ('u2', 200, 'c', '2026-06-19 00:00:00', NULL)");
$pdo->exec("INSERT INTO ghl_processed_leads (id, avg_first_5_response_seconds) VALUES
    (100, 100), (101, 300), (102, NULL), (103, 999), (200, 50)");
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    (1, 'a', 'u1', 'outbound', '2026-06-19 09:10:00'),
    (2, 'a', 'u1', 'outbound', '2026-06-19 10:10:00'),
    (3, 'a', 'u1', 'outbound', '2026-06-19 11:10:00'),
    (4, 'b', 'u1', 'outbound', '2026-06-19 14:00:00'),
    (5, 'd', 'u1', 'outbound', '2026-06-19 15:00:00'),
    (6, 'e', 'u1', 'outbound', '2026-06-19 23:30:00'),
    (7, 'c', 'u2', 'outbound', '2026-06-19 16:00:00')");

// Mirror of the model query: DISTINCT (owner, lead, response_seconds) collapses
// the message multiplication, then the pure helper averages per owner.
$sql = "
    SELECT DISTINCT
        glo.owner_user_id AS owner_user_id,
        glo.processed_lead_id AS processed_lead_id,
        pl.avg_first_5_response_seconds AS response_seconds
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    INNER JOIN ghl_processed_leads pl ON pl.id = glo.processed_lead_id
    WHERE gm.date_added BETWEEN '2026-06-19 00:00:00' AND '2026-06-19 23:59:59'
      AND time(gm.date_added) BETWEEN '07:00:00' AND '22:00:00'
      AND pl.avg_first_5_response_seconds IS NOT NULL
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$byOwner = array();
foreach ($rows as $r) {
    $byOwner[$r['owner_user_id']][] = $r['response_seconds'];
}

$u1 = isset($byOwner['u1']) ? lead_reply_activity_avg_response_seconds($byOwner['u1']) : null;
$u2 = isset($byOwner['u2']) ? lead_reply_activity_avg_response_seconds($byOwner['u2']) : null;

assert_eq('u1: (100 + 300) / 2, replies-per-lead do not skew', 200, $u1);
assert_eq('u2: single lead response time',                      50, $u2);
assert_eq('out-of-hours-only lead 103 excluded',              false, in_array('999', array_map('strval', isset($byOwner['u1']) ? $byOwner['u1'] : array()), true));

echo "\nAll assertions passed.\n";
