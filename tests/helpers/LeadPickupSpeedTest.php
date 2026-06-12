<?php
/**
 * Run with: php tests/helpers/LeadPickupSpeedTest.php
 *
 * Locks the SQL behind the TC "Lead Pickup Speed (Today / Week / Month)" card
 * and its team-wide "Best:" footer. The Booking controller builds the card by
 * calling Report_Model->Lead_Pickup_Speed_Summary() per window and
 * Report_Model->Lead_Pickup_Speed_Best_Agent() once for the month.
 *
 * Pickup speed = RAW wall-clock gap from when the lead started a brand-new
 * conversation (pl.lead_started_at) to the agent's FIRST reply
 * (pl.response_1_agent_message_at). This is distinct from "My Response Time",
 * which averages the first 5 reply gaps and is duty-hours aware.
 *
 * Rules verified:
 *   - Only leads that were actually picked up (response_1_agent_message_at not
 *     null) and have a start anchor count; un-replied leads are ignored.
 *   - Negative gaps (clock skew: reply stamped before start) are excluded so a
 *     bad row can't drag the average below zero.
 *   - The average is a plain mean of the per-lead gaps, windowed by
 *     lead_started_at.
 *   - n counts only the qualifying (picked-up) leads in the window.
 *   - Best-agent footer groups by assigned agent, requires >= 2 qualifying
 *     leads (min-sample guard, same as Draft -> Payment Time), and returns the
 *     fastest (lowest average) agent, name resolved from ghl_users.Name with a
 *     fallback to the raw UID.
 *
 * NOTE: production runs on MySQL and uses UNIX_TIMESTAMP(); SQLite has no such
 * function, so this mirror uses strftime('%s', ...). Only the LOGIC is locked
 * here, matching the convention of the other lead-dashboard mirror tests.
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
    response_1_agent_message_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (
    UserID TEXT,
    Name   TEXT
)");

$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES
    ('UID-A', 'Aisha'),
    ('UID-B', 'Ben')
");
// UID-C has no ghl_users row -> name should fall back to 'UID-C'.

// Month window under test: 2026-06-01 .. 2026-06-30.
// Gaps (seconds): A r1=30s, A r2=90s  -> avg 60s ; A r3 prev-month (excluded).
//                 B r5=600s, B r6=200s -> avg 400s.
//                 C r7=10s (only one -> dropped by min-sample for the footer).
//                 A r-skew: reply BEFORE start -> excluded everywhere.
//                 A r-noreply: picked up null -> excluded.
$pdo->exec("INSERT INTO ghl_processed_leads
    (id, assigned_to_user_id, lead_started_at, response_1_agent_message_at) VALUES
    (1, 'UID-A', '2026-06-03 09:00:00', '2026-06-03 09:00:30'),  /* +30s   */
    (2, 'UID-A', '2026-06-10 09:00:00', '2026-06-10 09:01:30'),  /* +90s   */
    (3, 'UID-A', '2026-05-20 09:00:00', '2026-05-20 09:00:10'),  /* prev month */
    (4, 'UID-A', '2026-06-12 09:00:00', '2026-06-12 08:59:50'),  /* skew -> excl */
    (5, 'UID-A', '2026-06-15 09:00:00',  NULL),                  /* no reply */
    (6, 'UID-B', '2026-06-05 10:00:00', '2026-06-05 10:10:00'),  /* +600s  */
    (7, 'UID-B', '2026-06-06 10:00:00', '2026-06-06 10:03:20'),  /* +200s  */
    (8, 'UID-C', '2026-06-15 10:00:00', '2026-06-15 10:00:10'),  /* +10s, single */
    (9, '',      '2026-06-03 11:00:00', '2026-06-03 11:00:05')   /* unassigned */
");

// ---- Mirror of Report_Model::Lead_Pickup_Speed_Summary() (per agent set) ----
// gap = strftime seconds; only picked-up, non-skewed, in-window rows averaged.
$summary = function ($pdo, $agentIds, $start, $end) {
    $place = implode(',', array_fill(0, count($agentIds), '?'));
    $gap = "(strftime('%s', pl.response_1_agent_message_at) - strftime('%s', pl.lead_started_at))";
    $qual = "pl.response_1_agent_message_at IS NOT NULL
             AND pl.lead_started_at IS NOT NULL
             AND {$gap} >= 0";
    $sql = "
        SELECT
            AVG(CASE WHEN {$qual} THEN {$gap} END) AS avg_seconds,
            SUM(CASE WHEN {$qual} THEN 1 ELSE 0 END) AS n
        FROM ghl_processed_leads pl
        WHERE NULLIF(pl.assigned_to_user_id, '') IN ({$place})
          AND pl.lead_started_at >= ?
          AND pl.lead_started_at <= ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($agentIds, array($start . ' 00:00:00', $end . ' 23:59:59')));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return array(
        'avg_seconds' => $r['avg_seconds'] === null ? null : (int) round($r['avg_seconds']),
        'count'       => (int) $r['n'],
    );
};

// ---- Mirror of Report_Model::Lead_Pickup_Speed_Best_Agent() ----
$best = function ($pdo, $start, $end) {
    $gap = "(strftime('%s', pl.response_1_agent_message_at) - strftime('%s', pl.lead_started_at))";
    $sql = "
        SELECT
            NULLIF(pl.assigned_to_user_id, '') AS agent_id,
            COALESCE(NULLIF(gu.Name, ''), NULLIF(pl.assigned_to_user_id, '')) AS agent_name,
            AVG({$gap}) AS avg_seconds,
            COUNT(*) AS n
        FROM ghl_processed_leads pl
        LEFT JOIN ghl_users gu ON gu.UserID = NULLIF(pl.assigned_to_user_id, '')
        WHERE NULLIF(pl.assigned_to_user_id, '') IS NOT NULL
          AND pl.lead_started_at >= ? AND pl.lead_started_at <= ?
          AND pl.response_1_agent_message_at IS NOT NULL
          AND pl.lead_started_at IS NOT NULL
          AND {$gap} >= 0
        GROUP BY agent_id, agent_name
        HAVING n >= 2
        ORDER BY avg_seconds ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($start . ' 00:00:00', $end . ' 23:59:59'));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    return array(
        'agent_id'    => $r['agent_id'],
        'agent_name'  => $r['agent_name'],
        'avg_seconds' => (int) round($r['avg_seconds']),
        'n'           => (int) $r['n'],
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

$mStart = '2026-06-01';
$mEnd   = '2026-06-30';

// Aisha's own card: rows 1 (+30) and 2 (+90) qualify -> avg 60s, n 2.
// Row 3 prev-month, row 4 skew, row 5 no reply all excluded.
$a = $summary($pdo, array('UID-A'), $mStart, $mEnd);
assert_eq('Aisha avg_seconds', 60, $a['avg_seconds']);
assert_eq('Aisha count',        2, $a['count']);

// Ben's own card: rows 6 (+600) and 7 (+200) -> avg 400s, n 2.
$b = $summary($pdo, array('UID-B'), $mStart, $mEnd);
assert_eq('Ben avg_seconds', 400, $b['avg_seconds']);
assert_eq('Ben count',         2, $b['count']);

// Empty window -> null avg, zero count (front-end renders an em-dash).
$empty = $summary($pdo, array('UID-A'), '2026-01-01', '2026-01-31');
assert_eq('empty avg_seconds', null, $empty['avg_seconds']);
assert_eq('empty count',          0, $empty['count']);

// Best agent (month): Aisha 60s beats Ben 400s; C excluded (only 1 lead);
// unassigned row 9 never grouped.
$top = $best($pdo, $mStart, $mEnd);
assert_eq('best agent_id',    'UID-A', $top['agent_id']);
assert_eq('best agent_name',  'Aisha', $top['agent_name']);
assert_eq('best avg_seconds',      60, $top['avg_seconds']);

echo "\nAll assertions passed.\n";
