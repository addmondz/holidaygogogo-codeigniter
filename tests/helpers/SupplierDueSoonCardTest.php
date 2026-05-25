<?php
/**
 * Run with: php tests/helpers/SupplierDueSoonCardTest.php
 *
 * Locks the SQL behind the OP "Supplier Pay-out Due Soon" summary card.
 * Forward-looking mirror of the Finance "Supplier Overdue" card — same
 * filters, but the Deadline predicate looks 1-3 days ahead so the two
 * cards stay disjoint (today and earlier belong to Supplier Overdue).
 *
 * A supplier payout row counts toward the due-soon tally when:
 *
 *   - payment.Status = 'P'                          (pending — not yet paid)
 *   - payment.Deadline BETWEEN today+1 AND today+3  (next 3 days)
 *   - payment.Debit > 0                             (money out, not money in)
 *   - payment.Type LIKE 'SUPPLIER PAYMENT%'         (deposit/full/additional)
 *   - supplier.SupplierID linked                    (orphans excluded)
 *
 * Invariant: grand total of due-soon Debit equals SUM across per-supplier
 * rows so the headline reconciles with the breakdown table.
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
$plus1    = '2026-05-21';
$plus2    = '2026-05-22';
$plus3    = '2026-05-23';
$plus4    = '2026-05-24';
$minus1   = '2026-05-19';

$pdo->exec("INSERT INTO supplier VALUES
    (1, 'Hotel Alpha'),
    (2, 'Tour Beta'),
    (3, 'Cruise Gamma')
");

$pdo->exec("INSERT INTO payment VALUES
    /*  1  Alpha deposit due tomorrow                                 -> COUNT */
    ( 1, 1, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 500.00, 0, '{$plus1}'),
    /*  2  Alpha full due in 3 days                                   -> COUNT */
    ( 2, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 700.00, 0, '{$plus3}'),
    /*  3  Beta deposit due in 2 days                                 -> COUNT */
    ( 3, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 200.00, 0, '{$plus2}'),

    /*  4  Out — due today (belongs to Supplier Overdue once date rolls) */
    ( 4, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 999.00, 0, '{$today}'),
    /*  5  Out — past due (Supplier Overdue card)                       */
    ( 5, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 999.00, 0, '{$minus1}'),
    /*  6  Out — beyond 3-day window                                    */
    ( 6, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 999.00, 0, '{$plus4}'),
    /*  7  Out — already paid (Status=Y)                                */
    ( 7, 3, 'SUPPLIER PAYMENT (FULL)',       'Y', 999.00, 0, '{$plus2}'),
    /*  8  Out — deleted (Status=N)                                     */
    ( 8, 1, 'SUPPLIER PAYMENT (FULL)',       'N', 999.00, 0, '{$plus2}'),
    /*  9  Out — Debit=0 (no money moving out)                          */
    ( 9, 1, 'SUPPLIER PAYMENT (DEPOSIT)',    'P',   0.00, 0, '{$plus2}'),
    /* 10  Out — wrong Type (customer payment-in)                       */
    (10, 1, 'PAYMENT FROM CUSTOMER',         'P', 999.00, 0, '{$plus2}'),
    /* 11  Out — agent commission FROM supplier (money in, not a payout)*/
    (11, 3, 'AGENT COMMISSION FROM SUPPLIER','P',  50.00, 0, '{$plus2}'),
    /* 12  Out — orphan: no SupplierID                                  */
    (12, NULL,'SUPPLIER PAYMENT (DEPOSIT)',  'P', 999.00, 0, '{$plus2}')
");

$where = "
    payment.Status = 'P'
    AND payment.Deadline BETWEEN :s AND :e
    AND payment.Debit > 0
    AND payment.Type LIKE 'SUPPLIER PAYMENT%'
    AND payment.SupplierID IS NOT NULL
";

// Per-supplier breakdown — ordered by earliest deadline ASC so the
// most-urgent supplier surfaces at the top of the OP card.
$perStmt = $pdo->prepare("
    SELECT supplier.SupplierID AS sid, supplier.Name AS name,
           COUNT(*) AS due_count,
           COALESCE(SUM(payment.Debit), 0) AS due_total,
           MIN(payment.Deadline) AS earliest_deadline
    FROM payment
    JOIN supplier ON supplier.SupplierID = payment.SupplierID
    WHERE {$where}
    GROUP BY supplier.SupplierID, supplier.Name
    ORDER BY MIN(payment.Deadline) ASC, due_total DESC
");
$perStmt->execute(array(':s' => $plus1, ':e' => $plus3));
$rows = $perStmt->fetchAll(PDO::FETCH_ASSOC);

// Grand total — what the headline card shows.
$totStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_count, COALESCE(SUM(payment.Debit), 0) AS total_due
    FROM payment
    WHERE {$where}
");
$totStmt->execute(array(':s' => $plus1, ':e' => $plus3));
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

$by_sid = array();
foreach ($rows as $r) { $by_sid[(int) $r['sid']] = $r; }

assert_eq('supplier rows shown',                 2,         count($rows));                 // Alpha + Beta
assert_eq('grand total_count',                   3,         (int)   $tot['total_count']);  // 1,2,3
assert_eq('grand total_due (500+700+200)',       1400.0,    (float) $tot['total_due']);

assert_eq('Alpha due_count',                     2,         (int)   $by_sid[1]['due_count']);
assert_eq('Alpha due_total (500+700)',           1200.0,    (float) $by_sid[1]['due_total']);
assert_eq('Alpha earliest_deadline',             $plus1,    (string)$by_sid[1]['earliest_deadline']);

assert_eq('Beta due_count',                      1,         (int)   $by_sid[2]['due_count']);
assert_eq('Beta due_total',                      200.0,     (float) $by_sid[2]['due_total']);
assert_eq('Beta earliest_deadline',              $plus2,    (string)$by_sid[2]['earliest_deadline']);

// Earliest-deadline ASC ordering — Alpha (plus1) must appear before Beta (plus2).
assert_eq('row order by earliest deadline asc',  1,         (int)   $rows[0]['sid']);
assert_eq('second row',                          2,         (int)   $rows[1]['sid']);

// Gamma had only an excluded payment (already paid), so it must not surface.
assert_eq('Gamma absent (only excluded rows)',   false,     isset($by_sid[3]));

// Reconciliation invariant: per-supplier sums to grand total.
$sum = 0.0;
foreach ($rows as $r) { $sum += (float) $r['due_total']; }
assert_eq('partition: per-supplier sums to grand total', (float) $tot['total_due'], $sum);

echo "\nAll assertions passed.\n";
