<?php
/**
 * Run with: php tests/helpers/OpMonthlyCancellationCardTest.php
 *
 * Locks the OP "Cancellations (Month)" card (view-only, OP + OP TEAM LEAD).
 * The card lets OP pick any month and see the OP team's cancelled BCs and rate.
 *
 * It must agree with the TC "Cancellation Rate (Month)" card's counting rule:
 *   - confirmed BCs only (drafts / quotations excluded)
 *   - duplicate-cancellations dropped from BOTH numerator and denominator
 *   - scoped by booking.SalesAgent to the OP team
 *   - counted by InsertDate (when created), windowed to the picked month
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/cancellation_rate_helper.php';
require_once __DIR__ . '/../../application/helpers/op_cancellation_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE cancellation_reason (
    CancellationReasonID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    InsertDate TEXT,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    CancellationReasonID INTEGER,
    NetTotal REAL,
    Status TEXT
)");

// Reason 1 is the DUPLICATE reason (must be excluded from both sides);
// reason 2 is a genuine reason.
$pdo->exec("INSERT INTO cancellation_reason VALUES
    (1, 'BOOKING - DUPLICATED BOOKING'),
    (2, 'Customer Changed Mind')
");

// OP team = agents 3,4,5. Agent 9 is OUTSIDE the team (must be ignored).
// Target month = 2026-07.
$pdo->exec("INSERT INTO booking VALUES
    /* --- team, July: 4 in population, 2 genuine cancellations --- */
    (1, 3, '2026-07-02', 'BOOKING CONFIRMATION', 'N', NULL, 1000, 'P'),
    (2, 4, '2026-07-05', 'BOOKING CONFIRMATION', 'N', NULL, 2000, 'P'),
    (3, 5, '2026-07-10', 'BOOKING CONFIRMATION', 'Y', 2,    3000, 'P'),
    (4, 3, '2026-07-20', 'BOOKING CONFIRMATION', 'Y', NULL, 4000, 'P'),
    /* duplicate-cancellation: dropped from BOTH total and cancelled (and revenue) */
    (5, 4, '2026-07-11', 'BOOKING CONFIRMATION', 'Y', 1,    5000, 'P'),
    /* draft (Status=N) and quotation: excluded entirely */
    (6, 3, '2026-07-12', 'BOOKING CONFIRMATION', 'N', NULL, 6000, 'N'),
    (7, 3, '2026-07-13', 'QUOTATION',            'N', NULL, 7000, 'P'),
    /* --- team, but AUGUST: outside the month window --- */
    (8, 3, '2026-08-01', 'BOOKING CONFIRMATION', 'Y', 2,    8000, 'P'),
    /* --- July but agent OUTSIDE the OP team: excluded by scope --- */
    (9, 9, '2026-07-07', 'BOOKING CONFIRMATION', 'Y', 2,    9000, 'P')
");

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ----- Team-scoped July -----
$sql  = op_monthly_cancellation_sql('booking.SalesAgent IN (3,4,5)');
$stmt = $pdo->prepare($sql);
$stmt->execute(['2026-07-01', '2026-07-31']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Population: rows 1,2,3,4 (row 5 duplicate excluded, 6 draft, 7 quotation,
// 8 wrong month, 9 wrong team). = 4
assert_eq('team July population', 4, (int) $row['total']);
// Genuine cancellations: rows 3 and 4. = 2
assert_eq('team July cancelled', 2, (int) $row['cancelled']);
assert_eq('team July rate', 50.0, op_cancellation_rate($row['cancelled'], $row['total']));
// Revenue lost: NetTotal of the genuine cancellations only (rows 3 + 4).
// Duplicate row 5 (5000) is excluded — data-entry copy, not lost sales.
assert_eq('team July revenue lost', 7000.0, (float) $row['revenue_lost']);

// ----- Team-scoped August (only the duplicate-... no, row 8 is genuine) -----
$stmt->execute(['2026-08-01', '2026-08-31']);
$rowA = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('team Aug population', 1, (int) $rowA['total']);
assert_eq('team Aug cancelled',  1, (int) $rowA['cancelled']);
assert_eq('team Aug rate', 100.0, op_cancellation_rate($rowA['cancelled'], $rowA['total']));
assert_eq('team Aug revenue lost', 8000.0, (float) $rowA['revenue_lost']);

// ----- A month with no team bookings: rate must be 0, no divide-by-zero -----
$stmt->execute(['2026-01-01', '2026-01-31']);
$rowE = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('empty month population', 0, (int) $rowE['total']);
assert_eq('empty month rate', 0.0, op_cancellation_rate($rowE['cancelled'], $rowE['total']));
assert_eq('empty month revenue lost', 0.0, (float) $rowE['revenue_lost']);

// ----- Rate rounding to one decimal (1/3) -----
assert_eq('rate rounds to 1 dp', 33.3, op_cancellation_rate(1, 3));

echo "\nAll assertions passed.\n";
