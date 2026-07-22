<?php
/**
 * Run with: php tests/helpers/CancellationRateExcludesDuplicateTest.php
 *
 * Locks the rule that "BOOKING - DUPLICATED BOOKING" cancellations are excluded
 * from the Cancellation Rate KPI — from BOTH the cancelled count (numerator)
 * and the total population (denominator). A duplicate is a data-entry copy of a
 * real booking, not a lost sale, so it must not move the rate at all.
 *
 * Exercises cancellation_rate_exclude_duplicate_clause() against SQLite, which
 * mirrors the WHERE fragment the live MySQL KPI queries use.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/cancellation_rate_helper.php';

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
    (3, 'BOOKING - DUPLICATED BOOKING'),
    (4, 'CUSTOMER - NO RESPONSE')
");

// 3 active BCs, 1 genuine cancellation (reason 4), 1 duplicate cancellation
// (reason 3 -> excluded), 1 cancellation with NULL reason (kept as genuine).
$pdo->exec("INSERT INTO booking VALUES
    (1, '2026-05-01', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (2, '2026-05-02', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (3, '2026-05-03', 'BOOKING CONFIRMATION', 'N', 'P', NULL),
    (4, '2026-05-04', 'BOOKING CONFIRMATION', 'Y', 'P', 4),
    (5, '2026-05-05', 'BOOKING CONFIRMATION', 'Y', 'P', 3),
    (6, '2026-05-06', 'BOOKING CONFIRMATION', 'Y', 'P', NULL)
");

$exclude = cancellation_rate_exclude_duplicate_clause('booking');

$sql = "SELECT COUNT(*) AS total,
               SUM(CASE WHEN CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
        FROM booking
        WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
          AND Status!='N'
          AND InsertDate BETWEEN :ms AND :me
          AND {$exclude}";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ms' => '2026-05-01', ':me' => '2026-05-31']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Duplicate (BookingID 5) dropped from BOTH counts:
//   total     = 3 active + 1 genuine + 1 null-reason cancel = 5
//   cancelled = 1 genuine + 1 null-reason                   = 2
assert_eq('total excludes duplicate',     5, (int) $row['total']);
assert_eq('cancelled excludes duplicate', 2, (int) $row['cancelled']);

$rate = (int) $row['total'] > 0
    ? round(((int) $row['cancelled'] / (int) $row['total']) * 100, 1)
    : 0.0;
assert_eq('rate is 40.0% (2/5), not 50% (3/6)', 40.0, $rate);

// Sanity: WITHOUT the exclusion the duplicate would inflate to 3/6 = 50%.
$raw = $pdo->query("SELECT COUNT(*) AS total,
                           SUM(CASE WHEN CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
                    FROM booking
                    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION' AND Status!='N'")
           ->fetch(PDO::FETCH_ASSOC);
assert_eq('control: unfiltered cancelled is 3', 3, (int) $raw['cancelled']);
assert_eq('control: unfiltered total is 6',     6, (int) $raw['total']);

echo "\nAll assertions passed.\n";
