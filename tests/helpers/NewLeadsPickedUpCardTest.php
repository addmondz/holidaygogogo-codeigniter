<?php
/**
 * Run with: php tests/helpers/NewLeadsPickedUpCardTest.php
 *
 * Pins the "New Lead Picked Up" universe AFTER it was aligned with the
 * "Total New Leads (GHL)" card (Leads By Hour logic): count EVERY lead the
 * owner is the real assignee of that LANDED in the window. NO brand-new /
 * never-contacted-before filter -- re-engaged customers count too. The only
 * thing that keeps this below the company-wide card is the per-owner
 * assignment: a lead nobody has picked up has no owner row to sit in.
 *
 * One rule for the window: glo.lead_started_at BETWEEN :start AND :end
 * (the landing time), NOT the assignment date.
 *
 * Shared by the Lead Reply Activity dashboard column
 * (build_lead_reply_assignment_where_clause) and the sales-agent "New Leads"
 * card (Report_Model::Lead_Reply_Activity_Assigned_New_Leads_For_Uids):
 *
 *   FROM ghl_lead_ownership glo
 *   WHERE glo.is_assigned_owner = 1
 *     AND NULLIF(glo.assigned_to_user_id,'') = glo.owner_user_id   (owner = real assignee)
 *     AND glo.owner_user_id IN (:uids)
 *     AND glo.lead_started_at BETWEEN :start AND :end
 *   count = COUNT(DISTINCT glo.processed_lead_id)   (one lead once across the TC's inboxes)
 *
 * Mirrored in SQLite so the counting rules are pinned independently of MySQL.
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
    lead_started_at     TEXT,
    contact_id          TEXT
)");

// June window = 2026-06-01 .. 2026-06-30. Counted = any lead the owner is the
// real assignee of that landed (lead_started_at) inside the window.
// pl 1  u1 cA: landed 06-19                                          COUNT
// pl 2  u1 cB: landed 06-19, assigned_at NULL                        COUNT
// pl 3  u1 cC: landed 06-19, customer had contacted before (06-05)   COUNT (re-engaged still counts now)
// pl 4  u1 cD: landed 06-19, ASSIGNED 07-05 later                    COUNT (landing drives the window)
// pl 5  u1 cE: is_assigned_owner = 0 (reply owner only)              EXCLUDED
// pl 6  u1 cF: is_assigned_owner=1 BUT assigned_to_user_id=u2        EXCLUDED (owner != assignee)
// pl 7  u2 cG: landed 06-19, different agent                         EXCLUDED for u1
// pl 8  u1 + u1b cH: same lead on two inboxes 06-19                  COUNT ONCE (DISTINCT lead)
// pl 9  u1 cI: landed 05-20 (before window)                          EXCLUDED from June (belongs to May)
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, is_assigned_owner, assigned_to_user_id, assigned_at, lead_started_at, contact_id) VALUES
    ('u1',  1, 1, 'u1',  '2026-06-19 09:15:00', '2026-06-19 09:00:00', 'cA'),
    ('u1',  2, 1, 'u1',  NULL,                  '2026-06-19 09:45:00', 'cB'),
    ('u1',  3, 1, 'u1',  '2026-06-19 15:00:00', '2026-06-19 14:30:00', 'cC'),
    ('u1',  4, 1, 'u1',  '2026-07-05 10:00:00', '2026-06-19 10:00:00', 'cD'),
    ('u1',  5, 0, 'u1',  '2026-06-19 10:00:00', '2026-06-19 10:00:00', 'cE'),
    ('u1',  6, 1, 'u2',  '2026-06-19 11:00:00', '2026-06-19 11:00:00', 'cF'),
    ('u2',  7, 1, 'u2',  '2026-06-19 12:00:00', '2026-06-19 12:00:00', 'cG'),
    ('u1',  8, 1, 'u1',  '2026-06-19 16:00:00', '2026-06-19 15:00:00', 'cH'),
    ('u1b', 8, 1, 'u1b', '2026-06-19 16:00:00', '2026-06-19 15:00:00', 'cH'),
    ('u1',  9, 1, 'u1',  '2026-06-19 18:00:00', '2026-05-20 08:00:00', 'cI')");

// SQLite mirror of the landed-lead universe (no never-contacted-before filter).
function count_new_leads(PDO $pdo, array $uids, $start, $end) {
    $uids = array_values(array_filter(array_map('strval', $uids), 'strlen'));
    if (empty($uids)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql = "
        SELECT COUNT(DISTINCT glo.processed_lead_id) AS assigned_new_leads
        FROM ghl_lead_ownership glo
        WHERE glo.is_assigned_owner = 1
          AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
          AND glo.owner_user_id IN ({$placeholders})
          AND glo.lead_started_at BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($uids, array($start . ' 00:00:00', $end . ' 23:59:59')));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($row['assigned_new_leads']) ? (int) $row['assigned_new_leads'] : 0;
}

echo "[single inbox u1, June window]\n";
// Counts leads 1, 2, 3, 4, 8 -> 5. Lead 3 (re-engaged customer cC) now COUNTS.
// Lead 4 counts even though assigned in July (it landed in June). Lead 9 excluded
// (landed in May). 5 (reply owner), 6 (owner != assignee), 7 (u2) all excluded.
assert_eq('u1 landed leads in June', 5,
    count_new_leads($pdo, array('u1'), '2026-06-01', '2026-06-30'));

echo "[TC with two inboxes u1 + u1b]\n";
// Lead 8 is held by both inboxes; DISTINCT lead id keeps it one -> still 5.
assert_eq('two inboxes do not double-count lead 8', 5,
    count_new_leads($pdo, array('u1', 'u1b'), '2026-06-01', '2026-06-30'));

echo "[narrow to the single landing day 2026-06-19]\n";
// Leads 1,2,3,4,8 all landed on 06-19; lead 9 landed 05-20.
assert_eq('same 5 land on 06-19', 5,
    count_new_leads($pdo, array('u1'), '2026-06-19', '2026-06-19'));

echo "[May window catches lead 9]\n";
// Lead 9 landed 05-20, so it belongs to May, not the June its later ownership
// row's assignment sits in.
assert_eq('lead 9 belongs to May', 1,
    count_new_leads($pdo, array('u1'), '2026-05-01', '2026-05-31'));

echo "[July window: assignment date no longer pulls a lead in]\n";
// Lead 4 was ASSIGNED in July but landed in June -> July counts nothing.
assert_eq('no lead landed in July', 0,
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
