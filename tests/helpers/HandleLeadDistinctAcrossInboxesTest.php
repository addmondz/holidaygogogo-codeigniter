<?php
/**
 * Run with: php tests/helpers/HandleLeadDistinctAcrossInboxesTest.php
 *
 * Pins down the "Daily Handle Lead Count" card fix: when one agent (admin) is
 * linked to MORE THAN ONE GHL inbox, the card must count DISTINCT leads across
 * all of them in ONE query, NOT sum each inbox's own count. Summing double
 * counts any lead handled by two of the agent's inboxes (e.g. a transfer
 * between her own team inboxes), inflating the figure.
 *
 * The tooltip promises "distinct leads you replied to ... counts that lead once".
 * This test mirrors the model query against SQLite so the COUNT(DISTINCT ...)
 * across the uid set (no GROUP BY) is locked in, and shows it diverges from the
 * old per-inbox SUM exactly when an inbox-to-inbox overlap exists.
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
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT, processed_lead_id INTEGER, conversation_id TEXT,
    is_reply_owner INTEGER, lead_started_at TEXT, lead_ended_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, conversation_id TEXT, user_id TEXT,
    direction TEXT, date_added TEXT
)");

// Agent "Natasha" is linked to TWO inboxes: u_main and u_alt.
//   lead 100 (conv a): started under u_main, transferred to u_alt -> BOTH own it.
//                      replied to in-hours under each inbox -> ONE distinct lead.
//   lead 101 (conv b): only u_main, in-hours -> counts once.
//   lead 102 (conv c): only u_alt,  in-hours -> counts once.
//   lead 200 (conv z): belongs to an UNRELATED inbox u_other -> must NOT count.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, is_reply_owner, lead_started_at, lead_ended_at) VALUES
    ('u_main', 100, 'a', 1, '2026-06-22 00:00:00', NULL),
    ('u_alt',  100, 'a', 1, '2026-06-22 00:00:00', NULL),
    ('u_main', 101, 'b', 1, '2026-06-22 00:00:00', NULL),
    ('u_alt',  102, 'c', 1, '2026-06-22 00:00:00', NULL),
    ('u_other',200, 'z', 1, '2026-06-22 00:00:00', NULL)");
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    (1, 'a', 'u_main', 'outbound', '2026-06-22 09:30:00'),
    (2, 'a', 'u_alt',  'outbound', '2026-06-22 14:30:00'),
    (3, 'b', 'u_main', 'outbound', '2026-06-22 10:00:00'),
    (4, 'c', 'u_alt',  'outbound', '2026-06-22 11:00:00'),
    (5, 'z', 'u_other','outbound', '2026-06-22 12:00:00')");

$uids = array('u_main', 'u_alt');
$place = implode(',', array_fill(0, count($uids), '?'));
$businessHours = "time(gm.date_added) BETWEEN '07:00:00' AND '22:00:00'";

// OLD behaviour (per-inbox COUNT(DISTINCT) then SUM in PHP) -> double counts lead 100.
$oldSql = "
    SELECT glo.owner_user_id, COUNT(DISTINCT glo.processed_lead_id) AS c
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE glo.is_reply_owner = 1
      AND glo.owner_user_id IN ({$place})
      AND gm.date_added BETWEEN '2026-06-22 00:00:00' AND '2026-06-22 23:59:59'
      AND {$businessHours}
    GROUP BY glo.owner_user_id";
$stmt = $pdo->prepare($oldSql);
$stmt->execute($uids);
$oldTotal = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $oldTotal += (int) $r['c']; }

// NEW behaviour (single COUNT(DISTINCT) across the uid set) -> lead 100 once.
$newSql = "
    SELECT COUNT(DISTINCT glo.processed_lead_id) AS c
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE glo.is_reply_owner = 1
      AND glo.owner_user_id IN ({$place})
      AND gm.date_added BETWEEN '2026-06-22 00:00:00' AND '2026-06-22 23:59:59'
      AND {$businessHours}";
$stmt = $pdo->prepare($newSql);
$stmt->execute($uids);
$newTotal = (int) $stmt->fetch(PDO::FETCH_ASSOC)['c'];

assert_eq('old per-inbox SUM double counts the transferred lead', 4, $oldTotal);
assert_eq('new DISTINCT across inboxes counts each lead once',    3, $newTotal);

echo "\nAll assertions passed.\n";
