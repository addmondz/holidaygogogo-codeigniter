<?php
/**
 * Run with: php tests/helpers/LeadsByHourPickedUpTest.php
 *
 * Pins the "Leads By Hour" report AFTER it was switched to the "New Lead Picked
 * Up" universe. The grid no longer counts every ghl_processed_leads row by the
 * hour the lead *landed*; it now counts PICKED-UP leads from ghl_lead_ownership
 * bucketed by the hour they were picked up, exactly like the dashboard column:
 *
 *   FROM ghl_lead_ownership glo
 *   WHERE glo.is_assigned_owner = 1
 *     AND NULLIF(glo.assigned_to_user_id,'') = glo.owner_user_id   (owner = real assignee)
 *   bucket = DATE/HOUR of COALESCE(glo.assigned_at, glo.lead_started_at)  (pick-up time)
 *   count  = COUNT(DISTINCT owner_user_id ':' processed_lead_id)   (matches dashboard total)
 *
 * This mirrors the model SQL in SQLite (HOUR()->strftime, CONCAT->||) so the
 * counting rules are locked independently of MySQL, then feeds the sparse rows
 * through the real leads_by_hour_build_matrix() to confirm the grid totals.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/leads_by_hour_helper.php';

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

// pl 1  u1: picked up 2026-06-19 09:15  -> 06-19 h9   COUNT
// pl 2  u1: assigned_at NULL, lead_started 06-19 09:45 -> falls back -> 06-19 h9  COUNT (COALESCE)
// pl 3  u2: picked up 2026-06-19 14:30  -> 06-19 h14  COUNT
// pl 4  u1: is_assigned_owner = 0 (reply owner only)  -> EXCLUDED
// pl 5  u1: is_assigned_owner=1 BUT assigned_to_user_id=u2 (owner != assignee) -> EXCLUDED
// pl 6  u1: picked up 2026-06-20 09:05  -> 06-20 h9   COUNT
// pl 1  u1: duplicate ownership row, same hour -> DISTINCT owner:lead dedups -> still 1
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, is_assigned_owner, assigned_to_user_id, assigned_at, lead_started_at) VALUES
    ('u1', 1, 1, 'u1', '2026-06-19 09:15:00', '2026-06-19 09:00:00'),
    ('u1', 2, 1, 'u1', NULL,                  '2026-06-19 09:45:00'),
    ('u2', 3, 1, 'u2', '2026-06-19 14:30:00', '2026-06-19 14:00:00'),
    ('u1', 4, 0, 'u1', '2026-06-19 10:00:00', '2026-06-19 10:00:00'),
    ('u1', 5, 1, 'u2', '2026-06-19 11:00:00', '2026-06-19 11:00:00'),
    ('u1', 6, 1, 'u1', '2026-06-20 09:05:00', '2026-06-20 09:00:00'),
    ('u1', 1, 1, 'u1', '2026-06-19 09:50:00', '2026-06-19 09:00:00')");

// SQLite mirror of the model query. Pick-up date = COALESCE(assigned_at, lead_started_at);
// DATE()/HOUR() -> strftime; CONCAT() -> ||. WHERE mirrors build_lead_reply_assignment_where_clause.
function run(PDO $pdo, $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- 1) Full range: which cells light up ---
$assign = "COALESCE(glo.assigned_at, glo.lead_started_at)";
$baseSql = "
    SELECT
        DATE({$assign}) AS lead_date,
        CAST(strftime('%H', {$assign}) AS INTEGER) AS hour_of_day,
        COUNT(DISTINCT glo.owner_user_id || ':' || glo.processed_lead_id) AS lead_count
    FROM ghl_lead_ownership glo
    WHERE glo.is_assigned_owner = 1
      AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
      {WHERE}
    GROUP BY lead_date, hour_of_day
    ORDER BY lead_date ASC, hour_of_day ASC
";

echo "[full range]\n";
$rows = run($pdo, str_replace('{WHERE}', '', $baseSql), array());
$dates = leads_by_hour_expand_dates('2026-06-19', '2026-06-20');
$m = leads_by_hour_build_matrix($rows, $dates);

$cell = function($m, $date, $hour) {
    foreach ($m['rows'] as $r) { if ($r['date'] === $date) return $r['counts'][$hour]; }
    return null;
};
assert_eq('06-19 h9 (leads 1 & 2)',       2, $cell($m, '2026-06-19', 9));
assert_eq('06-19 h14 (lead 3)',           1, $cell($m, '2026-06-19', 14));
assert_eq('06-20 h9 (lead 6)',            1, $cell($m, '2026-06-20', 9));
assert_eq('06-19 h10 (reply-owner excl)', 0, $cell($m, '2026-06-19', 10));
assert_eq('06-19 h11 (owner!=assignee)',  0, $cell($m, '2026-06-19', 11));
assert_eq('grand total (dup deduped)',    4, $m['grand_total']);
assert_eq('hour_totals[9] both days',     3, $m['hour_totals'][9]);

// --- 2) Date filter locks to 06-19 (pick-up time drives the window) ---
echo "[date filter: 2026-06-19 only]\n";
$where = " AND {$assign} >= ? AND {$assign} <= ? ";
$rows = run($pdo, str_replace('{WHERE}', $where, $baseSql),
    array('2026-06-19 00:00:00', '2026-06-19 23:59:59'));
$m = leads_by_hour_build_matrix($rows, leads_by_hour_expand_dates('2026-06-19', '2026-06-19'));
assert_eq('grand total (06-20 excluded)', 3, $m['grand_total']);

// --- 3) Agent filter (owner_user_id = u1) ---
echo "[agent filter: u1]\n";
$where = " AND glo.owner_user_id IN (?) ";
$rows = run($pdo, str_replace('{WHERE}', $where, $baseSql), array('u1'));
$m = leads_by_hour_build_matrix($rows, leads_by_hour_expand_dates('2026-06-19', '2026-06-20'));
assert_eq('u1 only: leads 1,2,6', 3, $m['grand_total']);
assert_eq('u1 06-19 h9',          2, $cell($m, '2026-06-19', 9));
assert_eq('u1 06-19 h14 (u2 gone)', 0, $cell($m, '2026-06-19', 14));

echo "\nALL PASSED\n";
