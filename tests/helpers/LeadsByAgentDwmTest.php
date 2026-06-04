<?php
/**
 * Run with: php tests/helpers/LeadsByAgentDwmTest.php
 *
 * Locks the SQL behind the OWNER-only "Leads by Agent (Today / Week / Month)"
 * table. The Booking controller builds this by calling
 * Report_Model->Lead_Dashboard_Leads_By_Agent_DWM($today, $week_start,
 * $week_end, $month_start, $month_end), which fans the three Leads-card windows
 * out per agent in a single conditional-SUM query. This test replicates that
 * query shape so a regression in the windowing / grouping / agent-name
 * resolution shows up here even without booting CI.
 *
 * Rules verified:
 *   - One row per assigned agent; unassigned ('' or NULL) rows excluded.
 *   - day_cnt counts only leads started today.
 *   - week_cnt counts Mon..Sun of this week.
 *   - month_cnt counts 1st..last of this month.
 *   - A single lead counts in every window it falls inside (today => also
 *     week + month), so the columns nest exactly like the Leads card.
 *   - Leads outside the widest window (range) never count.
 *   - agent_name resolves from ghl_users.Name, falling back to the raw UID
 *     when the agent has no ghl_users row.
 *   - Rows are ordered by month_cnt DESC, then week, then day, then name.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    lead_started_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (
    UserID TEXT,
    Name   TEXT
)");

// today = 2026-06-03 (Wed). Week Mon 2026-06-01 .. Sun 2026-06-07.
// Month 2026-06-01 .. 2026-06-30.
$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES
    ('UID-A', 'Aisha'),
    ('UID-B', 'Ben')
");
// UID-C deliberately has no ghl_users row -> name should fall back to 'UID-C'.

$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1,  'UID-A', '2026-06-03 09:00:00'),  /* today  -> day+week+month, A */
    (2,  'UID-A', '2026-06-02 09:00:00'),  /* week+month, A              */
    (3,  'UID-A', '2026-06-10 09:00:00'),  /* month only, A              */
    (4,  'UID-A', '2026-05-20 09:00:00'),  /* prev month -> excluded     */
    (5,  'UID-B', '2026-06-03 10:00:00'),  /* today  -> day+week+month, B */
    (6,  'UID-B', '2026-06-04 10:00:00'),  /* week+month, B              */
    (7,  'UID-C', '2026-06-15 10:00:00'),  /* month only, C (no name)    */
    (8,  '',      '2026-06-03 11:00:00'),  /* unassigned -> excluded     */
    (9,  NULL,    '2026-06-03 11:30:00')   /* null assign -> excluded    */
");

// Mirror of Report_Model::Lead_Dashboard_Leads_By_Agent_DWM().
$run = function ($pdo, $today, $week_start, $week_end, $month_start, $month_end) {
    // Outer WHERE is bounded by the union of the three windows so a week that
    // spills into an adjacent month near a boundary is still scanned, while
    // all-time leads are not.
    $starts = array($today, $week_start, $month_start);
    $ends   = array($today, $week_end, $month_end);
    $range_start = min($starts);
    $range_end   = max($ends);

    $sql = "
        SELECT
            NULLIF(pl.assigned_to_user_id, '') AS agent_id,
            COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, '')) AS agent_name,
            SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS day_cnt,
            SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_cnt,
            SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_cnt
        FROM ghl_processed_leads pl
        LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
        WHERE NULLIF(pl.assigned_to_user_id, '') IS NOT NULL
          AND pl.lead_started_at BETWEEN ? AND ?
        GROUP BY agent_id, agent_name
        HAVING (day_cnt + week_cnt + month_cnt) > 0
        ORDER BY month_cnt DESC, week_cnt DESC, day_cnt DESC, agent_name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
        $today . ' 00:00:00',       $today . ' 23:59:59',
        $week_start . ' 00:00:00',  $week_end . ' 23:59:59',
        $month_start . ' 00:00:00', $month_end . ' 23:59:59',
        $range_start . ' 00:00:00', $range_end . ' 23:59:59',
    ));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = array(
            'agent_id'   => $r['agent_id'],
            'agent_name' => $r['agent_name'],
            'day'        => (int)$r['day_cnt'],
            'week'       => (int)$r['week_cnt'],
            'month'      => (int)$r['month_cnt'],
        );
    }
    return $out;
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$rows = $run($pdo, '2026-06-03', '2026-06-01', '2026-06-07', '2026-06-01', '2026-06-30');

// Three assigned agents only; unassigned rows 8 & 9 dropped.
assert_eq('agent count', 3, count($rows));

// Ordered by month DESC: Aisha(3), Ben(2), UID-C(1).
assert_eq('row 0 name', 'Aisha', $rows[0]['agent_name']);
assert_eq('row 1 name', 'Ben',   $rows[1]['agent_name']);
assert_eq('row 2 name', 'UID-C', $rows[2]['agent_name']);  // name falls back to UID

// Aisha: r1 today, r2 week, r3 month (r4 prev month excluded by range).
assert_eq('Aisha day',   1, $rows[0]['day']);
assert_eq('Aisha week',  2, $rows[0]['week']);
assert_eq('Aisha month', 3, $rows[0]['month']);

// Ben: r5 today, r6 week.
assert_eq('Ben day',   1, $rows[1]['day']);
assert_eq('Ben week',  2, $rows[1]['week']);
assert_eq('Ben month', 2, $rows[1]['month']);

// UID-C: r7 month only.
assert_eq('UID-C day',   0, $rows[2]['day']);
assert_eq('UID-C week',  0, $rows[2]['week']);
assert_eq('UID-C month', 1, $rows[2]['month']);

// Windows nest: every today lead is also a week + month lead.
foreach ($rows as $r) {
    if ($r['day'] > $r['week'] || $r['week'] > $r['month']) {
        echo "  FAIL  windows not nested for {$r['agent_name']}: "
           . "day={$r['day']} week={$r['week']} month={$r['month']}\n";
        exit(1);
    }
}
echo "  PASS  windows nest (day <= week <= month) for all agents\n";

echo "\nAll assertions passed.\n";
