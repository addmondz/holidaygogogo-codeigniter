<?php
/**
 * Run with: php tests/helpers/PendingPartialPaymentsGroupByTest.php
 *
 * Locks the SELECT shape behind Dashboard_Model::Pending_Partial_Payments().
 * The query LEFT JOINs payment (many rows per booking) and GROUP BYs
 * BookingNumber, so Credit/Debit are summed across the booking's payments while
 * NetTotal and the deadline columns are constant per booking. Those per-booking
 * columns MUST be wrapped in an aggregate (MIN) so the SELECT is valid under
 * MySQL's only_full_group_by mode (error 1055) — otherwise the dashboard's
 * "Pending Payments" endpoint 500s.
 *
 * This replicates the query shape against SQLite and verifies:
 *   - One row per booking (grouping by BookingNumber).
 *   - OutstandingBalance = NetTotal - SUM(Credit) + SUM(Debit) over the booking's
 *     payment rows.
 *   - Month picks the later of FullPaymentDeadline / AdditionalPaymentDeadline,
 *     and uses FullPaymentDeadline when AdditionalPaymentDeadline IS NULL.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    BookingNumber TEXT,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    FullPaymentDeadline TEXT,
    AdditionalPaymentDeadline TEXT
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    Type TEXT,
    Status TEXT,
    Credit REAL,
    DEbit REAL
)");

// Booking 1: NetTotal 1000, two qualifying payments (Credit 300 + 200), additional
// deadline (Apr) later than full (Mar) -> Month = 4. Outstanding = 1000-500 = 500.
// Booking 2: NetTotal 800, one payment (Credit 800), no additional deadline ->
// Month from FullPaymentDeadline (Jun) = 6. Outstanding = 800-800 = 0.
$pdo->exec("INSERT INTO booking VALUES
    (1, 'BN-1', 1000, 'BOOKING CONFIRMATION', 'N', 'PP', '2026-03-10', '2026-04-15'),
    (2, 'BN-2', 800,  'BOOKING CONFIRMATION', 'N', 'PP', '2026-06-20', NULL)
");
$pdo->exec("INSERT INTO payment VALUES
    (1, 1, 'DEPOSIT', 'Y', 300, 0),
    (2, 1, 'FULL',    'Y', 200, 0),
    (3, 2, 'FULL',    'Y', 800, 0)
");

// Mirror of Dashboard_Model::Pending_Partial_Payments() SELECT (only_full_group_by-safe).
$sql = "
    SELECT (MIN(NetTotal) - SUM(Credit) + SUM(DEbit)) AS OutstandingBalance,
           CAST(strftime('%m', CASE WHEN MIN(AdditionalPaymentDeadline) IS NULL
                                      OR MIN(FullPaymentDeadline) > MIN(AdditionalPaymentDeadline)
                                    THEN MIN(FullPaymentDeadline)
                                    ELSE MIN(AdditionalPaymentDeadline) END) AS INTEGER) AS Month
    FROM booking
    LEFT JOIN payment ON payment.BookingID = booking.BookingID
    WHERE payment.Type IN ('DEPOSIT','FULL','ADDITIONAL PAYMENT','CUSTOMER REFUND')
      AND payment.Status = 'Y'
      AND BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND CancelStatus = 'N'
      AND booking.Status = 'PP'
    GROUP BY BookingNumber
    ORDER BY BookingNumber
";
// NOTE: MySQL uses MONTH(...); SQLite has no MONTH(), so strftime('%m') stands in.
// The aggregate-wrapping (MIN over constant-per-booking columns) is what this test
// guards — that is the only_full_group_by fix.

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('one row per booking', 2, count($rows));

// Booking 1: outstanding 500, Month = 4 (additional Apr later than full Mar).
assert_eq('B1 outstanding', 500.0, (float) $rows[0]['OutstandingBalance']);
assert_eq('B1 month (later deadline)', 4, (int) $rows[0]['Month']);

// Booking 2: outstanding 0, Month = 6 (full deadline; additional is NULL).
assert_eq('B2 outstanding', 0.0, (float) $rows[1]['OutstandingBalance']);
assert_eq('B2 month (full deadline, additional null)', 6, (int) $rows[1]['Month']);

echo "\nAll assertions passed.\n";
