<?php
/**
 * Run with: php tests/helpers/TopCancellationReasonShareTest.php
 *
 * Locks the "Top 5 Cancellation Reasons" card percentage rule: each reason's %
 * is that reason's cancelled-booking count divided by the TOTAL booking count
 * (all booking confirmations in the window), NOT divided by the total number of
 * cancellations. So the % answers "what share of all bookings were cancelled
 * for this reason", giving a small, meaningful figure instead of a share that
 * always sums to 100% across reasons.
 *
 * Mirrors the WHERE fragments Top_Cancellation_Reasons() and Total_Bookings()
 * build in Dashboard_Model, exercised against SQLite.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE cancellation_reason (
    CancellationReasonID INTEGER PRIMARY KEY,
    Name TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    InsertDate TEXT,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    CancellationReasonID INTEGER
)");

$pdo->exec("INSERT INTO cancellation_reason VALUES
    (4, 'CUSTOMER - NO RESPONSE'),
    (5, 'CUSTOMER - CHANGED MIND')
");

// 10 booking confirmations in-window: 6 active, 4 cancelled
//   (3 for reason 4, 1 for reason 5). Plus noise that must be ignored:
//   a draft (Status='N'), a quotation, and an out-of-window cancellation.
$pdo->exec("INSERT INTO booking VALUES
    (1,  '2026-03-01', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (2,  '2026-03-02', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (3,  '2026-03-03', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (4,  '2026-03-04', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (5,  '2026-03-05', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (6,  '2026-03-06', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (7,  '2026-03-07', 'BOOKING CONFIRMATION', 'Y', 'P', 4),
    (8,  '2026-03-08', 'BOOKING CONFIRMATION', 'Y', 'P', 4),
    (9,  '2026-03-09', 'BOOKING CONFIRMATION', 'Y', 'P', 4),
    (10, '2026-03-10', 'BOOKING CONFIRMATION', 'Y', 'P', 5),
    (11, '2026-03-11', 'BOOKING CONFIRMATION', 'N', 'N', NULL),
    (12, '2026-03-12', 'QUOTATION',            'Y', 'P', 4),
    (13, '2025-12-31', 'BOOKING CONFIRMATION', 'Y', 'P', 4)
");

$start = '2026-01-01';
$end   = '2026-12-31';

// Per-reason cancelled counts (numerators) — mirrors Top_Cancellation_Reasons().
$reasonSql = "SELECT cr.Name AS Name, COUNT(b.BookingID) AS Total
              FROM booking b
              INNER JOIN cancellation_reason cr
                      ON cr.CancellationReasonID = b.CancellationReasonID
              WHERE b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
                AND b.CancelStatus = 'Y'
                AND b.Status <> 'N'
                AND b.InsertDate BETWEEN :s AND :e
              GROUP BY cr.CancellationReasonID, cr.Name
              ORDER BY Total DESC";
$stmt = $pdo->prepare($reasonSql);
$stmt->execute([':s' => $start, ':e' => $end]);
$reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total booking count (denominator) — mirrors Total_Bookings(): every in-window
// booking confirmation, cancelled or not, drafts excluded.
$totalSql = "SELECT COUNT(BookingID) AS Total
             FROM booking
             WHERE BookingConfirmationTitle = 'BOOKING CONFIRMATION'
               AND Status <> 'N'
               AND InsertDate BETWEEN :s AND :e";
$stmt = $pdo->prepare($totalSql);
$stmt->execute([':s' => $start, ':e' => $end]);
$bookingTotal = (int) $stmt->fetch(PDO::FETCH_ASSOC)['Total'];

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Denominator counts active + genuine cancellations, ignores draft/quotation/out-of-window.
assert_eq('total booking count is 10', 10, $bookingTotal);

// Top reason is "CUSTOMER - NO RESPONSE" with 3 cancellations.
assert_eq('top reason name', 'CUSTOMER - NO RESPONSE', $reasons[0]['Name']);
assert_eq('top reason cancelled count', 3, (int) $reasons[0]['Total']);

// Share = cancelled / total bookings, NOT cancelled / total cancellations (which was 3/4=75%).
$topPct = $bookingTotal > 0 ? round(((int) $reasons[0]['Total'] / $bookingTotal) * 100) : 0;
assert_eq('top reason share is 30% (3/10), not 75% (3/4)', 30.0, $topPct);

$secondPct = $bookingTotal > 0 ? round(((int) $reasons[1]['Total'] / $bookingTotal) * 100) : 0;
assert_eq('second reason share is 10% (1/10)', 10.0, $secondPct);

echo "\nAll assertions passed.\n";
