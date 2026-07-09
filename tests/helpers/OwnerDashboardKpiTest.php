<?php
/**
 * Run with: php tests/helpers/OwnerDashboardKpiTest.php
 *
 * Locks the SQL behind the Owner-dashboard (Level 10) KPI cards, added in
 * Dashboard_Model:
 *   - Team_Sales($s,$e)             — SUM(NetTotal) per active team, window on InsertDate
 *   - New leads picked up ($s,$e)   — company-wide "New Lead Picked Up" total from
 *                                     ghl_lead_ownership, matching the Lead Reply
 *                                     Activity dashboard tfoot (distinct owner:lead
 *                                     pairs, windowed by pick-up time). This is
 *                                     Report_Model::Lead_Reply_Activity_Picked_Up_Total,
 *                                     surfaced on the owner card as owner_new_leads.
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
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id       TEXT,
    processed_lead_id   INTEGER,
    is_assigned_owner   INTEGER,
    assigned_to_user_id TEXT,
    assigned_at         TEXT,
    lead_started_at     TEXT
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY, BookingID INTEGER, Credit REAL, Debit REAL, Status TEXT, Date TEXT, Deadline TEXT
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
    // Company-wide "New Lead Picked Up" total: the same universe as the Lead
    // Reply Activity dashboard's tfoot — distinct owner:lead pairs (a lead
    // picked up by two owners counts for each, matching the sum of the per-agent
    // rows), windowed by the pick-up time COALESCE(assigned_at, lead_started_at).
    $st = $pdo->prepare("
        SELECT COUNT(DISTINCT (glo.owner_user_id || ':' || glo.processed_lead_id)) AS c
        FROM ghl_lead_ownership glo
        WHERE glo.is_assigned_owner = 1
          AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
          AND COALESCE(glo.assigned_at, glo.lead_started_at) >= :s
          AND COALESCE(glo.assigned_at, glo.lead_started_at) <= :e");
    $st->execute([':s'=>$start . ' 00:00:00', ':e'=>$end . ' 23:59:59']);
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
// Unapproved (pending) payment OUT — deadlines that fall WITHIN the window
// [:s, :e] only (bounded range, NOT cumulative), so the card matches the Payment
// listing's "Total Payment Out" filtered by deadline range. Overdue pay-outs whose
// deadline is before the window start are NOT counted here. Mirrors
// Unapproved_Payment_Out($start,$end): SUM(Debit) where Credit=0, Status='P'.
// Windows on DEADLINE, not Date: pending pay-outs have no transaction Date yet
// (only stamped once approved) — they carry a Deadline. Windowing on Date would
// exclude every pending row (all NULL) and the card would always read 0.
function unapproved_out(PDO $pdo, $start, $end) {
    $sql = "SELECT COALESCE(SUM(payment.Debit),0) AS Amount
            FROM booking LEFT JOIN payment ON payment.BookingID = booking.BookingID
            WHERE payment.Credit = 0
              AND payment.Status = 'P'
              AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND date(payment.Deadline) >= :s AND date(payment.Deadline) <= :e";
    $st = $pdo->prepare($sql); $st->execute([':s'=>$start, ':e'=>$end]);
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
// New leads picked up — company-wide "New Lead Picked Up" total
// (ghl_lead_ownership, windowed by pick-up time, distinct owner:lead)
// ===========================================================================
// pl 1  u1: picked up 06-15 (assigned_at in window)                     COUNT
// pl 2  u1: assigned_at NULL -> falls back to lead_started_at 06-15     COUNT (COALESCE)
// pl 3  u1: started 06-14 (before window) but PICKED UP 06-15           COUNT (pick-up drives window)
// pl 4  u1: started 06-15 (in window) but PICKED UP 06-16 (after)       EXCLUDED for the 06-15 day
// pl 5  u1: is_assigned_owner = 0 (reply owner only)                    EXCLUDED
// pl 6  u1: is_assigned_owner=1 BUT assigned_to_user_id=u2 (owner!=assignee) EXCLUDED
// pl 7  u2: picked up 06-15, different owner                            COUNT (company-wide)
// pl 8  u1 + u2: same lead picked up by two DIFFERENT owners 06-15      COUNT TWICE (owner:lead)
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, is_assigned_owner, assigned_to_user_id, assigned_at, lead_started_at) VALUES
    ('u1', 1, 1, 'u1', '2026-06-15 08:00:00', '2026-06-15 07:00:00'),
    ('u1', 2, 1, 'u1', NULL,                  '2026-06-15 23:30:00'),
    ('u1', 3, 1, 'u1', '2026-06-15 14:00:00', '2026-06-14 10:00:00'),
    ('u1', 4, 1, 'u1', '2026-06-16 10:00:00', '2026-06-15 10:00:00'),
    ('u1', 5, 0, 'u1', '2026-06-15 10:00:00', '2026-06-15 10:00:00'),
    ('u1', 6, 1, 'u2', '2026-06-15 11:00:00', '2026-06-15 11:00:00'),
    ('u2', 7, 1, 'u2', '2026-06-15 12:00:00', '2026-06-15 12:00:00'),
    ('u1', 8, 1, 'u1', '2026-06-15 15:00:00', '2026-06-15 15:00:00'),
    ('u2', 8, 1, 'u2', '2026-06-15 16:00:00', '2026-06-15 16:00:00')");
// Day 06-15: u1 leads 1,2,3,8 (4) + u2 leads 7,8 (2) = 6 owner:lead pairs.
assert_eq('picked up today', 6, new_leads($pdo, '2026-06-15', '2026-06-15'));
// Widen to 06-14..06-16: also picks up lead 4 (u1, assigned 06-16) -> 7.
assert_eq('picked up 3 days', 7, new_leads($pdo, '2026-06-14', '2026-06-16'));
assert_eq('picked up none',   0, new_leads($pdo, '2026-01-01', '2026-01-01'));

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
// Unapproved (pending) payment OUT — bounded deadline range (matches listing).
// ===========================================================================
// Pending pay-outs carry NULL Date (not stamped until approved) + a Deadline.
$pdo->exec("INSERT INTO payment (PaymentID,BookingID,Credit,Debit,Status,Date,Deadline) VALUES
    (10,1,0,100,'P',NULL,'2026-06-01'),   -- pending out due 06-01
    (11,1,0,200,'P',NULL,'2026-06-15'),   -- pending out due 06-15
    (12,1,0,300,'P',NULL,'2026-06-20'),   -- pending out due 06-20
    (13,1,0,999,'Y','2026-06-15','2026-06-15'), -- approved out -> excluded (Status Y)
    (14,6,0,400,'P',NULL,'2026-06-15')    -- pending out on cancelled booking 6 -> excluded
");
// Single day 06-15: only the 06-15 deadline (200). This is the "Today" cell shape.
// Regression: all pending rows have NULL Date — windowing on Date would drop
// them all and return 0. Windowing on Deadline keeps them.
assert_eq('unapproved out day 06-15', 200.0, unapproved_out($pdo, '2026-06-15', '2026-06-15'));
// Whole month 06-01..06-30: all three in range = 100 + 200 + 300 = 600.
assert_eq('unapproved out month', 600.0, unapproved_out($pdo, '2026-06-01', '2026-06-30'));
// Window starting 06-10: the 06-01 deadline is BEFORE the window -> excluded (200+300).
assert_eq('unapproved out no overdue', 500.0, unapproved_out($pdo, '2026-06-10', '2026-06-30'));
// Single day 06-01: just that day's deadline.
assert_eq('unapproved out day 06-01', 100.0, unapproved_out($pdo, '2026-06-01', '2026-06-01'));

echo "\nAll assertions passed.\n";
