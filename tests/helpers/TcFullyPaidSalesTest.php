<?php
/**
 * Run with: php tests/helpers/TcFullyPaidSalesTest.php
 *
 * Locks the new "Total Sales (Month)" filter on the TC summary card:
 * a booking only contributes to the total when the SUM of its approved
 * customer payments is >= NetTotal. Partial / deposit-only / unpaid BCs
 * are excluded. Agent-commission payment rows must NOT be counted as
 * customer payment toward the threshold.
 *
 * Mirrors the approved-credits pattern in Booking_Model line ~302
 * (Status='Y', Credit > 0, Type != 'AGENT COMMISSION FROM SUPPLIER').
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    Credit REAL,
    Status TEXT,
    Type TEXT
)");

// All bookings credited to admin 10 under TC1 (InsertDate < 2026-06-01).
// NetTotal = 1000 for all unless noted.
$pdo->exec("INSERT INTO booking VALUES
    (1, 10, 99, '2026-05-03', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),   /* unpaid             -> EXCLUDED */
    (2, 10, 99, '2026-05-04', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),   /* deposit 500 only   -> EXCLUDED */
    (3, 10, 99, '2026-05-05', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),   /* fully paid 1000    -> COUNTED  */
    (4, 10, 99, '2026-05-06', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),   /* split 600+400=1000 -> COUNTED  */
    (5, 10, 99, '2026-05-07', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),   /* paid but pending   -> EXCLUDED */
    (6, 10, 99, '2026-05-08', 1000, 'BOOKING CONFIRMATION', 'Y', 'P'),   /* cancelled fully paid -> EXCLUDED */
    (7, 10, 99, '2026-05-09', 1000, 'BOOKING CONFIRMATION', 'N', 'N'),   /* draft fully paid   -> EXCLUDED */
    (8, 10, 99, '2026-05-10', 1000, 'QUOTATION',            'N', 'P'),   /* quotation fully paid -> EXCLUDED */
    (9, 10, 99, '2026-05-11',    0, 'BOOKING CONFIRMATION', 'N', 'P'),   /* NetTotal=0         -> EXCLUDED */
    (10, 99, 10, '2026-05-12', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),  /* not TC1's row      -> EXCLUDED */
    (11, 10, 99, '2026-05-13', 1000, 'BOOKING CONFIRMATION', 'N', 'P')   /* covered only by AGENT COMMISSION -> EXCLUDED */
");

$pdo->exec("INSERT INTO payment VALUES
    (1, 2,  500, 'Y', 'CUSTOMER PAYMENT'),                  /* deposit only */
    (2, 3, 1000, 'Y', 'CUSTOMER PAYMENT'),                  /* fully paid */
    (3, 4,  600, 'Y', 'CUSTOMER PAYMENT'),                  /* part 1 */
    (4, 4,  400, 'Y', 'CUSTOMER PAYMENT'),                  /* part 2 -> total 1000 */
    (5, 5, 1000, 'P', 'CUSTOMER PAYMENT'),                  /* pending approval */
    (6, 6, 1000, 'Y', 'CUSTOMER PAYMENT'),                  /* cancelled BC */
    (7, 7, 1000, 'Y', 'CUSTOMER PAYMENT'),                  /* draft BC */
    (8, 8, 1000, 'Y', 'CUSTOMER PAYMENT'),                  /* quotation */
    (9, 11, 1000, 'Y', 'AGENT COMMISSION FROM SUPPLIER')    /* commission, NOT customer payment */
");

$credit_clause = lead_conversion_credit_booking_clause();
$admin_id      = 10;
$month_start   = '2026-05-01';
$month_end     = '2026-05-31';

$sql = "
    SELECT COALESCE(SUM(booking.NetTotal),0) AS total,
           COUNT(*) AS bc_count
    FROM booking
    WHERE {$credit_clause}
      AND booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND booking.CancelStatus='N' AND booking.Status!='N'
      AND booking.InsertDate BETWEEN ? AND ?
      AND booking.NetTotal > 0
      AND COALESCE((
            SELECT SUM(p.Credit) FROM payment p
            WHERE p.BookingID = booking.BookingID
              AND p.Status = 'Y' AND p.Credit > 0
              AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')
          ), 0) >= booking.NetTotal
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array($admin_id, $admin_id, $month_start, $month_end));
$row = $stmt->fetch(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Bookings 3 and 4 are the only fully-paid customer-payment BCs for TC1.
assert_eq('fully-paid BC count',  2,     (int)$row['bc_count']);
assert_eq('fully-paid sales sum', 2000.0, (float)$row['total']);

// ---- Boundary: change month to June 2026 (post-cutoff), nothing matches.
$stmt = $pdo->prepare($sql);
$stmt->execute(array($admin_id, $admin_id, '2026-06-01', '2026-06-30'));
$row_jun = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('June 2026 has no rows', 0, (int)$row_jun['bc_count']);

// ---- Boundary: TC2 (admin 99) credited for BCs after cutoff date 2026-06-01.
// Add one fully-paid BC dated 2026-06-15 with SalesAgent2 = 99.
$pdo->exec("INSERT INTO booking VALUES
    (12, 10, 99, '2026-06-15', 500, 'BOOKING CONFIRMATION', 'N', 'P')
");
$pdo->exec("INSERT INTO payment VALUES (10, 12, 500, 'Y', 'CUSTOMER PAYMENT')");
$stmt = $pdo->prepare($sql);
$stmt->execute(array(99, 99, '2026-06-01', '2026-06-30'));
$row_tc2 = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('TC2 sees post-cutoff fully-paid BC', 500.0, (float)$row_tc2['total']);

// Admin 10 (TC1) should NOT see booking 12 in June (post-cutoff).
$stmt = $pdo->prepare($sql);
$stmt->execute(array(10, 10, '2026-06-01', '2026-06-30'));
$row_tc1_jun = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('TC1 does not see post-cutoff BC', 0, (int)$row_tc1_jun['bc_count']);

echo "\nAll assertions passed.\n";
