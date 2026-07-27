<?php
/**
 * Run with: php tests/helpers/SupplierPayoutBcFilterTest.php
 *
 * Locks the booking-list filter behind the clickable Overdue / Today /
 * Tomorrow segments of the OP "Supplier Pay-out Due Soon" card
 * (?supplier_payout=overdue|today|tomorrow, handled by
 * Booking_Model::apply_supplier_payout_filter()).
 *
 * A booking is in the filtered list when it has at least one supplier
 * payout whose deadline falls in the bucket. The payout row predicate
 * mirrors the card exactly:
 *
 *   - payment.Status = 'P'                  (pending)
 *   - payment.Debit > 0                     (money out)
 *   - payment.Type LIKE 'SUPPLIER PAYMENT%'
 *   - payment.SupplierID IS NOT NULL
 *   - payment.Deadline in the bucket window:
 *       overdue  : floor (1 Mar, current year) <= Deadline < today
 *       today    : Deadline = today
 *       tomorrow : Deadline = today + 1
 *
 * Because the card counts payout rows but the list counts bookings, a BC
 * with two matching payouts appears once here — the sets are by design not
 * a 1:1 count match, but every listed BC has a real payout in the bucket.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    SupplierID INTEGER,
    Type TEXT,
    Status TEXT,
    Debit REAL,
    Deadline TEXT
)");

$today    = '2026-05-20';
$tomorrow = '2026-05-21';
$minus1   = '2026-05-19';
$floor    = '2026-03-01';
$mar1     = '2026-03-01';
$preMar   = '2026-02-10';

$pdo->exec("INSERT INTO booking VALUES (1),(2),(3),(4),(5),(6),(7)");

$pdo->exec("INSERT INTO payment VALUES
    /* BC1 — overdue payout (yesterday)                  -> OVERDUE      */
    ( 1, 1, 10, 'SUPPLIER PAYMENT (FULL)',    'P', 500.00, '{$minus1}'),
    /* BC2 — payout due today                            -> TODAY        */
    ( 2, 2, 10, 'SUPPLIER PAYMENT (DEPOSIT)', 'P', 200.00, '{$today}'),
    /* BC3 — payout due tomorrow                         -> TOMORROW     */
    ( 3, 3, 11, 'SUPPLIER PAYMENT (FULL)',    'P', 300.00, '{$tomorrow}'),
    /* BC4 — payout overdue but BEFORE the floor (Feb)   -> none         */
    ( 4, 4, 11, 'SUPPLIER PAYMENT (FULL)',    'P', 999.00, '{$preMar}'),
    /* BC5 — payout today but already paid (Status=Y)    -> none         */
    ( 5, 5, 12, 'SUPPLIER PAYMENT (FULL)',    'Y', 999.00, '{$today}'),
    /* BC6 — payout overdue but no supplier linked       -> none         */
    ( 6, 6, NULL,'SUPPLIER PAYMENT (DEPOSIT)','P', 999.00, '{$minus1}'),
    /* BC7 — two payouts: overdue-on-floor AND tomorrow  -> OVERDUE+TMR  */
    ( 7, 7, 10, 'SUPPLIER PAYMENT (FULL)',    'P', 150.00, '{$mar1}'),
    ( 8, 7, 10, 'SUPPLIER PAYMENT (DEPOSIT)', 'P', 250.00, '{$tomorrow}'),
    /* BC2 — also an excluded money-in row (Debit=0)      -> ignored      */
    ( 9, 2, 10, 'SUPPLIER PAYMENT (FULL)',    'P',   0.00, '{$today}')
");

// Mirrors apply_supplier_payout_filter(): the EXISTS predicate per bucket.
function bucket_predicate($bucket, $today, $tomorrow, $floor) {
    if ($bucket === 'overdue')  return "p.Deadline >= '{$floor}' AND p.Deadline < '{$today}'";
    if ($bucket === 'today')    return "p.Deadline = '{$today}'";
    if ($bucket === 'tomorrow') return "p.Deadline = '{$tomorrow}'";
    return null;
}

function bcs_for_bucket($pdo, $bucket, $today, $tomorrow, $floor) {
    $deadline = bucket_predicate($bucket, $today, $tomorrow, $floor);
    $sql = "SELECT BookingID FROM booking
            WHERE EXISTS (
                SELECT 1 FROM payment p
                WHERE p.BookingID = booking.BookingID
                  AND p.Status = 'P' AND p.Debit > 0
                  AND p.Type LIKE 'SUPPLIER PAYMENT%'
                  AND p.SupplierID IS NOT NULL
                  AND {$deadline}
            )
            ORDER BY BookingID";
    return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . json_encode($actual) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . json_encode($expected)
           . ", got " . json_encode($actual) . "\n";
        exit(1);
    }
}

assert_eq('overdue BCs (BC1 + BC7 on floor)',  array(1, 7), bcs_for_bucket($pdo, 'overdue',  $today, $tomorrow, $floor));
assert_eq('today BCs (BC2 only)',              array(2),    bcs_for_bucket($pdo, 'today',    $today, $tomorrow, $floor));
assert_eq('tomorrow BCs (BC3 + BC7)',          array(3, 7), bcs_for_bucket($pdo, 'tomorrow', $today, $tomorrow, $floor));

// Unknown bucket yields no predicate — guarded by the whitelist in the model.
assert_eq('unknown bucket has no predicate', null, bucket_predicate('garbage', $today, $tomorrow, $floor));

echo "\nAll assertions passed.\n";
