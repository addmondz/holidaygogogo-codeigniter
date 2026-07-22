<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityTransferOutTest.php
 *
 * Pins the "Transfer Out" universe on the Lead Reply Activity dashboard.
 *
 * "Transfer Out" must mean a lead that ACTUALLY left the owner's queue -- it is
 * no longer assigned to that owner (reassigned to a different agent/inbox). A
 * lead the owner is still assigned to is NEVER a transfer out, no matter which
 * day it first arrived or how many times the owner has replied.
 *
 * The old rule excluded a lead from Transfer Out only when it was BOTH assigned
 * to the owner AND started inside the viewed window:
 *     AND NOT (assigned_to_user_id = owner_user_id AND pickup_at BETWEEN s AND e)
 * So a lead still assigned to the owner but STARTED ON AN EARLIER DAY (whose
 * first reply merely lands in the window) was wrongly counted as transferred out.
 * That is the Natasha bug: leads still under Natasha showing as Transfer Out.
 *
 * The fixed rule ignores the start date and keys purely off the live assignment:
 *     AND NULLIF(assigned_to_user_id, '') <> owner_user_id
 *
 * This test mirrors both rules in SQLite over the same fixtures and asserts the
 * old rule mislabels (red) while the fixed rule is correct (green).
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
    owner_user_id       TEXT,
    processed_lead_id   INTEGER,
    conversation_id     TEXT,
    contact_id          TEXT,
    is_reply_owner      INTEGER,
    assigned_to_user_id TEXT,
    lead_started_at     TEXT,
    lead_ended_at       TEXT
)");

$pdo->exec("CREATE TABLE ghl_messages (
    conversation_id TEXT,
    user_id         TEXT,
    direction       TEXT,
    date_added      TEXT
)");

// Owner u1 = "Natasha TC Team". Viewing window = 2026-07-09.
// Reply-created rule: a lead counts on the owner's FIRST outbound reply.
//
//  lead 1 STILL-UNDER-NATASHA, started YESTERDAY (the actual bug):
//     owner=u1, assigned=u1, started 2026-07-08, first reply on 07-09
//     -> NOT a transfer out (never left her queue).
//  lead 2 REASSIGNED to agent u2, first reply on 07-09:
//     owner=u1, assigned=u2
//     -> IS a transfer out (moved to a different agent).
//  lead 3 OWN FRESH lead started in-window, first reply on 07-09:
//     owner=u1, assigned=u1, started 2026-07-09
//     -> NOT a transfer out.
//  lead 4 REASSIGNED but first reply on 07-08 (OUTSIDE the window):
//     owner=u1, assigned=u2
//     -> excluded: its reply-created day is not in the window.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, contact_id, is_reply_owner, assigned_to_user_id, lead_started_at, lead_ended_at) VALUES
    ('u1', 1, 'cv1', 'c1', 1, 'u1', '2026-07-08 20:19:38', NULL),
    ('u1', 2, 'cv2', 'c2', 1, 'u2', '2026-07-08 21:00:00', NULL),
    ('u1', 3, 'cv3', 'c3', 1, 'u1', '2026-07-09 09:00:00', NULL),
    ('u1', 4, 'cv4', 'c4', 1, 'u2', '2026-07-08 08:00:00', NULL)");

// Owner outbound replies. Each lead's FIRST reply is what dates it into a window.
// Leads 1/2/3 first reply on 07-09 (in window); lead 4 first reply on 07-08 (out).
$pdo->exec("INSERT INTO ghl_messages (conversation_id, user_id, direction, date_added) VALUES
    ('cv1','u1','outbound','2026-07-09 09:10:00'),
    ('cv2','u1','outbound','2026-07-09 09:20:00'),
    ('cv3','u1','outbound','2026-07-09 09:01:00'),
    ('cv4','u1','outbound','2026-07-08 08:10:00'),
    ('cv4','u1','outbound','2026-07-08 08:20:00')");

$start = '2026-07-09 00:00:00';
$end   = '2026-07-09 23:59:59';

// Mirror of Lead_Reply_Activity_Reply_Created_Base_Rows: reply-created leads
// (owner's FIRST outbound reply lands in the window), with a swappable exclusion.
// reply_created_at = the earliest owner outbound reply in the lead's window.
$transferOutSql = function ($exclusion) use ($start, $end) {
    return "
        SELECT reply_created.processed_lead_id
        FROM (
            SELECT
                glo.owner_user_id,
                glo.processed_lead_id,
                glo.assigned_to_user_id,
                glo.lead_started_at AS pickup_at,
                (SELECT gm4.date_added FROM ghl_messages gm4
                   WHERE gm4.conversation_id = glo.conversation_id
                     AND gm4.user_id = glo.owner_user_id
                     AND gm4.direction = 'outbound'
                     AND gm4.date_added >= glo.lead_started_at
                     AND (glo.lead_ended_at IS NULL OR gm4.date_added < glo.lead_ended_at)
                   ORDER BY gm4.date_added ASC LIMIT 1 OFFSET 0) AS reply_created_at
            FROM ghl_lead_ownership glo
            INNER JOIN ghl_messages gm
                ON gm.conversation_id = glo.conversation_id
               AND gm.user_id = glo.owner_user_id
               AND gm.direction = 'outbound'
               AND gm.date_added >= glo.lead_started_at
               AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
            WHERE glo.is_reply_owner = 1
              AND glo.owner_user_id = 'u1'
            GROUP BY glo.owner_user_id, glo.processed_lead_id, glo.assigned_to_user_id, pickup_at
            HAVING COUNT(*) > 0
        ) reply_created
        WHERE reply_created.reply_created_at BETWEEN '{$start}' AND '{$end}'
          {$exclusion}
        ORDER BY reply_created.processed_lead_id
    ";
};

$idsFor = function ($exclusion) use ($pdo, $transferOutSql) {
    $rows = $pdo->query($transferOutSql($exclusion))->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $rows);
};

// ---------- Red: the OLD rule mislabels the still-under-Natasha lead ----------
$oldExclusion = "AND NOT (NULLIF(reply_created.assigned_to_user_id, '') = reply_created.owner_user_id
                          AND reply_created.pickup_at BETWEEN '{$start}' AND '{$end}')";
$oldIds = $idsFor($oldExclusion);
assert_eq('OLD rule wrongly includes still-owned lead 1', true, in_array(1, $oldIds, true));

// ---------- Green: the FIXED rule keys only off the live assignment ----------
$newExclusion = "AND NULLIF(reply_created.assigned_to_user_id, '') <> reply_created.owner_user_id";
$newIds = $idsFor($newExclusion);

// lead 1 (still assigned to u1, started yesterday)  -> NOT transfer out
assert_eq('lead 1 still under owner -> not transfer out', false, in_array(1, $newIds, true));
// lead 2 (reassigned to u2)                          -> IS transfer out
assert_eq('lead 2 reassigned -> transfer out', true, in_array(2, $newIds, true));
// lead 3 (own fresh lead, started in-window)         -> NOT transfer out
assert_eq('lead 3 own fresh lead -> not transfer out', false, in_array(3, $newIds, true));
// lead 4 (reassigned, but first reply outside the window) -> not in this window
assert_eq('lead 4 out-of-window reply -> absent', false, in_array(4, $newIds, true));

assert_eq('fixed transfer-out set is exactly {2}', array(2), $newIds);

echo "\nAll LeadReplyActivityTransferOut assertions passed.\n";
