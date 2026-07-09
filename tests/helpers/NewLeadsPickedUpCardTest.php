<?php
/**
 * Run with: php tests/helpers/NewLeadsPickedUpCardTest.php
 *
 * Pins the "New Lead Picked Up" universe AFTER it was switched to mean a
 * BRAND-NEW customer: the lead's customer must NEVER have contacted us before,
 * and that first-ever contact must fall inside the window. Two rules combine:
 *
 *   1. first-contact window: glo.lead_started_at BETWEEN :start AND :end
 *   2. never-contacted-before: the contact has NO inbound message earlier than
 *      lead_started_at (a returning customer who re-engages opens a fresh lead
 *      cycle, but their older inbound disqualifies it).
 *
 * Shared by the Lead Reply Activity dashboard column
 * (build_lead_reply_assignment_where_clause) and the sales-agent "New Leads"
 * card (Report_Model::Lead_Reply_Activity_Assigned_New_Leads_For_Uids):
 *
 *   FROM ghl_lead_ownership glo
 *   WHERE glo.is_assigned_owner = 1
 *     AND NULLIF(glo.assigned_to_user_id,'') = glo.owner_user_id   (owner = real assignee)
 *     AND NOT EXISTS (inbound msg for glo.contact_id before glo.lead_started_at)
 *     AND glo.owner_user_id IN (:uids)
 *     AND glo.lead_started_at BETWEEN :start AND :end
 *   count = COUNT(DISTINCT glo.processed_lead_id)   (one lead once across the TC's inboxes)
 *
 * Mirrored in SQLite (NULLIF/NOT EXISTS are native) so the counting rules are
 * pinned independently of MySQL.
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

$pdo->exec("CREATE TABLE ghl_messages (
    contact_id  TEXT,
    direction   TEXT,
    date_added  TEXT
)");

// June window = 2026-06-01 .. 2026-06-30. Counted = brand-new customer whose
// FIRST-EVER contact lands in the window.
// pl 1  u1 cA: landed 06-19, no earlier inbound                      COUNT (brand-new)
// pl 2  u1 cB: landed 06-19, assigned_at NULL, no earlier inbound    COUNT (brand-new)
// pl 3  u1 cC: landed 06-19 BUT cC first messaged 06-05 (re-engaged) EXCLUDED (contacted before)
// pl 4  u1 cD: landed 06-19, ASSIGNED 07-05 later, no earlier inbound COUNT (first-contact drives the window)
// pl 5  u1 cE: is_assigned_owner = 0 (reply owner only)              EXCLUDED
// pl 6  u1 cF: is_assigned_owner=1 BUT assigned_to_user_id=u2        EXCLUDED (owner != assignee)
// pl 7  u2 cG: landed 06-19, different agent                         EXCLUDED for u1
// pl 8  u1 + u1b cH: same brand-new lead on two inboxes 06-19        COUNT ONCE (DISTINCT lead)
// pl 9  u1 cI: brand-new but landed 05-20 (before window)            EXCLUDED from June (belongs to May)
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

// Inbound history. Each contact's FIRST inbound == its lead_started_at, EXCEPT
// cC which messaged us on 06-05 (before its 06-19 lead) -> a returning customer.
$pdo->exec("INSERT INTO ghl_messages (contact_id, direction, date_added) VALUES
    ('cA', 'inbound', '2026-06-19 09:00:00'),
    ('cB', 'inbound', '2026-06-19 09:45:00'),
    ('cC', 'inbound', '2026-06-05 08:00:00'),
    ('cC', 'inbound', '2026-06-19 14:30:00'),
    ('cD', 'inbound', '2026-06-19 10:00:00'),
    ('cE', 'inbound', '2026-06-19 10:00:00'),
    ('cF', 'inbound', '2026-06-19 11:00:00'),
    ('cG', 'inbound', '2026-06-19 12:00:00'),
    ('cH', 'inbound', '2026-06-19 15:00:00'),
    ('cI', 'inbound', '2026-05-20 08:00:00')");

// SQLite mirror of the new brand-new-customer universe. $firstContactOnly
// toggles the never-contacted-before rule so a test can show what it removes.
function count_new_leads(PDO $pdo, array $uids, $start, $end, $firstContactOnly = true) {
    $uids = array_values(array_filter(array_map('strval', $uids), 'strlen'));
    if (empty($uids)) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $neverContacted = $firstContactOnly ? "
          AND NOT EXISTS (
              SELECT 1 FROM ghl_messages gm_prior
              WHERE gm_prior.contact_id = glo.contact_id
                AND gm_prior.direction = 'inbound'
                AND gm_prior.date_added < glo.lead_started_at
          )" : "";
    $sql = "
        SELECT COUNT(DISTINCT glo.processed_lead_id) AS assigned_new_leads
        FROM ghl_lead_ownership glo
        WHERE glo.is_assigned_owner = 1
          AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
          {$neverContacted}
          AND glo.owner_user_id IN ({$placeholders})
          AND glo.lead_started_at BETWEEN ? AND ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($uids, array($start . ' 00:00:00', $end . ' 23:59:59')));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($row['assigned_new_leads']) ? (int) $row['assigned_new_leads'] : 0;
}

echo "[single inbox u1, June window]\n";
// Counts leads 1, 2, 4, 8 -> 4. Lead 3 is EXCLUDED (cC contacted us 06-05 -> not
// brand-new). Lead 4 counts even though assigned in July (first landed in June).
// Lead 9 excluded (landed in May). 5 (reply owner), 6 (owner != assignee), 7 (u2).
assert_eq('u1 brand-new leads in June', 4,
    count_new_leads($pdo, array('u1'), '2026-06-01', '2026-06-30'));

echo "[TC with two inboxes u1 + u1b]\n";
// Lead 8 is held by both inboxes; DISTINCT lead id keeps it one -> still 4.
assert_eq('two inboxes do not double-count lead 8', 4,
    count_new_leads($pdo, array('u1', 'u1b'), '2026-06-01', '2026-06-30'));

echo "[narrow to the single first-contact day 2026-06-19]\n";
// Leads 1,2,4,8 first landed on 06-19; lead 3 re-engaged (excluded), lead 9 landed 05-20.
assert_eq('same 4 land brand-new on 06-19', 4,
    count_new_leads($pdo, array('u1'), '2026-06-19', '2026-06-19'));

echo "[the never-contacted rule removes exactly the re-engaged lead]\n";
// Without the rule, June would count 5 leads (1,2,3,4,8); the rule drops lead 3
// (cC re-engaged) -> 4. This isolates the effect of "never contacted before".
assert_eq('June WITHOUT never-contacted rule', 5,
    count_new_leads($pdo, array('u1'), '2026-06-01', '2026-06-30', false));
assert_eq('June WITH never-contacted rule', 4,
    count_new_leads($pdo, array('u1'), '2026-06-01', '2026-06-30', true));

echo "[May window catches the brand-new lead 9]\n";
// Lead 9's customer first contacted 05-20, so it belongs to May, not the June
// its later ownership row sits in.
assert_eq('lead 9 belongs to May', 1,
    count_new_leads($pdo, array('u1'), '2026-05-01', '2026-05-31'));

echo "[July window: assignment date no longer pulls a lead in]\n";
// Lead 4 was ASSIGNED in July but first landed in June -> July counts nothing.
assert_eq('no lead first-landed in July', 0,
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
