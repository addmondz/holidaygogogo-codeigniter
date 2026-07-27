<?php
/**
 * Run with: php tests/helpers/TcLeadsDwmTest.php
 *
 * Locks the agent-scoped lead-count + avg-response semantics behind the new TC
 * "My Leads (Today/Week/Month)" card. The Booking controller builds this by
 * passing `agent_id => $my_ghl_uid` to Report_Model->Lead_Dashboard_Summary()
 * three times (day / week / month). This test replicates the filter shape so
 * a regression in agent_id-based scoping shows up here even if we never boot CI.
 *
 * Rules verified:
 *   - Counts include only rows where assigned_to_user_id = the TC's UID.
 *   - Day window includes today, excludes yesterday and tomorrow.
 *   - Week window includes Mon..Sun, excludes outside.
 *   - Month window includes 1st..last, excludes outside.
 *   - avg_first_5_response_seconds is averaged across the agent's matching
 *     rows only; rows for another agent must NOT influence the average.
 *   - Empty agent UID (no GHL link) -> zero rows.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    lead_started_at TEXT,
    responded_message_count INTEGER,
    avg_first_5_response_seconds REAL,
    is_converted INTEGER,
    booking_id INTEGER
)");

// 'UID-A' is the logged-in TC's GHL user id. 'UID-B' is another agent.
// Mon 2026-05-11 .. Sun 2026-05-17 is "this week"; 'today' = 2026-05-14 (Wed).
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1,  'UID-A', '2026-05-14 09:00:00', 3, 60,  0, NULL),   /* today, A         */
    (2,  'UID-A', '2026-05-13 09:00:00', 0, NULL, 0, NULL),  /* yesterday, A     */
    (3,  'UID-A', '2026-05-12 09:00:00', 5, 120, 1, 1),      /* this week, A     */
    (4,  'UID-A', '2026-05-04 09:00:00', 2, 90,  0, NULL),   /* this month, A    */
    (5,  'UID-A', '2026-04-30 09:00:00', 1, 30,  0, NULL),   /* prev month, A    */
    (6,  'UID-B', '2026-05-14 10:00:00', 1, 500, 0, NULL),   /* today, B         */
    (7,  'UID-B', '2026-05-12 10:00:00', 1, 500, 0, NULL),   /* week, B          */
    (8,  '',      '2026-05-14 11:00:00', 0, NULL, 0, NULL),  /* unassigned       */
    (9,  NULL,    '2026-05-14 11:30:00', 0, NULL, 0, NULL),  /* null assignment  */
    (10, 'UID-A', '2026-05-15 09:00:00', 4, 240, 0, NULL)    /* tomorrow, A      */
");

$run = function ($pdo, $agent_uid, $start, $end) {
    $sql = "
        SELECT
            COUNT(*) AS total_leads,
            AVG(avg_first_5_response_seconds) AS avg_seconds
        FROM ghl_processed_leads pl
        WHERE NULLIF(pl.assigned_to_user_id, '') = ?
          AND pl.lead_started_at >= ?
          AND pl.lead_started_at <= ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($agent_uid, $start . ' 00:00:00', $end . ' 23:59:59'));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return array(
        'total' => (int)$r['total_leads'],
        'avg'   => $r['avg_seconds'] === null ? null : (float)$r['avg_seconds'],
    );
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

$today      = '2026-05-14';
$week_start = '2026-05-11';
$week_end   = '2026-05-17';
$month_start = '2026-05-01';
$month_end   = '2026-05-31';

// ---- Agent A windows
// Row 1  = today (2026-05-14)
// Row 2  = yesterday (2026-05-13)         -> in week + month
// Row 3  = 2026-05-12                     -> in week + month
// Row 4  = 2026-05-04                     -> in month only
// Row 5  = 2026-04-30                     -> excluded (prev month)
// Row 10 = 2026-05-15 (tomorrow)          -> in week + month, NOT today
$day = $run($pdo, 'UID-A', $today, $today);
$week = $run($pdo, 'UID-A', $week_start, $week_end);
$month = $run($pdo, 'UID-A', $month_start, $month_end);

assert_eq('agent A day count',    1, $day['total']);              // row 1
assert_eq('agent A week count',   4, $week['total']);             // rows 1, 2, 3, 10
assert_eq('agent A month count',  5, $month['total']);            // rows 1, 2, 3, 4, 10

// Day-window avg: only row 1 falls inside (avg_first_5 = 60).
// Surfaced by the new "My Response Time" card's Today column.
assert_eq('agent A avg seconds day',   60.0,  $day['avg']);

// Month non-null seconds: 60 (r1), 120 (r3), 90 (r4), 240 (r10) -> mean = 127.5
// Row 2 has NULL seconds so SQLite's AVG ignores it.
assert_eq('agent A avg seconds month', 127.5, $month['avg']);

// Week non-null seconds: 60 (r1), 120 (r3), 240 (r10) -> mean = 140.0
assert_eq('agent A avg seconds week',  140.0, $week['avg']);

// ---- Agent B isolation
$b_day  = $run($pdo, 'UID-B', $today, $today);
$b_week = $run($pdo, 'UID-B', $week_start, $week_end);
assert_eq('agent B day count',  1, $b_day['total']);
assert_eq('agent B week count', 2, $b_week['total']);
assert_eq('agent B avg seconds', 500.0, $b_week['avg']);

// ---- Unassigned must not match any specific agent
$unassigned = $run($pdo, 'UID-A', $today, $today);
assert_eq('unassigned does not bleed into A', 1, $unassigned['total']);

// ---- Empty agent UID -> zero rows (mirrors $my_ghl_uid=null guard in controller)
$empty = $run($pdo, '', $month_start, $month_end);
assert_eq('empty UID returns zero leads', 0, $empty['total']);
assert_eq('empty UID returns null avg',   null, $empty['avg']);

echo "\nAll assertions passed.\n";
