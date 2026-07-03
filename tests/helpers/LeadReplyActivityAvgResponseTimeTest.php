<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityAvgResponseTimeTest.php
 *
 * Pins the "Avg Response Time" column on the Lead Reply Activity dashboard AFTER
 * it was unified with the Lead Reply Hourly page: BOTH now count EVERY
 * inbound->outbound reply pair with identical logic
 * (ghl_message_log_average_reply_seconds_by_group / ...seconds). The dashboard no
 * longer reads the precomputed ghl_processed_leads.avg_first_5_response_seconds
 * column and no longer collapses a lead to a single per-lead figure.
 *
 * Every consecutive inbound->owner-outbound pair inside a thread contributes its
 * in-hours gap; out-of-hours / cross-day / cross-conversation pairs never count;
 * the per-owner mean is SUM(gap) / COUNT(pairs) -- so a lead replied to many times
 * now weights the average by each reply, exactly like the Hourly card.
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

// --- SQLite mirror of the per-owner avg-response query (same universe as the
//     Hourly page, minus the single-owner filter, grouped per owner). ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT, conversation_id TEXT,
    lead_started_at TEXT, lead_ended_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, conversation_id TEXT, user_id TEXT,
    direction TEXT, date_added TEXT
)");

// Owner u1:
//   conv a: two in-hours replies (40s, 20s) -- BOTH count now (per-pair).
//   conv b: one in-hours reply (120s).
//   conv e: an out-of-hours pair (23:30) -- excluded.
//   u1 avg = (40 + 20 + 120) / 3 = 60.
// Owner u2:
//   conv c: one in-hours reply (50s). u2 avg = 50.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, conversation_id, lead_started_at, lead_ended_at) VALUES
    ('u1', 'a', '2026-06-19 00:00:00', NULL),
    ('u1', 'b', '2026-06-19 00:00:00', NULL),
    ('u1', 'e', '2026-06-19 00:00:00', NULL),
    ('u2', 'c', '2026-06-19 00:00:00', NULL)");
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    (1,  'a', 'cust', 'inbound',  '2026-06-19 09:00:00'),
    (2,  'a', 'u1',   'outbound', '2026-06-19 09:00:40'),
    (3,  'a', 'cust', 'inbound',  '2026-06-19 10:00:00'),
    (4,  'a', 'u1',   'outbound', '2026-06-19 10:00:20'),
    (5,  'b', 'cust', 'inbound',  '2026-06-19 14:00:00'),
    (6,  'b', 'u1',   'outbound', '2026-06-19 14:02:00'),
    (7,  'e', 'cust', 'inbound',  '2026-06-19 23:30:00'),
    (8,  'e', 'u1',   'outbound', '2026-06-19 23:30:30'),
    (9,  'c', 'cust', 'inbound',  '2026-06-19 16:00:00'),
    (10, 'c', 'u2',   'outbound', '2026-06-19 16:00:50')");

// Mirror of the model query: raw inbound + owner-outbound messages, ordered by
// owner then conversation then time, fed to the SAME grouping helper the Hourly
// page's per-agent variant uses.
$sql = "
    SELECT
        glo.owner_user_id AS owner_user_id,
        gm.conversation_id AS conversation_id,
        gm.direction AS direction,
        gm.date_added AS ts
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE gm.date_added BETWEEN '2026-06-19 00:00:00' AND '2026-06-19 23:59:59'
      AND (
            gm.direction = 'inbound'
            OR (gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id)
      )
    ORDER BY glo.owner_user_id ASC, gm.conversation_id ASC, gm.date_added ASC, gm.id ASC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$byOwner = ghl_message_log_average_reply_seconds_by_group($rows, 'owner_user_id', 'ts');

$u1 = isset($byOwner['u1']['avg_seconds']) ? $byOwner['u1']['avg_seconds'] : null;
$u2 = isset($byOwner['u2']['avg_seconds']) ? $byOwner['u2']['avg_seconds'] : null;

assert_eq('u1: (40 + 20 + 120) / 3, every reply pair counts', 60.0, $u1);
assert_eq('u2: single reply pair',                            50.0, $u2);
assert_eq('u1 lead_count = distinct convs with a pair (a, b)',   2, $byOwner['u1']['lead_count']);
assert_eq('out-of-hours conv e pair excluded (u1 unaffected)', 60.0, $u1);

echo "\nAll assertions passed.\n";
