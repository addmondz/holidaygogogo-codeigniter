<?php
/**
 * Run with: php tests/helpers/CustomerPaymentDueSoonCardTest.php
 *
 * Locks the SQL behind the OP "Payment From Customer Due Soon" summary
 * card. It is the money-IN mirror of "Supplier Pay-out Due Soon": it
 * surfaces the immediate customer-receivable horizon, bucketed into three
 * actionable groups so OP can triage by urgency:
 *
 *   - overdue  : window_start <= next_deadline < today   (already passed)
 *   - today    : next_deadline  =  today
 *   - tomorrow : next_deadline  =  today+1
 *
 * Unlike supplier payouts (whose deadline lives on the payment row),
 * customer payment deadlines live on the BOOKING. The operative "next
 * due" deadline depends on where the BC is in the payment flow:
 *
 *   - Status 'P'  (pending payment, nothing received): the deposit is due
 *     first -> DepositDeadline, falling back to FullPaymentDeadline when
 *     no deposit schedule was set.
 *   - Status 'PP' (partial payment, deposit in): the balance is due ->
 *     FullPaymentDeadline.
 *
 * A BC is in scope when:
 *   - booking.CancelStatus = 'N'                 (active)
 *   - booking.Status IN ('P','PP')               (still owes a scheduled payment)
 *   - next_deadline BETWEEN window_start AND today+1   (since-March..tomorrow)
 *   - outstanding balance > 0, where
 *       outstanding = NetTotal - SUM(approved customer credits)
 *       approved credit = payment.Status='Y' AND Credit>0 AND
 *                         Type != 'AGENT COMMISSION FROM SUPPLIER'
 *
 * The amount shown per bucket/row is the outstanding balance still owed.
 * Mirrors the booking-list PO / P / PP status filters so the drill-down
 * agrees with the card.
 *
 * Invariant: the three bucket counts/totals partition the windowed set,
 * and the per-booking breakdown (earliest deadline first) sums back to the
 * windowed grand total.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    BookingNumber TEXT,
    Customer TEXT,
    Status TEXT,
    CancelStatus TEXT,
    NetTotal REAL,
    DepositDeadline TEXT,
    FullPaymentDeadline TEXT
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    Type TEXT,
    Status TEXT,
    Credit REAL
)");

$today    = '2026-05-20';
$tomorrow = '2026-05-21';
$minus1   = '2026-05-19';
$win_start = '2026-03-01';   // overdue lookback floor (1 March, current year)
$mar1      = '2026-03-01';   // exactly on the floor — included
$preMar    = '2026-02-10';   // before the floor — excluded

// BookingID, Number, Customer, Status, CancelStatus, NetTotal, DepositDeadline, FullPaymentDeadline
$pdo->exec("INSERT INTO booking VALUES
    /* 1  P, deposit overdue yesterday, nothing paid     -> OVERDUE 1000 */
    (1, 'BC-1', 'Alpha', 'P',  'N', 1000.0, '{$minus1}', '2026-08-01'),
    /* 2  PP, balance due today, RM500 deposit paid       -> TODAY 1500  */
    (2, 'BC-2', 'Beta',  'PP', 'N', 2000.0, '{$minus1}', '{$today}'),
    /* 3  P, no deposit schedule, full due tomorrow        -> TOMORROW 800 (fallback) */
    (3, 'BC-3', 'Gamma', 'P',  'N',  800.0, NULL,        '{$tomorrow}'),
    /* 4  PP, balance due tomorrow, fully paid             -> EXCLUDED (outstanding 0) */
    (4, 'BC-4', 'Delta', 'PP', 'N', 1200.0, '{$minus1}', '{$tomorrow}'),
    /* 5  P, deposit due in Feb (before floor)             -> EXCLUDED (outside window) */
    (5, 'BC-5', 'Eps',   'P',  'N',  900.0, '{$preMar}', '2026-09-01'),
    /* 6  P, deposit due today but CANCELLED               -> EXCLUDED (cancelled) */
    (6, 'BC-6', 'Zeta',  'P',  'Y',  500.0, '{$today}',  '2026-09-01'),
    /* 7  Completed (Status Y), full today                 -> EXCLUDED (not P/PP) */
    (7, 'BC-7', 'Eta',   'Y',  'N',  700.0, '{$minus1}', '{$today}'),
    /* 8  PP, balance overdue on the floor (1 Mar), RM300  -> OVERDUE 700 */
    (8, 'BC-8', 'Theta', 'PP', 'N', 1000.0, '{$minus1}', '{$mar1}'),
    /* 9  PP, balance due today, only supplier-commission  -> TODAY 1000 (commission ignored) */
    (9, 'BC-9', 'Iota',  'PP', 'N', 1000.0, '{$minus1}', '{$today}')
");

$pdo->exec("INSERT INTO payment VALUES
    /* BC2 deposit (counts toward paid)                    */
    (1, 2, 'DEPOSIT', 'Y', 500.0),
    /* BC4 full payment — fully settles the BC             */
    (2, 4, 'FULL',    'Y', 1200.0),
    /* BC8 partial deposit                                  */
    (3, 8, 'DEPOSIT', 'Y', 300.0),
    /* BC9 only an agent-commission-from-supplier credit — must NOT reduce what the customer owes */
    (4, 9, 'AGENT COMMISSION FROM SUPPLIER', 'Y', 1000.0),
    /* BC2 a pending (Status=P) credit — not yet approved, must NOT count */
    (5, 2, 'FULL',    'P', 999.0)
");

// Operative customer deadline + outstanding balance — the two derived
// expressions the card aggregates over.
$nd  = "(CASE WHEN booking.Status = 'P' THEN COALESCE(booking.DepositDeadline, booking.FullPaymentDeadline) ELSE booking.FullPaymentDeadline END)";
$out = "(booking.NetTotal - COALESCE((SELECT SUM(p.Credit) FROM payment p"
     . " WHERE p.BookingID = booking.BookingID"
     . " AND p.Status = 'Y' AND p.Credit > 0"
     . " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0))";

// Three buckets, computed in one pass — mirrors the controller's CASE
// aggregation so the headline reconciles with the windowed table.
$bucketStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN t.nd <  :today THEN 1 ELSE 0 END)                          AS overdue_cnt,
        COALESCE(SUM(CASE WHEN t.nd <  :today THEN t.outstanding ELSE 0 END), 0) AS overdue_due,
        SUM(CASE WHEN t.nd =  :today THEN 1 ELSE 0 END)                          AS today_cnt,
        COALESCE(SUM(CASE WHEN t.nd =  :today THEN t.outstanding ELSE 0 END), 0) AS today_due,
        SUM(CASE WHEN t.nd =  :tmr   THEN 1 ELSE 0 END)                          AS tomorrow_cnt,
        COALESCE(SUM(CASE WHEN t.nd =  :tmr   THEN t.outstanding ELSE 0 END), 0) AS tomorrow_due
    FROM (
        SELECT {$nd} AS nd, {$out} AS outstanding
        FROM booking
        WHERE booking.CancelStatus = 'N'
          AND booking.Status IN ('P','PP')
    ) t
    WHERE t.nd BETWEEN :start AND :tmr2
      AND t.outstanding > 0
");
$bucketStmt->execute(array(':today' => $today, ':tmr' => $tomorrow, ':start' => $win_start, ':tmr2' => $tomorrow));
$b = $bucketStmt->fetch(PDO::FETCH_ASSOC);

// Per-booking breakdown across the whole window, earliest deadline first
// (most overdue leads), ties broken by largest outstanding.
$perStmt = $pdo->prepare("
    SELECT t.BookingNumber AS booking_number, t.Customer AS customer,
           t.nd AS earliest_deadline, t.outstanding AS total_due
    FROM (
        SELECT booking.BookingNumber AS BookingNumber, booking.Customer AS Customer,
               {$nd} AS nd, {$out} AS outstanding
        FROM booking
        WHERE booking.CancelStatus = 'N'
          AND booking.Status IN ('P','PP')
    ) t
    WHERE t.nd BETWEEN :start AND :tmr
      AND t.outstanding > 0
    ORDER BY t.nd ASC, t.outstanding DESC
    LIMIT 5
");
$perStmt->execute(array(':start' => $win_start, ':tmr' => $tomorrow));
$rows = $perStmt->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- Bucket headlines --------------------------------------------------
// Overdue spans 1 Mar..yesterday: BC8 (mar1, owes 700) + BC1 (minus1, owes 1000).
assert_eq('overdue count (BC1 + BC8)',          2,       (int)   $b['overdue_cnt']);
assert_eq('overdue total (1000 + 700)',         1700.0,  (float) $b['overdue_due']);
// Today: BC2 (owes 1500) + BC9 (commission ignored, owes 1000).
assert_eq('today count (BC2 + BC9)',            2,       (int)   $b['today_cnt']);
assert_eq('today total (1500 + 1000)',          2500.0,  (float) $b['today_due']);
// Tomorrow: BC3 (fallback to FullPaymentDeadline, owes 800). BC4 fully paid -> out.
assert_eq('tomorrow count (BC3)',               1,       (int)   $b['tomorrow_cnt']);
assert_eq('tomorrow total (800)',               800.0,   (float) $b['tomorrow_due']);

// ---- Per-booking table (windowed) -------------------------------------
assert_eq('rows shown (5 owing BCs in window)', 5,       count($rows));
// Order: BC8 (1 Mar) -> BC1 (19 May) -> BC2 (20 May, 1500) -> BC9 (20 May, 1000) -> BC3 (21 May).
assert_eq('row 0 = BC8 (1 Mar, most overdue)',  'BC-8',  (string) $rows[0]['booking_number']);
assert_eq('row 1 = BC1 (yesterday)',            'BC-1',  (string) $rows[1]['booking_number']);
assert_eq('row 2 = BC2 (today, larger owed)',   'BC-2',  (string) $rows[2]['booking_number']);
assert_eq('row 3 = BC9 (today, smaller owed)',  'BC-9',  (string) $rows[3]['booking_number']);
assert_eq('row 4 = BC3 (tomorrow)',             'BC-3',  (string) $rows[4]['booking_number']);
assert_eq('BC8 outstanding (1000-300)',         700.0,   (float)  $rows[0]['total_due']);
assert_eq('BC8 earliest_deadline (1 Mar)',      $mar1,   (string) $rows[0]['earliest_deadline']);

// Excluded BCs never surface.
$nums = array_map(function ($r) { return $r['booking_number']; }, $rows);
assert_eq('BC4 absent (fully paid)',            false,   in_array('BC-4', $nums, true));
assert_eq('BC5 absent (before floor)',          false,   in_array('BC-5', $nums, true));
assert_eq('BC6 absent (cancelled)',             false,   in_array('BC-6', $nums, true));
assert_eq('BC7 absent (Status not P/PP)',       false,   in_array('BC-7', $nums, true));

// ---- Invariants --------------------------------------------------------
// Buckets partition the windowed set: counts and totals add up.
$bucket_cnt   = (int)$b['overdue_cnt'] + (int)$b['today_cnt'] + (int)$b['tomorrow_cnt'];
$bucket_total = (float)$b['overdue_due'] + (float)$b['today_due'] + (float)$b['tomorrow_due'];
assert_eq('buckets partition: total count',  5,       $bucket_cnt);
assert_eq('buckets partition: total amount', 5000.0,  $bucket_total);

// Per-booking sums reconcile with the windowed grand total (only 5 rows here).
$sum = 0.0;
foreach ($rows as $r) { $sum += (float) $r['total_due']; }
assert_eq('partition: per-booking sums to windowed total', $bucket_total, $sum);

echo "\nAll assertions passed.\n";
