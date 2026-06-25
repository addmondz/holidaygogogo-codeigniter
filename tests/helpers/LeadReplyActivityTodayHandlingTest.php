<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityTodayHandlingTest.php
 *
 * Locks the reworked Lead Reply Activity dashboard columns:
 *   - "Lead Responded"     = DISTINCT leads an owner replied to whose qualifying
 *                            reply lands on a weekday (Mon-Fri) within 09:00-19:00.
 *                            Many replies to the same lead still count as ONE.
 *   - "Transfer Out Lead"  = the existing reply-created count (relabel only).
 *   - "Today Handling Lead"= Lead Responded - Transfer Out Lead, never negative.
 *
 * The today-handling arithmetic is a pure helper. The distinct, business-hours
 * gated "Lead Responded" count is exercised against a SQLite mirror of the model
 * query so the intended COUNT(DISTINCT ...) + weekday/time gate is pinned down.
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

// --- Today Handling = Responded - Transfer Out, clamped at zero. ---
assert_eq('handling is responded minus transfer out', 7, lead_reply_activity_today_handling(10, 3));
assert_eq('handling zero when all transferred out',    0, lead_reply_activity_today_handling(4, 4));
assert_eq('handling never negative',                   0, lead_reply_activity_today_handling(2, 5));
assert_eq('handling coerces string inputs',            5, lead_reply_activity_today_handling('5', '0'));

// --- SQLite mirror of the "Lead Responded" distinct, business-hours query. ---
// 2026-06-19 = Friday, 2026-06-20 = Saturday, 2026-06-21 = Sunday.
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

// Owner u1: lead 100 (conv a) replied to 4 times in-hours -> counts ONCE.
//           lead 101 (conv b) replied to only out-of-hours -> NOT counted.
// Owner u2: lead 200 (conv c) one in-hours reply -> counts once.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, lead_started_at, lead_ended_at) VALUES
    ('u1', 100, 'a', '2026-06-19 00:00:00', NULL),
    ('u1', 101, 'b', '2026-06-19 00:00:00', NULL),
    ('u2', 200, 'c', '2026-06-19 00:00:00', NULL)");
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    (1, 'a', 'u1', 'outbound', '2026-06-19 09:10:00'),
    (2, 'a', 'u1', 'outbound', '2026-06-19 10:10:00'),
    (3, 'a', 'u1', 'outbound', '2026-06-19 11:10:00'),
    (4, 'a', 'u1', 'outbound', '2026-06-19 12:10:00'),
    (5, 'b', 'u1', 'outbound', '2026-06-19 21:30:00'),
    (6, 'b', 'u1', 'outbound', '2026-06-20 10:00:00'),
    (7, 'c', 'u2', 'outbound', '2026-06-19 14:00:00'),
    (8, 'a', 'u9', 'inbound',  '2026-06-19 09:05:00')");

// Portable mirror of the model's MySQL gate: weekday (strftime %w 1..5) and
// 09:00:00 <= TIME <= 19:00:00, counting DISTINCT processed_lead_id per owner.
$sql = "
    SELECT glo.owner_user_id AS owner_user_id,
           COUNT(DISTINCT glo.processed_lead_id) AS lead_responded
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE gm.date_added BETWEEN '2026-06-19 00:00:00' AND '2026-06-19 23:59:59'
      AND CAST(strftime('%w', gm.date_added) AS INTEGER) BETWEEN 1 AND 5
      AND time(gm.date_added) BETWEEN '09:00:00' AND '19:00:00'
    GROUP BY glo.owner_user_id
    ORDER BY glo.owner_user_id
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$byOwner = array();
foreach ($rows as $r) { $byOwner[$r['owner_user_id']] = (int) $r['lead_responded']; }

assert_eq('u1: four in-hours replies to one lead count once', 1, isset($byOwner['u1']) ? $byOwner['u1'] : 0);
assert_eq('u2: single in-hours reply counts once',            1, isset($byOwner['u2']) ? $byOwner['u2'] : 0);
assert_eq('out-of-hours-only lead (101) never appears',       false, in_array(2, $byOwner, true));

// End-to-end: u1 responded 1, transferred out 0 -> still handling 1 today.
assert_eq('u1 today handling', 1, lead_reply_activity_today_handling($byOwner['u1'], 0));

echo "\nAll assertions passed.\n";
