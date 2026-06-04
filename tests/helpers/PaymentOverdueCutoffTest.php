<?php
/**
 * Run with: php tests/helpers/PaymentOverdueCutoffTest.php
 *
 * Locks the 3pm overdue rule used by the Payment Overdue card and the booking
 * list's PO status filter:
 *
 *   payment_overdue_cutoff_date($now) returns the date D such that a payment
 *   deadline is overdue when `deadline < D`.
 *     - before 15:00 -> D = today      (only deadlines strictly before today)
 *     - 15:00 onward -> D = tomorrow   (today's deadlines become overdue)
 *
 * Plus an integration check: a booking whose deadline falls TODAY flips from
 * not-overdue to overdue exactly at the 3pm boundary.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/booking_status_filter_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- cutoff date by time of day -------------------------------------------
assert_eq('00:00 -> today',  '2026-06-15', payment_overdue_cutoff_date('2026-06-15 00:00:00'));
assert_eq('09:00 -> today',  '2026-06-15', payment_overdue_cutoff_date('2026-06-15 09:00:00'));
assert_eq('14:59 -> today',  '2026-06-15', payment_overdue_cutoff_date('2026-06-15 14:59:59'));
assert_eq('15:00 -> tomorrow','2026-06-16', payment_overdue_cutoff_date('2026-06-15 15:00:00'));
assert_eq('16:30 -> tomorrow','2026-06-16', payment_overdue_cutoff_date('2026-06-15 16:30:00'));
assert_eq('23:59 -> tomorrow','2026-06-16', payment_overdue_cutoff_date('2026-06-15 23:59:00'));

// ---- month / year rollover at 3pm -----------------------------------------
assert_eq('month-end 3pm',   '2026-07-01', payment_overdue_cutoff_date('2026-06-30 15:00:00'));
assert_eq('year-end 3pm',    '2027-01-01', payment_overdue_cutoff_date('2026-12-31 15:00:00'));
assert_eq('leap Feb 28 3pm',  '2024-02-29', payment_overdue_cutoff_date('2024-02-28 15:00:00'));

// ---- the PO status clause uses the cutoff, not today ----------------------
$clauses = booking_status_filter_per_status_clauses('PO', '2026-06-15', '2026-06-16');
$po_sql = implode(' AND ', $clauses);
assert_eq('PO clause uses cutoff date', true, strpos($po_sql, "< '2026-06-16'") !== false);
assert_eq('PO clause drops today date', true, strpos($po_sql, "< '2026-06-15'") === false);
// Two-arg call (legacy) keeps the today comparison.
$legacy = implode(' AND ', booking_status_filter_per_status_clauses('PO', '2026-06-15'));
assert_eq('legacy PO uses today', true, strpos($legacy, "< '2026-06-15'") !== false);

// ---- integration: a deadline TODAY flips at the boundary ------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, CancelStatus TEXT, BookingConfirmationTitle TEXT,
    Status TEXT, FullPaymentDeadline TEXT, DepositDeadline TEXT
)");
$pdo->exec("INSERT INTO booking VALUES
    /* due TODAY (full), partial paid */
    (1, 'N', 'BOOKING CONFIRMATION', 'PP', '2026-06-15', '2026-05-01'),
    /* due yesterday (always overdue) */
    (2, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-06-14', '2026-06-14'),
    /* due tomorrow (never overdue today) */
    (3, 'N', 'BOOKING CONFIRMATION', 'P',  '2026-06-16', '2026-06-16')");

$countOverdue = function ($cutoff) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking
        WHERE BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N'
          AND ((FullPaymentDeadline < :c AND Status IN ('P','PP'))
            OR (DepositDeadline < :c AND Status='P'))");
    $stmt->execute(array(':c' => $cutoff));
    return (int) $stmt->fetchColumn();
};

// Before 3pm: cutoff = today -> only the yesterday one (row 2).
assert_eq('before 3pm overdue count', 1, $countOverdue(payment_overdue_cutoff_date('2026-06-15 09:00:00')));
// From 3pm: cutoff = tomorrow -> yesterday + today (rows 1, 2).
assert_eq('after 3pm overdue count',  2, $countOverdue(payment_overdue_cutoff_date('2026-06-15 15:00:00')));

echo "\nAll assertions passed.\n";
