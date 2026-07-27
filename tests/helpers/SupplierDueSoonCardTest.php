<?php
/**
 * Run with: php tests/helpers/SupplierDueSoonCardTest.php
 *
 * Locks the SQL behind the OP "Supplier Pay-out Due Soon" summary card.
 * The card surfaces the immediate supplier-payout horizon, bucketed into
 * three actionable groups so OP can triage by urgency:
 *
 *   - overdue  : window_start <= payment.Deadline < today   (already passed)
 *   - today    : payment.Deadline  =  today
 *   - tomorrow : payment.Deadline  =  today+1
 *
 * The overdue lookback starts at window_start (1 March of the current
 * year) so stale pre-March payouts don't clutter the card. All three
 * buckets share the same row filters; only the Deadline predicate
 * differs. A supplier payout row is in scope when:
 *
 *   - payment.Status = 'P'                          (pending — not yet paid)
 *   - payment.Debit > 0                             (money out, not money in)
 *   - payment.Type LIKE 'SUPPLIER PAYMENT%'         (deposit/full/additional)
 *   - supplier.SupplierID linked                    (orphans excluded)
 *   - payment.Deadline BETWEEN window_start AND today+1  (window = since-March..tomorrow)
 *
 * Invariant: the three bucket counts/totals partition the windowed set, and
 * the per-supplier breakdown (overdue..tomorrow, earliest deadline first)
 * sums back to the windowed grand total.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    SupplierID INTEGER,
    Type TEXT,
    Status TEXT,
    Debit REAL,
    Credit REAL,
    Deadline TEXT
)");
$pdo->exec("CREATE TABLE supplier (
    SupplierID INTEGER PRIMARY KEY,
    Name TEXT
)");

$today    = '2026-05-20';
$tomorrow = '2026-05-21';
$plus2    = '2026-05-22';
$minus1   = '2026-05-19';
$minus2   = '2026-05-18';
$win_start = '2026-03-01';   // overdue lookback floor (1 March, current year)
$mar1      = '2026-03-01';   // exactly on the floor — included
$preMar    = '2026-02-10';   // before the floor — excluded

$pdo->exec("INSERT INTO supplier VALUES
    (1, 'Hotel Alpha'),
    (2, 'Tour Beta'),
    (3, 'Cruise Gamma')
");

$pdo->exec("INSERT INTO payment VALUES
    /*  1  Alpha overdue (yesterday)                 -> OVERDUE  */
    ( 1, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 500.00, 0, '{$minus1}'),
    /*  2  Beta overdue (2 days ago)                 -> OVERDUE  */
    ( 2, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 300.00, 0, '{$minus2}'),
    /*  3  Alpha due today                           -> TODAY    */
    ( 3, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 700.00, 0, '{$today}'),
    /*  4  Alpha due tomorrow                        -> TOMORROW */
    ( 4, 1, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 200.00, 0, '{$tomorrow}'),
    /*  5  Beta due tomorrow                         -> TOMORROW */
    ( 5, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 400.00, 0, '{$tomorrow}'),
    /* 13  Beta overdue exactly on the floor (1 Mar) -> OVERDUE  */
    (13, 2, 'SUPPLIER PAYMENT (FULL)',       'P', 100.00, 0, '{$mar1}'),
    /* 14  Alpha overdue before the floor (Feb)      -> EXCLUDED */
    (14, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 999.00, 0, '{$preMar}'),

    /*  6  Out — beyond tomorrow (day after)                            */
    ( 6, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 999.00, 0, '{$plus2}'),
    /*  7  Out — already paid (Status=Y)                                */
    ( 7, 3, 'SUPPLIER PAYMENT (FULL)',       'Y', 999.00, 0, '{$today}'),
    /*  8  Out — deleted (Status=N)                                     */
    ( 8, 1, 'SUPPLIER PAYMENT (FULL)',       'N', 999.00, 0, '{$minus1}'),
    /*  9  Out — Debit=0 (no money moving out)                          */
    ( 9, 1, 'SUPPLIER PAYMENT (DEPOSIT)',    'P',   0.00, 0, '{$today}'),
    /* 10  Out — wrong Type (customer payment-in)                       */
    (10, 1, 'PAYMENT FROM CUSTOMER',         'P', 999.00, 0, '{$today}'),
    /* 11  Out — agent commission FROM supplier (money in, not a payout)*/
    (11, 3, 'AGENT COMMISSION FROM SUPPLIER','P',  50.00, 0, '{$today}'),
    /* 12  Out — orphan: no SupplierID                                  */
    (12, NULL,'SUPPLIER PAYMENT (DEPOSIT)',  'P', 999.00, 0, '{$minus1}')
");

// Shared row filters — every bucket and the table apply these.
$base = "
    payment.Status = 'P'
    AND payment.Debit > 0
    AND payment.Type LIKE 'SUPPLIER PAYMENT%'
    AND payment.SupplierID IS NOT NULL
";

// Three buckets, computed in one pass — mirrors the controller's CASE
// aggregation so the headline reconciles with the windowed table.
$bucketStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN payment.Deadline <  :today THEN 1 ELSE 0 END)                      AS overdue_cnt,
        COALESCE(SUM(CASE WHEN payment.Deadline <  :today THEN payment.Debit ELSE 0 END), 0)  AS overdue_due,
        SUM(CASE WHEN payment.Deadline =  :today THEN 1 ELSE 0 END)                      AS today_cnt,
        COALESCE(SUM(CASE WHEN payment.Deadline =  :today THEN payment.Debit ELSE 0 END), 0)  AS today_due,
        SUM(CASE WHEN payment.Deadline =  :tmr   THEN 1 ELSE 0 END)                      AS tomorrow_cnt,
        COALESCE(SUM(CASE WHEN payment.Deadline =  :tmr   THEN payment.Debit ELSE 0 END), 0)  AS tomorrow_due
    FROM payment
    WHERE {$base}
      AND payment.Deadline BETWEEN :start AND :tmr2
");
$bucketStmt->execute(array(':today' => $today, ':tmr' => $tomorrow, ':start' => $win_start, ':tmr2' => $tomorrow));
$b = $bucketStmt->fetch(PDO::FETCH_ASSOC);

// Per-supplier breakdown across the whole window (overdue..tomorrow),
// ordered by earliest deadline ASC so the most-overdue supplier leads.
$perStmt = $pdo->prepare("
    SELECT supplier.SupplierID AS sid, supplier.Name AS name,
           COUNT(*) AS due_count,
           COALESCE(SUM(payment.Debit), 0) AS due_total,
           MIN(payment.Deadline) AS earliest_deadline
    FROM payment
    JOIN supplier ON supplier.SupplierID = payment.SupplierID
    WHERE {$base}
      AND payment.Deadline BETWEEN :start AND :tmr
    GROUP BY supplier.SupplierID, supplier.Name
    ORDER BY MIN(payment.Deadline) ASC, due_total DESC
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

$by_sid = array();
foreach ($rows as $r) { $by_sid[(int) $r['sid']] = $r; }

// ---- Bucket headlines --------------------------------------------------
// Overdue spans 1 Mar..yesterday: Beta(mar1 100) + Beta(minus2 300) + Alpha(minus1 500).
// The Feb row (pre-floor) is excluded.
assert_eq('overdue count (mar1 + minus2 + minus1)',     3,      (int)   $b['overdue_cnt']);
assert_eq('overdue total (100+300+500)',                900.0,  (float) $b['overdue_due']);
assert_eq('today count (Alpha today)',                  1,      (int)   $b['today_cnt']);
assert_eq('today total (700)',                          700.0,  (float) $b['today_due']);
assert_eq('tomorrow count (Alpha + Beta tomorrow)',     2,      (int)   $b['tomorrow_cnt']);
assert_eq('tomorrow total (200+400)',                   600.0,  (float) $b['tomorrow_due']);

// ---- Per-supplier table (windowed) ------------------------------------
assert_eq('supplier rows shown',                 2,         count($rows));               // Alpha + Beta
assert_eq('Alpha due_count (minus1+today+tmr)',  3,         (int)   $by_sid[1]['due_count']);  // Feb row excluded
assert_eq('Alpha due_total (500+700+200)',       1400.0,    (float) $by_sid[1]['due_total']);
assert_eq('Alpha earliest_deadline',             $minus1,   (string)$by_sid[1]['earliest_deadline']);
assert_eq('Beta due_count (mar1+minus2+tmr)',    3,         (int)   $by_sid[2]['due_count']);
assert_eq('Beta due_total (100+300+400)',        800.0,     (float) $by_sid[2]['due_total']);
assert_eq('Beta earliest_deadline (1 Mar)',      $mar1,     (string)$by_sid[2]['earliest_deadline']);

// Earliest-deadline ASC — Beta (1 Mar) is the most overdue, so it leads.
assert_eq('row order by earliest deadline asc',  2,         (int)   $rows[0]['sid']);
assert_eq('second row',                          1,         (int)   $rows[1]['sid']);

// Gamma had only excluded rows (paid / commission), so it must not surface.
assert_eq('Gamma absent (only excluded rows)',   false,     isset($by_sid[3]));

// ---- Invariants --------------------------------------------------------
// Buckets partition the windowed set: counts and totals add up.
$bucket_cnt   = (int)$b['overdue_cnt'] + (int)$b['today_cnt'] + (int)$b['tomorrow_cnt'];
$bucket_total = (float)$b['overdue_due'] + (float)$b['today_due'] + (float)$b['tomorrow_due'];
assert_eq('buckets partition: total count',  6,        $bucket_cnt);
assert_eq('buckets partition: total amount', 2200.0,   $bucket_total);

// Per-supplier sums reconcile with the windowed grand total.
$sum = 0.0;
foreach ($rows as $r) { $sum += (float) $r['due_total']; }
assert_eq('partition: per-supplier sums to windowed total', $bucket_total, $sum);

echo "\nAll assertions passed.\n";
