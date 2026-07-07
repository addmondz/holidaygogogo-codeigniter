<?php
/**
 * Run with: php tests/helpers/OwnerDashboardKpiTest.php
 *
 * Locks the SQL behind the Owner-dashboard (Level 10) KPI cards, added in
 * Dashboard_Model:
 *   - Team_Sales($s,$e)             — SUM(NetTotal) per active team, window on InsertDate
 *   - New_Leads_Count($s,$e)        — count ghl_processed_leads by lead_started_at
 *   - Top_Cancellation_Reasons(...) — cancelled BCs grouped by reason, top N
 *   - Approved_Payment_Out($s,$e)   — SUM(Debit)  where Credit=0, Status='Y'
 *   - Approved_Payment_In($s,$e)    — SUM(Credit) where Credit!=0, Status='Y'
 *
 * The methods use CodeIgniter active-record against MySQL; this test replicates
 * the exact query shape against SQLite :memory: so the counted-booking filters
 * and window boundaries are locked without a live DB. date(col) mirrors MySQL's
 * CAST(col AS DATE) (strip the time part).
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Schema (only the columns the queries touch).
// ---------------------------------------------------------------------------
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    InsertDate TEXT,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    NetTotal REAL,
    SalesAgent INTEGER,
    CancellationReasonID INTEGER
)");
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, TeamID INTEGER)");
$pdo->exec("CREATE TABLE team (TeamID INTEGER PRIMARY KEY, Name TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE cancellation_reason (CancellationReasonID INTEGER PRIMARY KEY, Name TEXT)");
$pdo->exec("CREATE TABLE ghl_processed_leads (id INTEGER PRIMARY KEY, lead_started_at TEXT)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY, BookingID INTEGER, Credit REAL, Debit REAL, Status TEXT, Date TEXT
)");

$pdo->exec("INSERT INTO team VALUES
    (1, 'Alpha', 'Y'),
    (2, 'Beta',  'Y'),
    (3, 'Ghost', 'N')");            // inactive team -> excluded
$pdo->exec("INSERT INTO admin VALUES
    (10, 1),                        -- Alpha
    (11, 1),                        -- Alpha
    (12, 2),                        -- Beta
    (13, 3)");                      // Ghost (inactive team)
$pdo->exec("INSERT INTO cancellation_reason VALUES
    (100,'Duplicate'),(101,'Customer changed mind'),(102,'Price'),(103,'Weather')");

// ---------------------------------------------------------------------------
// Helper: run the shared "counted booking" window predicate for team sales.
// ---------------------------------------------------------------------------
function team_sales(PDO $pdo, $start, $end) {
    $sql = "SELECT team.TeamID AS TeamID, team.Name AS TeamName, COALESCE(SUM(booking.NetTotal),0) AS Sales
            FROM booking
            INNER JOIN admin ON admin.AdminID = booking.SalesAgent
            INNER JOIN team  ON team.TeamID = admin.TeamID
            WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND booking.NetTotal > 0
              AND team.Status = 'Y'
              AND date(booking.InsertDate) >= :s
              AND date(booking.InsertDate) <= :e
            GROUP BY team.TeamID, team.Name
            ORDER BY team.TeamID";
    $st = $pdo->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function unassigned_sales(PDO $pdo, $start, $end) {
    // Inverse of team_sales(): BC bookings whose agent is outside any active team
    // (no admin row, admin with no TeamID, or an inactive team). LEFT joins so the
    // unmatched rows survive; predicate keeps only the "not in an active team" set.
    $sql = "SELECT COALESCE(SUM(booking.NetTotal),0) AS Sales
            FROM booking
            LEFT JOIN admin ON admin.AdminID = booking.SalesAgent
            LEFT JOIN team  ON team.TeamID = admin.TeamID
            WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND booking.NetTotal > 0
              AND (team.TeamID IS NULL OR team.Status != 'Y')
              AND date(booking.InsertDate) >= :s
              AND date(booking.InsertDate) <= :e";
    $st = $pdo->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    return (float) $st->fetch(PDO::FETCH_ASSOC)['Sales'];
}
function new_leads(PDO $pdo, $start, $end) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM ghl_processed_leads
        WHERE date(lead_started_at) >= :s AND date(lead_started_at) <= :e");
    $st->execute([':s'=>$start, ':e'=>$end]);
    return (int) $st->fetch(PDO::FETCH_ASSOC)['c'];
}
function top_reasons(PDO $pdo, $start, $end, $limit) {
    $sql = "SELECT cr.Name AS Name, COUNT(booking.BookingID) AS Total
            FROM booking
            INNER JOIN cancellation_reason cr ON cr.CancellationReasonID = booking.CancellationReasonID
            WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'Y'
              AND booking.Status != 'N'
              AND date(booking.InsertDate) >= :s
              AND date(booking.InsertDate) <= :e
            GROUP BY cr.CancellationReasonID, cr.Name
            ORDER BY Total DESC, cr.Name ASC
            LIMIT {$limit}";
    $st = $pdo->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function pay(PDO $pdo, $col, $creditPred, $start, $end) {
    $sql = "SELECT COALESCE(SUM(payment.{$col}),0) AS Amount
            FROM booking LEFT JOIN payment ON payment.BookingID = booking.BookingID
            WHERE {$creditPred}
              AND payment.Status = 'Y'
              AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND date(payment.Date) >= :s AND date(payment.Date) <= :e";
    $st = $pdo->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
    return (float) $st->fetch(PDO::FETCH_ASSOC)['Amount'];
}

// ===========================================================================
// Team_Sales
// ===========================================================================
$pdo->exec("INSERT INTO booking (BookingID,InsertDate,BookingConfirmationTitle,CancelStatus,Status,NetTotal,SalesAgent,CancellationReasonID) VALUES
    (1,'2026-06-10 09:00:00','BOOKING CONFIRMATION','N','P',1000,10,NULL),  -- Alpha, in window
    (2,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','PP',500,11,NULL),  -- Alpha, in window
    (3,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',3000,12,NULL),  -- Beta, in window
    (4,'2026-06-09 12:00:00','BOOKING CONFIRMATION','N','P',9999,10,NULL),  -- Alpha, BEFORE window
    (5,'2026-06-10 12:00:00','QUOTATION','N','P',7000,10,NULL),             -- quotation excluded
    (6,'2026-06-10 12:00:00','BOOKING CONFIRMATION','Y','P',8000,10,NULL),  -- cancelled excluded
    (7,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','N',6000,10,NULL),  -- draft excluded
    (8,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',0,10,NULL),     -- zero excluded
    (9,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',4000,13,NULL)   -- Ghost (inactive team) excluded
");
$rows = team_sales($pdo, '2026-06-10', '2026-06-10');
assert_eq('team sales row count', 2, count($rows));
assert_eq('Alpha name',  'Alpha',  $rows[0]['TeamName']);
assert_eq('Alpha sales', 1500.0,   (float)$rows[0]['Sales']);   // 1000 + 500, day-scoped
assert_eq('Beta sales',  3000.0,   (float)$rows[1]['Sales']);
// Widen to include the 9th -> Alpha picks up +9999.
$rows = team_sales($pdo, '2026-06-09', '2026-06-10');
assert_eq('Alpha widened', 11499.0, (float)$rows[0]['Sales']);   // + booking 4 (9999) on 06-09

// ===========================================================================
// Unassigned_Sales — the "not in an active team" bucket that reconciles the
// team breakdown to the company total. Add a booking by an agent with NO admin
// row (agent 99); booking 9 already sits on the inactive "Ghost" team.
// ===========================================================================
$pdo->exec("INSERT INTO booking (BookingID,InsertDate,BookingConfirmationTitle,CancelStatus,Status,NetTotal,SalesAgent,CancellationReasonID) VALUES
    (30,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',250,99,NULL)   -- no admin row -> unassigned
");
// Day 06-10: Ghost booking 9 (4000) + no-admin booking 30 (250). The active-team
// bookings (Alpha 1500, Beta 3000) and the excluded rows must NOT leak in.
assert_eq('unassigned day', 4250.0, unassigned_sales($pdo, '2026-06-10', '2026-06-10'));
// Reconciliation: active teams + unassigned == every counted BC booking that day.
$teamDay = array_sum(array_map(function($r){ return (float)$r['Sales']; }, team_sales($pdo, '2026-06-10', '2026-06-10')));
assert_eq('company reconciles', 8750.0, $teamDay + unassigned_sales($pdo, '2026-06-10', '2026-06-10'));
assert_eq('unassigned none', 0.0, unassigned_sales($pdo, '2026-01-01', '2026-01-01'));

// ===========================================================================
// New_Leads_Count (windowed by lead_started_at, company-wide)
// ===========================================================================
$pdo->exec("INSERT INTO ghl_processed_leads (id,lead_started_at) VALUES
    (1,'2026-06-15 08:00:00'),
    (2,'2026-06-15 23:30:00'),
    (3,'2026-06-14 10:00:00'),
    (4,'2026-06-16 10:00:00')");
assert_eq('leads today',   2, new_leads($pdo, '2026-06-15', '2026-06-15'));
assert_eq('leads 3 days',  4, new_leads($pdo, '2026-06-14', '2026-06-16'));
assert_eq('leads none',    0, new_leads($pdo, '2026-01-01', '2026-01-01'));

// ===========================================================================
// Top_Cancellation_Reasons
// ===========================================================================
$pdo->exec("INSERT INTO booking (BookingID,InsertDate,BookingConfirmationTitle,CancelStatus,Status,NetTotal,SalesAgent,CancellationReasonID) VALUES
    (20,'2026-03-01','BOOKING CONFIRMATION','Y','P',100,10,101), -- changed mind
    (21,'2026-03-02','BOOKING CONFIRMATION','Y','P',100,10,101), -- changed mind
    (22,'2026-03-02','BOOKING CONFIRMATION','Y','P',100,10,101), -- changed mind
    (23,'2026-03-03','BOOKING CONFIRMATION','Y','P',100,10,102), -- price
    (24,'2026-03-04','BOOKING CONFIRMATION','Y','P',100,10,102), -- price
    (25,'2026-03-05','BOOKING CONFIRMATION','Y','P',100,10,103), -- weather
    (26,'2026-03-06','BOOKING CONFIRMATION','N','P',100,10,101), -- NOT cancelled -> excluded
    (27,'2026-03-07','QUOTATION','Y','P',100,10,101)             -- quotation -> excluded
");
$rows = top_reasons($pdo, '2026-01-01', '2026-12-31', 5);
assert_eq('reasons count', 3, count($rows));
assert_eq('top reason name',  'Customer changed mind', $rows[0]['Name']);
assert_eq('top reason total', 3, (int)$rows[0]['Total']);
assert_eq('2nd reason name',  'Price', $rows[1]['Name']);
$rows = top_reasons($pdo, '2026-01-01', '2026-12-31', 2);
assert_eq('limit 2 applied', 2, count($rows));

// ===========================================================================
// Approved payment IN / OUT
// ===========================================================================
$pdo->exec("INSERT INTO payment (PaymentID,BookingID,Credit,Debit,Status,Date) VALUES
    (1,1,500,0,'Y','2026-06-15'),   -- IN approved (booking 1 = confirmed/not cancelled)
    (2,1,300,0,'Y','2026-06-16'),   -- IN approved next day
    (3,1,0,200,'Y','2026-06-15'),   -- OUT approved
    (4,1,999,0,'P','2026-06-15'),   -- pending -> excluded
    (5,6,400,0,'Y','2026-06-15')    -- booking 6 is cancelled -> excluded
");
assert_eq('pay in week',  800.0, pay($pdo,'Credit',"payment.Credit != 0", '2026-06-15','2026-06-16'));
assert_eq('pay in day',   500.0, pay($pdo,'Credit',"payment.Credit != 0", '2026-06-15','2026-06-15'));
assert_eq('pay out day',  200.0, pay($pdo,'Debit', "payment.Credit = 0",  '2026-06-15','2026-06-15'));
assert_eq('pay out none', 0.0,   pay($pdo,'Debit', "payment.Credit = 0",  '2026-06-16','2026-06-16'));

echo "\nAll assertions passed.\n";
