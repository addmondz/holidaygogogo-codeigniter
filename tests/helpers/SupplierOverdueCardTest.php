<?php
/**
 * Run with: php tests/helpers/SupplierOverdueCardTest.php
 *
 * Locks the SQL behind the Finance "Supplier Overdue" summary card. A
 * supplier payout row counts toward the overdue tally when:
 *
 *   - payment.Status = 'P'       (pending — not yet paid)
 *   - payment.Deadline < today   (past due)
 *   - payment.Debit > 0          (money out, not money in)
 *   - payment.Type LIKE 'SUPPLIER PAYMENT%' (deposit/full/additional)
 *   - supplier.SupplierID linked (orphans excluded)
 *
 * Card surfaces (count_overdue, total_due) per supplier and the grand total.
 * Invariant: grand total of overdue Debit equals SUM across per-supplier
 * rows, so the headline number on the card always reconciles with the
 * popover/drill-down breakdown.
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

$today = '2026-05-20';

$pdo->exec("INSERT INTO supplier VALUES
    (1, 'Hotel Alpha'),
    (2, 'Tour Beta'),
    (3, 'Cruise Gamma')
");

$pdo->exec("INSERT INTO payment VALUES
    /* 1  Alpha deposit overdue                                     -> COUNT */
    ( 1, 1, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 500.00, 0, '2026-05-10'),
    /* 2  Alpha full overdue                                        -> COUNT */
    ( 2, 1, 'SUPPLIER PAYMENT (FULL)',       'P', 700.00, 0, '2026-05-05'),
    /* 3  Beta deposit overdue                                      -> COUNT */
    ( 3, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 200.00, 0, '2026-05-19'),
    /* 4  Beta deposit not yet due                                  -> skip  */
    ( 4, 2, 'SUPPLIER PAYMENT (DEPOSIT)',    'P', 999.00, 0, '2026-06-15'),
    /* 5  Gamma overdue but already paid (Status=Y)                 -> skip  */
    ( 5, 3, 'SUPPLIER PAYMENT (FULL)',       'Y', 100.00, 0, '2026-05-01'),
    /* 6  customer payment-in (Credit only)                         -> skip  */
    ( 6, 1, 'PAYMENT FROM CUSTOMER',         'P', 0,  300.00, '2026-05-01'),
    /* 7  agent commission from supplier (not a payout)             -> skip  */
    ( 7, 3, 'AGENT COMMISSION FROM SUPPLIER','P', 50.00, 0, '2026-05-01'),
    /* 8  orphan: no SupplierID                                     -> skip  */
    ( 8, NULL,'SUPPLIER PAYMENT (DEPOSIT)',  'P', 150.00, 0, '2026-05-10'),
    /* 9  additional payment to Alpha, overdue                      -> COUNT */
    ( 9, 1, 'SUPPLIER PAYMENT (ADDITIONAL)', 'P', 250.00, 0, '2026-05-15'),
    /* 10 Status=N (deleted)                                        -> skip  */
    (10, 1, 'SUPPLIER PAYMENT (FULL)',       'N', 999.00, 0, '2026-05-01')
");

$where = "
    payment.Status = 'P'
    AND payment.Deadline < :t
    AND payment.Debit > 0
    AND payment.Type LIKE 'SUPPLIER PAYMENT%'
    AND payment.SupplierID IS NOT NULL
";

// Per-supplier breakdown query.
$perStmt = $pdo->prepare("
    SELECT supplier.SupplierID AS sid, supplier.Name AS name,
           COUNT(*) AS overdue_count,
           COALESCE(SUM(payment.Debit), 0) AS overdue_total
    FROM payment
    JOIN supplier ON supplier.SupplierID = payment.SupplierID
    WHERE {$where}
    GROUP BY supplier.SupplierID, supplier.Name
    ORDER BY overdue_total DESC
");
$perStmt->execute(array(':t' => $today));
$rows = $perStmt->fetchAll(PDO::FETCH_ASSOC);

// Grand total query (what the headline card shows).
$totStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_count, COALESCE(SUM(payment.Debit), 0) AS total_due
    FROM payment
    WHERE {$where}
");
$totStmt->execute(array(':t' => $today));
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

assert_eq('supplier rows shown',           2,       count($rows));               // Alpha + Beta
assert_eq('grand total_count',             4,       (int)   $tot['total_count']); // 1,2,3,9
assert_eq('grand total_due (500+700+200+250)', 1650.0, (float) $tot['total_due']);

assert_eq('Alpha overdue_count',           3,       (int)   $by_sid[1]['overdue_count']);
assert_eq('Alpha overdue_total (500+700+250)', 1450.0, (float) $by_sid[1]['overdue_total']);
assert_eq('Beta overdue_count',            1,       (int)   $by_sid[2]['overdue_count']);
assert_eq('Beta overdue_total',            200.0,   (float) $by_sid[2]['overdue_total']);

$sum = 0.0;
foreach ($rows as $r) { $sum += (float) $r['overdue_total']; }
assert_eq('partition: per-supplier sums to grand total', (float) $tot['total_due'], $sum);

echo "\nAll assertions passed.\n";
