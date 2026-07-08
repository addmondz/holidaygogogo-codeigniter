<?php
/**
 * Run with: php tests/helpers/SalesByTeamCardTest.php
 *
 * Locks the SQL behind the Finance "Sales by Team (Month)" summary card.
 * Each row aggregates NetTotal for confirmations created this month, grouped
 * by the FROZEN booking.TeamID snapshot — the team the sale was credited under
 * at the time — NOT the SalesAgent's live admin.TeamID. Bookings whose snapshot
 * is NULL, or whose team is inactive, fall into a single "Unassigned" bucket so
 * totals always reconcile to the team-wide total.
 *
 * Invariant: SUM(per-team totals) = SUM(NetTotal for all valid bookings this
 * month). If team-assignment changes silently drop bookings, the team
 * breakdown would no longer add up to the team-wide Sales card.
 *
 * Regression guard: agent 12 has since been MOVED to Team B (admin.TeamID = 2),
 * but their May sale was credited under Team A (booking.TeamID = 1) and must
 * stay in Team A. The old live-join logic would have wrongly moved it to B.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    TeamID INTEGER,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE team (
    TeamID INTEGER PRIMARY KEY,
    Name TEXT,
    Status TEXT
)");

$month_start = '2026-05-01';
$month_end   = '2026-05-31';

$pdo->exec("INSERT INTO team VALUES (1, 'Team A', 'Y'), (2, 'Team B', 'Y')");

// booking.TeamID = the frozen team the sale was credited under. Bookings 1 & 2
// were made under Team A; booking 2's agent has since moved to Team B but the
// snapshot keeps the sale in Team A. Booking 4 has no team snapshot.
$pdo->exec("INSERT INTO booking VALUES
    (1, 11, 1,    1000.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-03'),
    (2, 12, 1,    1500.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-10'),
    (3, 21, 2,     800.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-15'),
    (4, 99, NULL,  600.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-20'),
    (5, 11, 1,    9999.00, 'BOOKING CONFIRMATION', 'Y', 'P',  '2026-05-03'),  /* cancelled */
    (6, 11, 1,    9999.00, 'BOOKING CONFIRMATION', 'N', 'N',  '2026-05-03'),  /* draft */
    (7, 11, 1,    9999.00, 'QUOTATION',            'N', 'P',  '2026-05-03'),  /* quotation */
    (8, 11, 1,    5000.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-04-30'),  /* out of window */
    (9, 21, 2,     500.00, 'BOOKING CONFIRMATION', 'N', 'P',  '2026-05-25')
");

// Per-team aggregation on the frozen snapshot. COALESCE groups any booking with
// no active-team snapshot under "Unassigned" (id 0) so SUM(totals) reconciles
// with the team-wide total below.
$sql = "
    SELECT
        COALESCE(t.TeamID, 0) AS team_id,
        COALESCE(t.Name, 'Unassigned') AS team_name,
        COUNT(*) AS bc_count,
        COALESCE(SUM(booking.NetTotal), 0) AS total
    FROM booking
    LEFT JOIN team t ON t.TeamID = booking.TeamID AND t.Status = 'Y'
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY team_id, team_name
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
    $by_team[(int) $r['team_id']] = $r;
}

assert_eq('team count', 3, count($rows));
assert_eq('Team A total (1000+1500)',  2500.0, (float) $by_team[1]['total']);
assert_eq('Team A count',              2,       (int)   $by_team[1]['bc_count']);
assert_eq('Team B total (800+500)',    1300.0, (float) $by_team[2]['total']);
assert_eq('Team B count',              2,       (int)   $by_team[2]['bc_count']);
assert_eq('Unassigned total (600)',     600.0, (float) $by_team[0]['total']);
assert_eq('Unassigned count',           1,      (int)   $by_team[0]['bc_count']);

$sum_per_team = 0.0;
foreach ($rows as $r) { $sum_per_team += (float) $r['total']; }
assert_eq('partition: per-team sums to team-wide', (float) $tot['total'], $sum_per_team);

echo "\nAll assertions passed.\n";
