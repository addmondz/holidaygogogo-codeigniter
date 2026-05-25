<?php
/**
 * Run with: php tests/helpers/SalesByTeamCardTest.php
 *
 * Locks the SQL behind the Finance "Sales by Team (Month)" summary card.
 * Each row aggregates NetTotal for confirmations created this month, grouped
 * by the team-lead admin (admin.TeamLeadID -> admin.AdminID). Bookings whose
 * SalesAgent doesn't roll up to any team lead fall into a single "Unassigned"
 * bucket so totals always reconcile to the team-wide total.
 *
 * Invariant: SUM(per-team totals) = SUM(NetTotal for all valid bookings this
 * month). If team-assignment changes silently drop bookings, the team
 * breakdown would no longer add up to the team-wide Sales card.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    TeamLeadID INTEGER
)");

$month_start = '2026-05-01';
$month_end   = '2026-05-31';

// Team A = admin 10 (lead). Team B = admin 20 (lead).
// Agents 11, 12 under team A. Agent 21 under team B. Agent 99 has no team.
$pdo->exec("INSERT INTO admin VALUES
    (10, 'Lead A',   NULL),
    (11, 'Agent A1', 10),
    (12, 'Agent A2', 10),
    (20, 'Lead B',   NULL),
    (21, 'Agent B1', 20),
    (99, 'Solo',     NULL)
");

$pdo->exec("INSERT INTO booking VALUES
    (1, 11, 1000.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-03'),
    (2, 12, 1500.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-10'),
    (3, 21,  800.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-15'),
    (4, 99,  600.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-20'),
    (5, 11, 9999.00, 'BOOKING CONFIRMATION', 'Y', 'P',  '2026-05-03'),  /* cancelled */
    (6, 11, 9999.00, 'BOOKING CONFIRMATION', 'N', 'N',  '2026-05-03'),  /* draft */
    (7, 11, 9999.00, 'QUOTATION',            'N', 'P',  '2026-05-03'),  /* quotation */
    (8, 11, 5000.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-04-30'),  /* out of window */
    (9, 21,  500.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-25')
");

// Per-team aggregation. COALESCE on the team lead's AdminID groups any agent
// without a TeamLeadID under "Unassigned" (id 0) so SUM(totals) reconciles
// with the team-wide total below.
$sql = "
    SELECT
        COALESCE(tl.AdminID, 0) AS team_lead_id,
        COALESCE(tl.Name, 'Unassigned') AS team_lead_name,
        COUNT(*) AS bc_count,
        COALESCE(SUM(booking.NetTotal), 0) AS total
    FROM booking
    LEFT JOIN admin agent ON agent.AdminID = booking.SalesAgent
    LEFT JOIN admin tl ON tl.AdminID = agent.TeamLeadID
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY team_lead_id, team_lead_name
    ORDER BY total DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':ms' => $month_start, ':me' => $month_end));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Team-wide total for reconciliation.
$totStmt = $pdo->prepare("
    SELECT COALESCE(SUM(NetTotal), 0) AS total, COUNT(*) AS cnt
    FROM booking
    WHERE BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND CancelStatus = 'N'
      AND Status != 'N'
      AND InsertDate BETWEEN :ms AND :me
");
$totStmt->execute(array(':ms' => $month_start, ':me' => $month_end));
$tot = $totStmt->fetch(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$by_team = array();
foreach ($rows as $r) {
    $by_team[(int) $r['team_lead_id']] = $r;
}

assert_eq('team count', 3, count($rows));
assert_eq('Team A total (1000+1500)',  2500.0, (float) $by_team[10]['total']);
assert_eq('Team A count',              2,       (int)   $by_team[10]['bc_count']);
assert_eq('Team B total (800+500)',    1300.0, (float) $by_team[20]['total']);
assert_eq('Team B count',              2,       (int)   $by_team[20]['bc_count']);
assert_eq('Unassigned total (600)',     600.0, (float) $by_team[0]['total']);
assert_eq('Unassigned count',           1,      (int)   $by_team[0]['bc_count']);

$sum_per_team = 0.0;
foreach ($rows as $r) { $sum_per_team += (float) $r['total']; }
assert_eq('partition: per-team sums to team-wide', (float) $tot['total'], $sum_per_team);

echo "\nAll assertions passed.\n";
