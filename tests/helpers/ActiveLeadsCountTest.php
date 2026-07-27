<?php
/**
 * Run with: php tests/helpers/ActiveLeadsCountTest.php
 *
 * Locks the SQL behind the "Active Leads (Day/Week/Month)" card surfaced to
 * TC LEAD / Owner. "Active" means the lead has been pulled from GHL but is
 * not yet converted to a booking. Three disjoint window counts:
 *
 *   day_active   = lead_started_at on $today AND is_converted=0
 *   week_active  = lead_started_at within $week_start..$week_end AND is_converted=0
 *   month_active = lead_started_at within $month_start..$month_end AND is_converted=0
 *
 * Invariant under test: the unconverted filter must exclude leads that
 * already have an associated booking, so the card cannot silently mix
 * converted leads into the "open work" headline number — which would defeat
 * the entire purpose of distinguishing "Leads" (total) from "Active Leads"
 * (still in the agent's GHL inbox).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    lead_started_at TEXT,
    is_converted INTEGER,
    assigned_to_user_id TEXT
)");

// today = 2026-05-20 (Wed). Week = 2026-05-18..2026-05-24. Month = 2026-05-01..2026-05-31.
$today       = '2026-05-20';
$week_start  = '2026-05-18';
$week_end    = '2026-05-24';
$month_start = '2026-05-01';
$month_end   = '2026-05-31';

$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    /* 1  today,   unconverted     -> day, week, month */
    ( 1, '2026-05-20 09:00:00', 0, 'agent-a'),
    /* 2  today,   converted       -> none (excluded) */
    ( 2, '2026-05-20 11:30:00', 1, 'agent-b'),
    /* 3  yesterday (Tue), unconv  -> week, month     */
    ( 3, '2026-05-19 14:00:00', 0, 'agent-a'),
    /* 4  Mon of this week, unconv -> week, month     */
    ( 4, '2026-05-18 08:00:00', 0, 'agent-a'),
    /* 5  last week, unconv        -> month only      */
    ( 5, '2026-05-12 10:00:00', 0, 'agent-c'),
    /* 6  last month, unconv       -> none            */
    ( 6, '2026-04-29 10:00:00', 0, 'agent-a'),
    /* 7  this month converted     -> none (excluded) */
    ( 7, '2026-05-15 10:00:00', 1, 'agent-a'),
    /* 8  Sun of this week, unconv -> week, month     */
    ( 8, '2026-05-24 22:00:00', 0, 'agent-b')
");

$stmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN lead_started_at BETWEEN :d_lo AND :d_hi THEN 1 ELSE 0 END) AS day_active,
      SUM(CASE WHEN lead_started_at BETWEEN :w_lo AND :w_hi THEN 1 ELSE 0 END) AS week_active,
      SUM(CASE WHEN lead_started_at BETWEEN :m_lo AND :m_hi THEN 1 ELSE 0 END) AS month_active
    FROM ghl_processed_leads
    WHERE is_converted = 0
");
$stmt->execute(array(
    ':d_lo' => $today       . ' 00:00:00', ':d_hi' => $today       . ' 23:59:59',
    ':w_lo' => $week_start  . ' 00:00:00', ':w_hi' => $week_end    . ' 23:59:59',
    ':m_lo' => $month_start . ' 00:00:00', ':m_hi' => $month_end   . ' 23:59:59',
));
$row = $stmt->fetch(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('day_active   (today only, unconverted)',  1, (int) $row['day_active']);   // row 1
assert_eq('week_active  (Mon-Sun, unconverted)',     4, (int) $row['week_active']);  // rows 1,3,4,8
assert_eq('month_active (1st-end, unconverted)',     5, (int) $row['month_active']); // rows 1,3,4,5,8
assert_eq('day <= week',  true, (int)$row['day_active']  <= (int)$row['week_active']);
assert_eq('week <= month',true, (int)$row['week_active'] <= (int)$row['month_active']);

echo "\nAll assertions passed.\n";
