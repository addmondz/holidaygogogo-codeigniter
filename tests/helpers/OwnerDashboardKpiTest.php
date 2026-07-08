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
    TeamID INTEGER,
    CancellationReasonID INTEGER
)");
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, TeamID INTEGER)");
$pdo->exec("CREATE TABLE team (TeamID INTEGER PRIMARY KEY, Name TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE cancellation_reason (CancellationReasonID INTEGER PRIMARY KEY, Name TEXT)");
$pdo->exec("CREATE TABLE ghl_processed_leads (id INTEGER PRIMARY KEY, lead_started_at TEXT)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY, BookingID INTEGER, Credit REAL, Debit REAL, Status TEXT, Date TEXT
)");
$pdo->exec("CREATE TABLE sales_target (AdminID INTEGER, target_year INTEGER, target_month INTEGER, target_amount REAL)");
$pdo->exec("CREATE TABLE sales_target_year (AdminID INTEGER, target_year INTEGER, target_amount REAL)");

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
    // Group on the FROZEN booking.TeamID snapshot (point-in-time), not the live
    // admin.TeamID, so a later team move can't rewrite past team totals.
    $sql = "SELECT team.TeamID AS TeamID, team.Name AS TeamName, COALESCE(SUM(booking.NetTotal),0) AS Sales
            FROM booking
            INNER JOIN team  ON team.TeamID = booking.TeamID
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
    // Inverse of team_sales(): BC bookings whose frozen snapshot is outside any
    // active team (booking.TeamID is NULL, or the snapshotted team is inactive).
    // LEFT join so unmatched rows survive; predicate keeps the "not active" set.
    $sql = "SELECT COALESCE(SUM(booking.NetTotal),0) AS Sales
            FROM booking
            LEFT JOIN team  ON team.TeamID = booking.TeamID
            WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND booking.NetTotal > 0
              AND (booking.TeamID IS NULL OR team.Status != 'Y')
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
// Unapproved (pending) payment OUT — cumulative UP TO :e (no lower bound) so
// overdue pending pay-outs scheduled before the window are still counted. This
// mirrors Unapproved_Payment_Out($end): SUM(Debit) where Credit=0, Status='P'.
function unapproved_out(PDO $pdo, $end) {
    $sql = "SELECT COALESCE(SUM(payment.Debit),0) AS Amount
            FROM booking LEFT JOIN payment ON payment.BookingID = booking.BookingID
            WHERE payment.Credit = 0
              AND payment.Status = 'P'
              AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND date(payment.Date) <= :e";
    $st = $pdo->prepare($sql); $st->execute([':e'=>$end]);
    return (float) $st->fetch(PDO::FETCH_ASSOC)['Amount'];
}

// ===========================================================================
// Team_Sales
// ===========================================================================
// TeamID = the frozen snapshot (each agent's team at sale time): 10,11 -> Alpha(1),
// 12 -> Beta(2), 13 -> Ghost(3).
$pdo->exec("INSERT INTO booking (BookingID,InsertDate,BookingConfirmationTitle,CancelStatus,Status,NetTotal,SalesAgent,TeamID,CancellationReasonID) VALUES
    (1,'2026-06-10 09:00:00','BOOKING CONFIRMATION','N','P',1000,10,1,NULL),  -- Alpha, in window
    (2,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','PP',500,11,1,NULL),  -- Alpha, in window
    (3,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',3000,12,2,NULL),  -- Beta, in window
    (4,'2026-06-09 12:00:00','BOOKING CONFIRMATION','N','P',9999,10,1,NULL),  -- Alpha, BEFORE window
    (5,'2026-06-10 12:00:00','QUOTATION','N','P',7000,10,1,NULL),             -- quotation excluded
    (6,'2026-06-10 12:00:00','BOOKING CONFIRMATION','Y','P',8000,10,1,NULL),  -- cancelled excluded
    (7,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','N',6000,10,1,NULL),  -- draft excluded
    (8,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',0,10,1,NULL),     -- zero excluded
    (9,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',4000,13,3,NULL)   -- Ghost (inactive team) excluded
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
$pdo->exec("INSERT INTO booking (BookingID,InsertDate,BookingConfirmationTitle,CancelStatus,Status,NetTotal,SalesAgent,TeamID,CancellationReasonID) VALUES
    (30,'2026-06-10 12:00:00','BOOKING CONFIRMATION','N','P',250,99,NULL,NULL)   -- no team snapshot -> unassigned
");
// Day 06-10: Ghost booking 9 (4000) + no-admin booking 30 (250). The active-team
// bookings (Alpha 1500, Beta 3000) and the excluded rows must NOT leak in.
assert_eq('unassigned day', 4250.0, unassigned_sales($pdo, '2026-06-10', '2026-06-10'));
// Reconciliation: active teams + unassigned == every counted BC booking that day.
$teamDay = array_sum(array_map(function($r){ return (float)$r['Sales']; }, team_sales($pdo, '2026-06-10', '2026-06-10')));
assert_eq('company reconciles', 8750.0, $teamDay + unassigned_sales($pdo, '2026-06-10', '2026-06-10'));
assert_eq('unassigned none', 0.0, unassigned_sales($pdo, '2026-01-01', '2026-01-01'));

// ===========================================================================
// Team_Targets — per active team, SUM of member agents' monthly (sales_target,
// year+month) and yearly (sales_target_year, year) targets. Membership via
// admin.TeamID; inactive teams (Ghost) and their agents' targets are excluded.
// ===========================================================================
function team_targets(PDO $pdo, $year, $month) {
    $out = [];
    $sql = "SELECT admin.TeamID AS TeamID, COALESCE(SUM(st.target_amount),0) AS Amount
            FROM sales_target st
            INNER JOIN admin ON admin.AdminID = st.AdminID
            INNER JOIN team  ON team.TeamID = admin.TeamID
            WHERE st.target_year = :y AND st.target_month = :m AND team.Status = 'Y'
            GROUP BY admin.TeamID";
    $s = $pdo->prepare($sql); $s->execute([':y'=>$year, ':m'=>$month]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['TeamID']]['month'] = (float)$r['Amount']; }
    $sql = "SELECT admin.TeamID AS TeamID, COALESCE(SUM(sty.target_amount),0) AS Amount
            FROM sales_target_year sty
            INNER JOIN admin ON admin.AdminID = sty.AdminID
            INNER JOIN team  ON team.TeamID = admin.TeamID
            WHERE sty.target_year = :y AND team.Status = 'Y'
            GROUP BY admin.TeamID";
    $s = $pdo->prepare($sql); $s->execute([':y'=>$year]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['TeamID']]['year'] = (float)$r['Amount']; }
    return $out;
}
$pdo->exec("INSERT INTO sales_target (AdminID,target_year,target_month,target_amount) VALUES
    (10,2026,6,1000),   -- Alpha
    (11,2026,6, 500),   -- Alpha
    (12,2026,6,3000),   -- Beta
    (13,2026,6,4000),   -- Ghost (inactive team) -> excluded
    (10,2026,5, 800)    -- different month -> excluded from June
");
$pdo->exec("INSERT INTO sales_target_year (AdminID,target_year,target_amount) VALUES
    (10,2026,12000),    -- Alpha
    (11,2026, 6000),    -- Alpha
    (12,2026,36000),    -- Beta
    (13,2026,48000),    -- Ghost -> excluded
    (10,2025, 9000)     -- different year -> excluded
");
$tg = team_targets($pdo, 2026, 6);
assert_eq('Alpha month target', 1500.0, $tg[1]['month']);   // 1000 + 500
assert_eq('Beta month target',  3000.0, $tg[2]['month']);
assert_eq('Alpha year target', 18000.0, $tg[1]['year']);    // 12000 + 6000
assert_eq('Beta year target',  36000.0, $tg[2]['year']);
assert_eq('Ghost team excluded', false, isset($tg[3]));      // inactive team absent
// A month with no monthly rows drops the 'month' key, but yearly targets are
// year-scoped so they still show for that year.
$tg1 = team_targets($pdo, 2026, 1);
assert_eq('no month key when unset', false,   isset($tg1[1]['month']));
assert_eq('year still present',      18000.0, $tg1[1]['year']);

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

// ===========================================================================
// Unapproved (pending) payment OUT — cumulative "due by :end", captures overdue.
// ===========================================================================
$pdo->exec("INSERT INTO payment (PaymentID,BookingID,Credit,Debit,Status,Date) VALUES
    (10,1,0,100,'P','2026-06-01'),   -- OVERDUE pending out (before any window) -> must count
    (11,1,0,200,'P','2026-06-15'),   -- pending out today
    (12,1,0,300,'P','2026-06-20'),   -- pending out future (later this month)
    (13,1,0,999,'Y','2026-06-15'),   -- approved out -> excluded (Status Y)
    (14,6,0,400,'P','2026-06-15')    -- pending out on cancelled booking 6 -> excluded
");
// Due by 06-15: overdue 100 + today 200 = 300 (future 300 not yet due).
assert_eq('unapproved out to today', 300.0, unapproved_out($pdo, '2026-06-15'));
// Due by month-end: + future 300 = 600. Overdue still included, none dropped.
assert_eq('unapproved out to month', 600.0, unapproved_out($pdo, '2026-06-30'));
// Even a window ending before every scheduled date still catches the 06-01 overdue.
assert_eq('unapproved out to 06-01', 100.0, unapproved_out($pdo, '2026-06-01'));

echo "\nAll assertions passed.\n";
