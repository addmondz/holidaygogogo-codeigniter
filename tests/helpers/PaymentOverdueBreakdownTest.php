<?php
/**
 * Run with: php tests/helpers/PaymentOverdueBreakdownTest.php
 *
 * Locks the disjoint breakdown used by the Payment Overdue popover on the
 * booking dashboard. The card's tooltip now shows two sub-counts that must
 * partition the overdue total:
 *
 *   full_overdue          = FullPaymentDeadline < today  AND Status IN ('P','PP')
 *   deposit_only_overdue  = DepositDeadline    < today  AND Status = 'P'
 *                            AND NOT (the full_overdue condition)
 *
 * The invariant under test: full_overdue + deposit_only_overdue equals the
 * row count from the original disjunctive WHERE clause used by the card. If
 * either branch drifts, the tooltip's sub-counts would stop summing to the
 * displayed card value, which is exactly the abstraction the popover refresh
 * was meant to eliminate.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT,
    Status TEXT,
    FullPaymentDeadline TEXT,
    DepositDeadline TEXT
)");

// today = 2026-05-19. Comments mark the expected bucket.
$today = '2026-05-19';

$pdo->exec("INSERT INTO booking VALUES
    /* 1  full-only:    Status P, full deadline passed, deposit still future       -> FULL  */
    (1, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-05-10', '2026-06-01'),
    /* 2  full-only PP: Status PP, full deadline passed, deposit doesn't matter     -> FULL  */
    (2, 'N', 'BOOKING CONFIRMATION', 'PP', '2026-05-15', '2026-04-01'),
    /* 3  deposit-only: Status P, deposit deadline passed, full deadline future     -> DEPO  */
    (3, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-06-30', '2026-05-10'),
    /* 4  BOTH overdue: Status P, both deadlines passed -> classified as FULL only  -> FULL  */
    (4, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-05-01', '2026-04-20'),
    /* 5  not overdue: deadlines all in the future                                  -> none  */
    (5, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-06-30', '2026-06-15'),
    /* 6  PP with deposit overdue ONLY: deposit deadline passed but Status=PP
            -> deposit-overdue branch needs Status='P' so this NEVER counts        -> none  */
    (6, 'N', 'BOOKING CONFIRMATION', 'PP', '2026-06-30', '2026-04-01'),
    /* 7  cancelled overdue: must be excluded by outer filter                       -> none  */
    (7, 'Y', 'BOOKING CONFIRMATION', 'P',  '2026-05-01', '2026-05-01'),
    /* 8  quotation: must be excluded by outer filter                               -> none  */
    (8, 'N', 'QUOTATION',            'P',  '2026-05-01', '2026-05-01')
");

// --- Total via the ORIGINAL disjunctive WHERE clause used by the card ----------
$totalStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt FROM booking
    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND CancelStatus='N'
      AND (
           (FullPaymentDeadline < :t AND Status IN ('P','PP'))
        OR (DepositDeadline    < :t AND Status='P')
      )
");
$totalStmt->execute(array(':t' => $today));
$total = (int) $totalStmt->fetchColumn();

// --- Disjoint breakdown that the popover surfaces ------------------------------
$breakStmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN FullPaymentDeadline < :t AND Status IN ('P','PP') THEN 1 ELSE 0 END) AS full_overdue,
      SUM(CASE WHEN DepositDeadline    < :t AND Status='P'
                AND NOT (FullPaymentDeadline < :t AND Status IN ('P','PP'))
               THEN 1 ELSE 0 END) AS deposit_only_overdue
    FROM booking
    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND CancelStatus='N'
      AND (
           (FullPaymentDeadline < :t AND Status IN ('P','PP'))
        OR (DepositDeadline    < :t AND Status='P')
      )
");
$breakStmt->execute(array(':t' => $today));
$row = $breakStmt->fetch(PDO::FETCH_ASSOC);
$full    = (int) $row['full_overdue'];
$deposit = (int) $row['deposit_only_overdue'];

// --- Assertions ----------------------------------------------------------------
function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('total (disjunctive WHERE)', 4, $total);              // rows 1,2,3,4
assert_eq('full_overdue branch',       3, $full);               // rows 1,2,4
assert_eq('deposit_only_overdue',      1, $deposit);            // row 3
assert_eq('partition: full+depo=total', $total, $full + $deposit);

echo "\nAll assertions passed.\n";
