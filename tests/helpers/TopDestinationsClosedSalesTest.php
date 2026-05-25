<?php
/**
 * Run with: php tests/helpers/TopDestinationsClosedSalesTest.php
 *
 * Locks the SQL behind the TC LEAD "Top Destinations — Closed Sales (Month)"
 * card. Only BCs whose approved customer payments (excluding agent commission)
 * sum to >= NetTotal should count, and the date filter is on creation date
 * (matches the TC Total Sales card definition).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE category (
    CategoryID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Destination INTEGER,
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

$pdo->exec("INSERT INTO category VALUES
    (1, 'Japan'),
    (2, 'Korea'),
    (3, 'Vietnam')
");

// Scenarios:
//  BC 1 — Japan,   fully paid (1000 == 1000)                              -> COUNT
//  BC 2 — Japan,   fully paid (multiple payments sum >= NetTotal)         -> COUNT
//  BC 3 — Korea,   partially paid (300 < 500)                             -> EXCLUDE
//  BC 4 — Korea,   only AGENT COMMISSION FROM SUPPLIER credits            -> EXCLUDE
//  BC 5 — Vietnam, fully paid but Status='N' (draft)                      -> EXCLUDE
//  BC 6 — Vietnam, fully paid but CancelStatus='Y'                        -> EXCLUDE
//  BC 7 — Vietnam, fully paid but created before window                   -> EXCLUDE
//  BC 8 — Vietnam, fully paid but NetTotal=0                              -> EXCLUDE
//  BC 9 — Vietnam, fully paid but QUOTATION                               -> EXCLUDE
//  BC 10 — Korea,  fully paid (2000) in window                            -> COUNT (Korea outranks Japan)
$pdo->exec("INSERT INTO booking VALUES
    (1,  1, '2026-05-03', 1000, 'BOOKING CONFIRMATION', 'N', 'P'),
    (2,  1, '2026-05-10',  800, 'BOOKING CONFIRMATION', 'N', 'P'),
    (3,  2, '2026-05-12',  500, 'BOOKING CONFIRMATION', 'N', 'P'),
    (4,  2, '2026-05-13',  500, 'BOOKING CONFIRMATION', 'N', 'P'),
    (5,  3, '2026-05-14',  900, 'BOOKING CONFIRMATION', 'N', 'N'),
    (6,  3, '2026-05-15',  900, 'BOOKING CONFIRMATION', 'Y', 'P'),
    (7,  3, '2026-04-30',  900, 'BOOKING CONFIRMATION', 'N', 'P'),
    (8,  3, '2026-05-18',    0, 'BOOKING CONFIRMATION', 'N', 'P'),
    (9,  3, '2026-05-19',  900, 'QUOTATION',            'N', 'P'),
    (10, 2, '2026-05-20', 2000, 'BOOKING CONFIRMATION', 'N', 'P')
");

// Payments:
//  BC 1 — single approved credit 1000 (fully paid)
//  BC 2 — two approved credits 500 + 300 = 800 (fully paid), plus a commission credit that must be ignored
//  BC 3 — only 300 paid (partially paid)
//  BC 4 — 600 credit but Type='AGENT COMMISSION FROM SUPPLIER' (must be ignored => 0 paid)
//  BC 5 — 900 paid (would otherwise be enough but Status='N')
//  BC 6 — 900 paid (would otherwise be enough but CancelStatus='Y')
//  BC 7 — 900 paid (before window)
//  BC 8 — 0 NetTotal; any credits >= 0 trivially match, must be excluded by NetTotal>0
//  BC 9 — 900 paid but QUOTATION
//  BC 10 — 2000 paid (fully paid)
//
// Also an unapproved credit on BC 1 (Status='N') that must be ignored.
$pdo->exec("INSERT INTO payment VALUES
    (1,  1, 1000,                            'Y', NULL),
    (2,  1, 99999,                           'N', NULL),                          /* unapproved - ignored */
    (3,  2,  500,                            'Y', NULL),
    (4,  2,  300,                            'Y', 'CASH'),
    (5,  2,  500,                            'Y', 'AGENT COMMISSION FROM SUPPLIER'), /* ignored */
    (6,  3,  300,                            'Y', NULL),
    (7,  4,  600,                            'Y', 'AGENT COMMISSION FROM SUPPLIER'), /* ignored */
    (8,  5,  900,                            'Y', NULL),
    (9,  6,  900,                            'Y', NULL),
    (10, 7,  900,                            'Y', NULL),
    (11, 9,  900,                            'Y', NULL),
    (12, 10, 2000,                           'Y', NULL)
");

// Reproduce the production SQL. {$paid_subquery} is inlined.
$paid_subquery = "COALESCE((
    SELECT SUM(p.Credit) FROM payment p
    WHERE p.BookingID = booking.BookingID
      AND p.Status = 'Y' AND p.Credit > 0
      AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')
), 0)";

$sql = "
    SELECT category.Name AS destination, category.CategoryID AS id,
           COUNT(BookingID) AS cnt, COALESCE(SUM(NetTotal),0) AS total
    FROM booking
    LEFT JOIN category ON category.CategoryID = booking.Destination
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND CancelStatus = 'N' AND booking.Status != 'N'
      AND booking.NetTotal > 0
      AND {$paid_subquery} >= booking.NetTotal
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY category.Name, category.CategoryID
    ORDER BY total DESC
    LIMIT 5
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':ms' => '2026-05-01', ':me' => '2026-05-31']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Expected: Korea total 2000 (BC 10), Japan total 1800 (BCs 1 + 2).
// All other rows must be filtered out: partial pay, commission-only, draft,
// cancelled, out-of-window, zero NetTotal, quotation.
assert_eq('two destinations survive filter', 2, count($rows));

assert_eq('top destination name',  'Korea',  (string)$rows[0]['destination']);
assert_eq('top destination cnt',   1,        (int)   $rows[0]['cnt']);
assert_eq('top destination total', 2000.0,   (float) $rows[0]['total']);

assert_eq('second destination name',  'Japan', (string)$rows[1]['destination']);
assert_eq('second destination cnt',   2,       (int)   $rows[1]['cnt']);
assert_eq('second destination total', 1800.0,  (float) $rows[1]['total']);

echo "\nAll assertions passed.\n";
