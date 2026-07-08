<?php
/**
 * Run with: php tests/helpers/NewLeadsPickedUpCardTest.php
 *
 * Pins the sales-agent "New Leads" summary card AFTER it was switched to the
 * "New Lead Picked Up" universe so the card matches the Lead Reply Activity
 * dashboard column exactly (Report_Model::Lead_Reply_Activity_Assigned_New_Leads_For_Uids).
 *
 * Old logic counted every ghl_processed_leads row assigned to the agent,
 * windowed by lead_started_at (when the conversation landed). New logic counts
 * PICKED-UP leads from ghl_lead_ownership, windowed by the pick-up time, exactly
 * like the dashboard:
 *
 *   FROM ghl_lead_ownership glo
 *   WHERE glo.is_assigned_owner = 1
 *     AND NULLIF(glo.assigned_to_user_id,'') = glo.owner_user_id   (owner = real assignee)
 *     AND glo.owner_user_id IN (:uids)
 *     AND COALESCE(glo.assigned_at, glo.lead_started_at) BETWEEN :start AND :end
 *   count = COUNT(DISTINCT glo.processed_lead_id)   (one lead once across the TC's inboxes)
 *
 * This mirrors the model SQL in SQLite (NULLIF/COALESCE are native) so the
 * counting rules are locked independently of MySQL.
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
    is_assigned_owner   INTEGER,
    assigned_to_user_id TEXT,
    assigned_at         TEXT,
    lead_started_at     TEXT
)");

// pl 1  u1: picked up 2026-06-19 (assigned_at in window)                 COUNT
// pl 2  u1: assigned_at NULL -> falls back to lead_started_at 06-19      COUNT (COALESCE)
// pl 3  u1: started 06-10 (before window) but PICKED UP 06-19            COUNT (pick-up drives window)
// pl 4  u1: started 06-19 (in window) but PICKED UP 07-05 (after)        EXCLUDED (pick-up out of window)
// pl 5  u1: is_assigned_owner = 0 (reply owner only)                     EXCLUDED
// pl 6  u1: is_assigned_owner=1 BUT assigned_to_user_id=u2 (owner!=assignee) EXCLUDED
// pl 7  u2: picked up 06-19, different agent                             EXCLUDED for u1
// pl 8  u1 + u1b: same lead handled by the TC's two inboxes 06-19        COUNT ONCE (DISTINCT lead)
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, is_assigned_owner, assigned_to_user_id, assigned_at, lead_started_at) VALUES
    ('u1',  1, 1, 'u1',  '2026-06-19 09:15:00', '2026-06-19 09:00:00'),
    ('u1',  2, 1, 'u1',  NULL,                  '2026-06-19 09:45:00'),
    ('u1',  3, 1, 'u1',  '2026-06-19 14:30:00', '2026-06-10 08:00:00'),
    ('u1',  4, 1, 'u1',  '2026-07-05 10:00:00', '2026-06-19 10:00:00'),
    ('u1',  5, 0, 'u1',  '2026-06-19 10:00:00', '2026-06-19 10:00:00'),
    ('u1',  6, 1, 'u2',  '2026-06-19 11:00:00', '2026-06-19 11:00:00'),
    ('u2',  7, 1, 'u2',  '2026-06-19 12:00:00', '2026-06-19 12:00:00'),
    ('u1',  8, 1, 'u1',  '2026-06-19 15:00:00', '2026-06-19 15:00:00'),
    ('u1b', 8, 1, 'u1b', '2026-06-19 16:00:00', '2026-06-19 16:00:00')");

// SQLite mirror of Lead_Reply_Activity_Assigned_New_Leads_For_Uids().
function count_new_leads(PDO $pdo, array $uids, $start, $end) {
    $uids = array_values(array_filter(array_map('strval', $uids), 'strlen'));
    if (empty($uids)) {
        return 0;
    }
    $assign = "COALESCE(glo.assigned_at, glo.lead_started_at)";
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql = "
        SELECT COUNT(DISTINCT glo.processed_lead_id) AS assigned_new_leads
        FROM ghl_lead_ownership glo
        WHERE glo.is_assigned_owner = 1
          AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
          AND glo.owner_user_id IN ({$placeholders})
          AND {$assign} BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($uids, array($start . ' 00:00:00', $end . ' 23:59:59')));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($row['assigned_new_leads']) ? (int) $row['assigned_new_leads'] : 0;
}

echo "[single inbox u1, June window]\n";
// Counts leads 1, 2, 3, 8 -> 4. Excludes 4 (picked up in July), 5 (reply owner),
// 6 (owner != assignee), 7 (other agent).
assert_eq('u1 picked-up leads in June', 4,
    count_new_leads($pdo, array('u1'), '2026-06-01', '2026-06-30'));

echo "[TC with two inboxes u1 + u1b]\n";
// Lead 8 is held by both inboxes; DISTINCT lead id keeps it one -> still 4.
assert_eq('two inboxes do not double-count lead 8', 4,
    count_new_leads($pdo, array('u1', 'u1b'), '2026-06-01', '2026-06-30'));

echo "[narrow to the single pick-up day 2026-06-19]\n";
assert_eq('same 4 land on 06-19 pick-up day', 4,
    count_new_leads($pdo, array('u1'), '2026-06-19', '2026-06-19'));

echo "[July window catches the late-assigned lead 4]\n";
assert_eq('lead 4 picked up in July', 1,
    count_new_leads($pdo, array('u1'), '2026-07-01', '2026-07-31'));

echo "[other agent u2]\n";
assert_eq('u2 sees only its own lead 7', 1,
    count_new_leads($pdo, array('u2'), '2026-06-01', '2026-06-30'));

echo "[no linked GHL uid -> zero]\n";
assert_eq('empty uid set', 0,
    count_new_leads($pdo, array(), '2026-06-01', '2026-06-30'));
assert_eq('blank uid filtered out', 0,
    count_new_leads($pdo, array('', ' '), '2026-06-01', '2026-06-30'));

echo "\nALL PASSED\n";
