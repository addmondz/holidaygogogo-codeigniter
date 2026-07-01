<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceOutstandingSqlTest.php
 *
 * Locks the SQL that derives PaidAmount / BalanceDue for the
 * booking_supplier_invoice feature. Mirrors the shape used by
 * Booking_Supplier_Invoice_Model::Read_By_Booking() (the Paid / Balance columns
 * on the booking form) by plugging the helper's correlated subquery into a
 * SQLite :memory: copy of the booking / supplier / payment / invoice schema.
 *
 * Scenario driven by the user request: Supplier A has Invoice A (RM1000, paid
 * RM100) and Invoice B (RM2000, paid RM100). Total outstanding to Supplier A
 * must be RM2800, even when the table also contains
 *   - a fully-paid invoice (must drop out of the outstanding view)
 *   - a soft-deleted invoice (Status='N')
 *   - a Pending payment (Status='P') that should NOT reduce the balance
 *   - a payment with the wrong Type (CUSTOMER REFUND) on a matching invoice
 *     number / supplier — must NOT reduce the balance.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/supplier_invoice_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE supplier (
    SupplierID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    BookingNumber TEXT
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    SupplierID INTEGER,
    InvoiceNumber TEXT,
    Type TEXT,
    Debit REAL,
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_supplier_invoice (
    SupplierInvoiceID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    SupplierID INTEGER,
    InvoiceNumber TEXT,
    InvoiceAmount REAL,
    PaymentDeadline TEXT,
    Remark TEXT,
    Status TEXT
)");

$pdo->exec("INSERT INTO supplier VALUES
    (1, 'Supplier A'),
    (2, 'Supplier B')
");
$pdo->exec("INSERT INTO booking VALUES
    (501, 'BK-2603-0001'),
    (502, 'BK-2603-0002'),
    (503, 'BK-2603-0003')
");

// Invoices --------------------------------------------------------------
// 101: Supplier A, RM1000, deadline 2026-03-10  (user's Invoice A)
// 102: Supplier A, RM2000, deadline 2026-03-20  (user's Invoice B)
// 103: Supplier A, RM500 fully paid              (must not appear in outstanding)
// 104: Supplier B, RM800 unpaid                  (separate-supplier control)
// 105: Supplier A, RM999 soft-deleted Status='N' (must not appear)
$pdo->exec("INSERT INTO booking_supplier_invoice VALUES
    (101, 501, 1, 'INV-A',     1000, '2026-03-10', NULL, 'Y'),
    (102, 502, 1, 'INV-B',     2000, '2026-03-20', NULL, 'Y'),
    (103, 503, 1, 'INV-PAID',   500, '2026-03-15', NULL, 'Y'),
    (104, 501, 2, 'INV-B-1',    800, '2026-03-25', NULL, 'Y'),
    (105, 501, 1, 'INV-DEL',    999, '2026-03-25', NULL, 'N')
");

// Payments --------------------------------------------------------------
// Each invoice's paid amount is SUM(Debit) over matching (SupplierID, InvoiceNumber)
// with Status='Y' and Type IN supplier-payment-types.
$pdo->exec("INSERT INTO payment VALUES
    -- INV-A: RM100 paid (deposit, approved)
    (1, 1, 'INV-A',     'SUPPLIER PAYMENT (DEPOSIT)',    100, 'Y'),
    -- INV-B: RM100 paid (additional, approved)
    (2, 1, 'INV-B',     'SUPPLIER PAYMENT (ADDITIONAL)', 100, 'Y'),
    -- INV-PAID: RM500 paid in full
    (3, 1, 'INV-PAID',  'SUPPLIER PAYMENT (FULL)',       500, 'Y'),
    -- Pending payment against INV-A: MUST NOT reduce balance
    (4, 1, 'INV-A',     'SUPPLIER PAYMENT (ADDITIONAL)', 200, 'P'),
    -- Wrong Type on matching invoice/supplier: MUST NOT reduce balance
    (5, 1, 'INV-B',     'CUSTOMER REFUND',                50, 'Y'),
    -- Cancelled payment against INV-B: MUST NOT reduce balance
    (6, 1, 'INV-B',     'SUPPLIER PAYMENT (FULL)',       300, 'N'),
    -- Approved payment against soft-deleted invoice: never seen
    (7, 1, 'INV-DEL',   'SUPPLIER PAYMENT (FULL)',       999, 'Y')
");

$paid = supplier_invoice_paid_subquery_sql();

// --- 1) Per-invoice paid / balance (Read_By_Booking Paid/Balance shape) ------

$lines_sql = "SELECT
    bsi.SupplierInvoiceID,
    bsi.InvoiceAmount,
    COALESCE(({$paid}), 0) AS PaidAmount,
    (bsi.InvoiceAmount - COALESCE(({$paid}), 0)) AS BalanceDue
FROM booking_supplier_invoice bsi
WHERE bsi.Status = 'Y'
ORDER BY bsi.SupplierInvoiceID";

$rows = [];
foreach ($pdo->query($lines_sql) as $r) {
    $rows[(int) $r['SupplierInvoiceID']] = [
        'paid'    => (float) $r['PaidAmount'],
        'balance' => (float) $r['BalanceDue'],
    ];
}

$assertions = [];

// User example: Invoice A — RM1000 - RM100 = RM900 balance
$assertions['INV-A paid = 100']     = isset($rows[101]) && abs($rows[101]['paid']    - 100) < 0.001;
$assertions['INV-A balance = 900']  = isset($rows[101]) && abs($rows[101]['balance'] - 900) < 0.001;

// User example: Invoice B — RM2000 - RM100 = RM1900 balance
// Verifies pending/cancelled/wrong-type payments are ignored even when they
// have the same SupplierID + InvoiceNumber.
$assertions['INV-B paid = 100']     = isset($rows[102]) && abs($rows[102]['paid']    - 100) < 0.001;
$assertions['INV-B balance = 1900'] = isset($rows[102]) && abs($rows[102]['balance'] - 1900) < 0.001;

// Fully-paid invoice — balance must be 0 (but still in this "all active" query)
$assertions['INV-PAID paid = 500']    = isset($rows[103]) && abs($rows[103]['paid']    - 500) < 0.001;
$assertions['INV-PAID balance = 0']   = isset($rows[103]) && abs($rows[103]['balance'])      < 0.001;

// Different supplier control
$assertions['INV-B-1 (Supplier B) paid = 0']    = isset($rows[104]) && abs($rows[104]['paid'])        < 0.001;
$assertions['INV-B-1 (Supplier B) balance=800'] = isset($rows[104]) && abs($rows[104]['balance'] - 800) < 0.001;

// Soft-deleted invoice must not appear
$assertions['Soft-deleted INV-DEL excluded from active set'] = !isset($rows[105]);

// --- 2) Outstanding-only view: only rows with BalanceDue > 0 -----------------

$outstanding_lines_sql = "SELECT
    bsi.SupplierInvoiceID
FROM booking_supplier_invoice bsi
WHERE bsi.Status = 'Y'
  AND (bsi.InvoiceAmount - COALESCE(({$paid}), 0)) > 0
ORDER BY bsi.SupplierInvoiceID";
$outstanding_ids = [];
foreach ($pdo->query($outstanding_lines_sql) as $r) {
    $outstanding_ids[] = (int) $r['SupplierInvoiceID'];
}
$assertions['Outstanding lines = [101, 102, 104]'] = ($outstanding_ids === [101, 102, 104]);

// --- 3) Per-supplier roll-up of outstanding balances ------------------------
// Supplier A outstanding = 900 + 1900 = 2800 (user's expected total)
// Supplier B outstanding = 800
$summary_sql = "SELECT
    bsi.SupplierID,
    s.Name AS SupplierName,
    SUM(bsi.InvoiceAmount - COALESCE(({$paid}), 0)) AS OutstandingTotal,
    COUNT(*) AS InvoiceCount
FROM booking_supplier_invoice bsi
LEFT JOIN supplier s ON s.SupplierID = bsi.SupplierID
WHERE bsi.Status = 'Y'
  AND (bsi.InvoiceAmount - COALESCE(({$paid}), 0)) > 0
GROUP BY bsi.SupplierID
ORDER BY s.Name";
$summary = [];
foreach ($pdo->query($summary_sql) as $r) {
    $summary[$r['SupplierName']] = [
        'total' => (float) $r['OutstandingTotal'],
        'count' => (int) $r['InvoiceCount'],
    ];
}
$assertions['Supplier A outstanding = 2800 (user example)'] =
    isset($summary['Supplier A']) && abs($summary['Supplier A']['total'] - 2800) < 0.001;
$assertions['Supplier A invoice count = 2 (fully-paid excluded)'] =
    isset($summary['Supplier A']) && $summary['Supplier A']['count'] === 2;
$assertions['Supplier B outstanding = 800'] =
    isset($summary['Supplier B']) && abs($summary['Supplier B']['total'] - 800) < 0.001;
$assertions['No row for fully-paid-only supplier'] = !isset($summary['Supplier C']);

// --- 4) supplier_id filter (drill-down link from summary)
$filtered_sql = "SELECT bsi.SupplierInvoiceID
FROM booking_supplier_invoice bsi
WHERE bsi.Status = 'Y'
  AND (bsi.InvoiceAmount - COALESCE(({$paid}), 0)) > 0
  AND bsi.SupplierID = 1
ORDER BY bsi.SupplierInvoiceID";
$filtered = [];
foreach ($pdo->query($filtered_sql) as $r) {
    $filtered[] = (int) $r['SupplierInvoiceID'];
}
$assertions['Supplier=1 filter drills to [101, 102]'] = ($filtered === [101, 102]);

// --- Report ------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
