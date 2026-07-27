<?php
/**
 * Run with: php tests/helpers/TeamSalesPointInTimeTest.php
 *
 * Locks the SQL behind "Total Sales by Team" AFTER the point-in-time rework.
 *
 * Sales are grouped by booking.TeamID — the team the credited agent belonged
 * to AT THE TIME of the sale — NOT by the agent's CURRENT admin.TeamID. This
 * keeps the year-over-year benchmark honest: an agent who moves team (or leaves)
 * next year must not retroactively pull this year's sale out of the team that
 * earned it.
 *
 * Scenario: Agent 11 sold under Team A (booking.TeamID = 1) but has since been
 * moved to Team B (admin.TeamID = 2). The card must still credit that sale to
 * Team A. The OLD "join admin -> live TeamID" logic would wrongly move it to B.
 *
 * Invariant preserved: SUM(per-team totals incl. Unassigned) = SUM(all valid
 * BC NetTotal in the window).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// booking now carries a frozen TeamID snapshot alongside the live SalesAgent.
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

$ms = '2026-05-01';
$me = '2026-05-31';

$pdo->exec("INSERT INTO team VALUES (1, 'Team A', 'Y'), (2, 'Team B', 'Y'), (3, 'Team C', 'N')");

// Key row: booking 1 was credited to agent 11 UNDER Team A (TeamID=1). Even
// though agent 11 now lives in Team B, the snapshot keeps the sale in Team A.
$pdo->exec("INSERT INTO booking VALUES
    (1, 11, 1,    1000.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-03'),
    (2, 12, 1,    1500.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-10'),
    (3, 21, 2,     800.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-15'),
    (4, 99, NULL,  600.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-20'),  /* no team -> unassigned */
    (5, 11, 1,    9999.00, 'BOOKING CONFIRMATION', 'Y', 'P', '2026-05-03'),  /* cancelled */
    (6, 11, 1,    9999.00, 'BOOKING CONFIRMATION', 'N', 'N', '2026-05-03'),  /* draft */
    (7, 11, 1,    9999.00, 'QUOTATION',            'N', 'P', '2026-05-03'),  /* quotation */
    (8, 11, 1,    5000.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-04-30'),  /* out of window */
    (9, 22, 3,     700.00, 'BOOKING CONFIRMATION', 'N', 'P', '2026-05-22')   /* inactive team -> unassigned */
");

// Active-team totals: group by the FROZEN booking.TeamID (Team_Sales()).
$team_sql = "
    SELECT team.TeamID AS team_id, team.Name AS team_name,
           COALESCE(SUM(booking.NetTotal), 0) AS total
    FROM booking
    INNER JOIN team ON team.TeamID = booking.TeamID
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.NetTotal > 0
      AND team.Status = 'Y'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY team.TeamID, team.Name
";
$stmt = $pdo->prepare($team_sql);
$stmt->execute(array(':ms' => $ms, ':me' => $me));
$by_team = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $by_team[(int) $r['team_id']] = (float) $r['total'];
}

// Unassigned: snapshot TeamID is NULL, or its team is inactive (Unassigned_Sales()).
$un_sql = "
    SELECT COALESCE(SUM(booking.NetTotal), 0) AS total
    FROM booking
    LEFT JOIN team ON team.TeamID = booking.TeamID
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.NetTotal > 0
      AND (booking.TeamID IS NULL OR team.Status != 'Y')
      AND booking.InsertDate BETWEEN :ms AND :me
";
$stmt = $pdo->prepare($un_sql);
$stmt->execute(array(':ms' => $ms, ':me' => $me));
$unassigned = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Team A keeps agent 11's sale (1000) + agent 12's (1500) = 2500, even though
// agent 11 has since been moved to Team B.
assert_eq('Team A keeps moved-agent sale', 2500.0, $by_team[1]);
// Team B only has its own booking (800) — it does NOT gain agent 11's history.
assert_eq('Team B unchanged by the move',   800.0, isset($by_team[2]) ? $by_team[2] : 0.0);
// Unassigned = no-team (600) + inactive-team (700) = 1300.
assert_eq('Unassigned (no team + inactive)', 1300.0, $unassigned);

// Partition invariant: active teams + unassigned = all valid BC in window.
$tot = (float) $pdo->query("
    SELECT COALESCE(SUM(NetTotal), 0) FROM booking
    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND CancelStatus='N' AND Status!='N' AND NetTotal > 0
      AND InsertDate BETWEEN '{$ms}' AND '{$me}'
")->fetchColumn();
$sum_parts = $unassigned;
foreach ($by_team as $t) { $sum_parts += $t; }
assert_eq('partition reconciles to BC total', $tot, $sum_parts);

echo "\nAll assertions passed.\n";
