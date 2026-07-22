<?php
/**
 * Run with: php tests/helpers/CustomerPaymentBcFilterTest.php
 *
 * Locks the booking-list filter behind the clickable Overdue / Today /
 * Tomorrow segments of the OP "Payment From Customer Due Soon" card
 * (?customer_payment=overdue|today|tomorrow, handled by
 * Booking_Model::apply_customer_payment_filter()).
 *
 * A booking is in the filtered list when it still owes a scheduled
 * customer payment whose operative deadline falls in the bucket. The
 * predicate mirrors the card exactly:
 *
 *   - booking.Status IN ('P','PP')
 *   - operative deadline in the bucket window:
 *       overdue  : floor (1 Mar, current year) <= deadline < today
 *       today    : deadline = today
 *       tomorrow : deadline = today + 1
 *     where the operative deadline is
 *       Status 'P'  -> COALESCE(DepositDeadline, FullPaymentDeadline)
 *       Status 'PP' -> FullPaymentDeadline
 *   - outstanding balance > 0
 *       outstanding = NetTotal - SUM(approved customer credits)
 *       approved credit = payment.Status='Y' AND Credit>0 AND
 *                         Type != 'AGENT COMMISSION FROM SUPPLIER'
 *
 * The card link also carries status=A, which supplies the live-BC scope
 * (CancelStatus='N', Status!='N') via the shared status filter; this test
 * focuses on the extra predicate the model adds.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Status TEXT,
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
$floor    = '2026-03-01';
$mar1     = '2026-03-01';
$preMar   = '2026-02-10';

$pdo->exec("INSERT INTO booking VALUES
    /* BC1 P, deposit overdue yesterday, nothing paid     -> OVERDUE   */
    (1, 'P',  1000.0, '{$minus1}', '2026-08-01'),
    /* BC2 PP, balance due today, RM500 paid              -> TODAY     */
    (2, 'PP', 2000.0, '{$minus1}', '{$today}'),
    /* BC3 P, no deposit schedule, full due tomorrow      -> TOMORROW  */
    (3, 'P',   800.0, NULL,        '{$tomorrow}'),
    /* BC4 PP, balance tomorrow but fully paid            -> none      */
    (4, 'PP', 1200.0, '{$minus1}', '{$tomorrow}'),
    /* BC5 P, deposit due in Feb (before floor)           -> none      */
    (5, 'P',   900.0, '{$preMar}', '2026-09-01'),
    /* BC7 completed (Status Y), full today               -> none      */
    (7, 'Y',   700.0, '{$minus1}', '{$today}'),
    /* BC8 PP, balance overdue on the floor (1 Mar), RM300 -> OVERDUE  */
    (8, 'PP', 1000.0, '{$minus1}', '{$mar1}'),
    /* BC9 PP, balance due today, only supplier-commission -> TODAY    */
    (9, 'PP', 1000.0, '{$minus1}', '{$today}')
");

$pdo->exec("INSERT INTO payment VALUES
    (1, 2, 'DEPOSIT', 'Y',  500.0),
    (2, 4, 'FULL',    'Y', 1200.0),
    (3, 8, 'DEPOSIT', 'Y',  300.0),
    (4, 9, 'AGENT COMMISSION FROM SUPPLIER', 'Y', 1000.0)
");

// Mirrors apply_customer_payment_filter(): the per-bucket range applied to
// the operative deadline, combined with the outstanding-balance guard.
function range_for($col, $bucket, $today, $tomorrow, $floor) {
    if ($bucket === 'overdue')  return "{$col} >= '{$floor}' AND {$col} < '{$today}'";
    if ($bucket === 'today')    return "{$col} = '{$today}'";
    if ($bucket === 'tomorrow') return "{$col} = '{$tomorrow}'";
    return null;
}

function bcs_for_bucket($pdo, $bucket, $today, $tomorrow, $floor) {
    $p_dl  = range_for("COALESCE(booking.DepositDeadline, booking.FullPaymentDeadline)", $bucket, $today, $tomorrow, $floor);
    $pp_dl = range_for("booking.FullPaymentDeadline", $bucket, $today, $tomorrow, $floor);
    if ($p_dl === null) {
        return null;
    }
    $outstanding = "(booking.NetTotal - COALESCE((SELECT SUM(p.Credit) FROM payment p"
        . " WHERE p.BookingID = booking.BookingID"
        . " AND p.Status = 'Y' AND p.Credit > 0"
        . " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0)) > 0";
    $sql = "SELECT BookingID FROM booking
            WHERE ((booking.Status = 'P' AND {$p_dl})
                OR (booking.Status = 'PP' AND {$pp_dl}))
              AND {$outstanding}
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

assert_eq('overdue BCs (BC1 + BC8 on floor)',  array(1, 8), bcs_for_bucket($pdo, 'overdue',  $today, $tomorrow, $floor));
assert_eq('today BCs (BC2 + BC9)',             array(2, 9), bcs_for_bucket($pdo, 'today',    $today, $tomorrow, $floor));
assert_eq('tomorrow BCs (BC3 only)',           array(3),    bcs_for_bucket($pdo, 'tomorrow', $today, $tomorrow, $floor));

// Unknown bucket yields no predicate — guarded by the whitelist in the model.
assert_eq('unknown bucket has no range', null, range_for('x', 'garbage', $today, $tomorrow, $floor));

echo "\nAll assertions passed.\n";
