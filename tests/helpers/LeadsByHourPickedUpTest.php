<?php
/**
 * Run with: php tests/helpers/LeadsByHourPickedUpTest.php
 *
 * Pins the "Leads By Hour" report to the LANDING-TIME universe (reverted from
 * the short-lived picked-up alignment). The grid counts every ghl_processed_leads
 * row by the hour the lead *landed* (first inbound), NOT the hour it was picked
 * up, so it no longer double-counts a lead per owner:
 *
 *   FROM ghl_processed_leads pl
 *   bucket = DATE/HOUR of pl.lead_started_at        (landing time)
 *   count  = COUNT(*)                               (one row per lead)
 *   WHERE  = build_lead_dashboard_where_clause      (date on lead_started_at,
 *            agent on NULLIF(pl.assigned_to_user_id,'') )
 *
 * This mirrors the model SQL in SQLite (HOUR()->strftime) so the counting rules
 * are locked independently of MySQL, then feeds the sparse rows through the real
 * leads_by_hour_build_matrix() to confirm the grid totals.
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

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id                  INTEGER,
    assigned_to_user_id TEXT,
    lead_started_at     TEXT
)");

// pl 1  u1: landed 2026-06-19 09:15  -> 06-19 h9   COUNT
// pl 2  u1: landed 2026-06-19 09:45  -> 06-19 h9   COUNT
// pl 3  u2: landed 2026-06-19 14:30  -> 06-19 h14  COUNT
// pl 4  '': landed 2026-06-19 10:00  -> 06-19 h10  COUNT (unassigned still lands)
// pl 5  u1: landed 2026-06-20 09:05  -> 06-20 h9   COUNT
$pdo->exec("INSERT INTO ghl_processed_leads
    (id, assigned_to_user_id, lead_started_at) VALUES
    (1, 'u1', '2026-06-19 09:15:00'),
    (2, 'u1', '2026-06-19 09:45:00'),
    (3, 'u2', '2026-06-19 14:30:00'),
    (4, '',   '2026-06-19 10:00:00'),
    (5, 'u1', '2026-06-20 09:05:00')");

// SQLite mirror of the model query. Landing bucket = pl.lead_started_at;
// DATE()/HOUR() -> strftime. WHERE mirrors build_lead_dashboard_where_clause.
function run(PDO $pdo, $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$baseSql = "
    SELECT
        DATE(pl.lead_started_at) AS lead_date,
        CAST(strftime('%H', pl.lead_started_at) AS INTEGER) AS hour_of_day,
        COUNT(*) AS lead_count
    FROM ghl_processed_leads pl
    WHERE 1=1
      {WHERE}
    GROUP BY lead_date, hour_of_day
    ORDER BY lead_date ASC, hour_of_day ASC
";

$cell = function($m, $date, $hour) {
    foreach ($m['rows'] as $r) { if ($r['date'] === $date) return $r['counts'][$hour]; }
    return null;
};

// --- 1) Full range: every landed lead counts once, by landing hour ---
echo "[full range]\n";
$rows = run($pdo, str_replace('{WHERE}', '', $baseSql), array());
$dates = leads_by_hour_expand_dates('2026-06-19', '2026-06-20');
$m = leads_by_hour_build_matrix($rows, $dates);

assert_eq('06-19 h9 (leads 1 & 2)',   2, $cell($m, '2026-06-19', 9));
assert_eq('06-19 h10 (unassigned 4)', 1, $cell($m, '2026-06-19', 10));
assert_eq('06-19 h14 (lead 3)',       1, $cell($m, '2026-06-19', 14));
assert_eq('06-20 h9 (lead 5)',        1, $cell($m, '2026-06-20', 9));
assert_eq('grand total',              5, $m['grand_total']);
assert_eq('hour_totals[9] both days', 3, $m['hour_totals'][9]);

// --- 2) Date filter locks to 06-19 (landing time drives the window) ---
echo "[date filter: 2026-06-19 only]\n";
$where = " AND pl.lead_started_at >= ? AND pl.lead_started_at <= ? ";
$rows = run($pdo, str_replace('{WHERE}', $where, $baseSql),
    array('2026-06-19 00:00:00', '2026-06-19 23:59:59'));
$m = leads_by_hour_build_matrix($rows, leads_by_hour_expand_dates('2026-06-19', '2026-06-19'));
assert_eq('grand total (06-20 excluded)', 4, $m['grand_total']);

// --- 3) Agent filter (assigned_to_user_id = u1) ---
echo "[agent filter: u1]\n";
$where = " AND NULLIF(pl.assigned_to_user_id, '') IN (?) ";
$rows = run($pdo, str_replace('{WHERE}', $where, $baseSql), array('u1'));
$m = leads_by_hour_build_matrix($rows, leads_by_hour_expand_dates('2026-06-19', '2026-06-20'));
assert_eq('u1 only: leads 1,2,5', 3, $m['grand_total']);
assert_eq('u1 06-19 h9',          2, $cell($m, '2026-06-19', 9));
assert_eq('u1 06-19 h14 (u2 gone)', 0, $cell($m, '2026-06-19', 14));

echo "\nALL PASSED\n";
