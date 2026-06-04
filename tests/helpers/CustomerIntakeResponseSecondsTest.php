<?php
/**
 * Run with: php tests/helpers/CustomerIntakeResponseSecondsTest.php
 *
 * Locks two things behind the customer-intake "response time" metric:
 *
 *   1. compute_response_seconds() correctly subtracts the customer's submission
 *      timestamp from the PBC log timestamp, and returns null on missing/bad
 *      input.
 *
 *   2. The SQL shape that calculate_submitted_to_payment_seconds() relies on -
 *      one row per booking from booking_customer_intake, plus the earliest
 *      booking_status_log row where to_status='P' (from_status set) - returns
 *      the timestamps we expect against the schema this migration creates.
 *
 * The pure compute function is verified directly. The SQL shape is verified
 * against an in-memory SQLite copy of the schema (same pattern as
 * SupplierInvoiceOutstandingSqlTest / ActiveLeadsCountTest).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_intake_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- 1) Pure subtraction --------------------------------------------------

assert_eq('exact 4500s gap (75m)', 4500,
    compute_response_seconds('2026-05-22 09:00:00', '2026-05-22 10:15:00'));

assert_eq('same-second gap = 0', 0,
    compute_response_seconds('2026-05-22 09:00:00', '2026-05-22 09:00:00'));

assert_eq('null submitted_at', null,
    compute_response_seconds(null, '2026-05-22 10:15:00'));

assert_eq('empty submitted_at', null,
    compute_response_seconds('', '2026-05-22 10:15:00'));

assert_eq('null finalised_at', null,
    compute_response_seconds('2026-05-22 09:00:00', null));

assert_eq('garbage submitted_at', null,
    compute_response_seconds('not-a-date', '2026-05-22 10:15:00'));

// -- 2) SQL shape against in-memory SQLite --------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking_customer_intake (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    submitted_at TEXT
)");
$pdo->exec("CREATE TABLE booking_status_log (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at TEXT
)");

// Booking 100: intake submitted at 09:00, P at 10:15 -> 4500s
// Booking 200: intake but no P transition -> null
// Booking 300: P log but no intake row -> null
// Booking 400: multiple P logs -> picks earliest (10:00 over 11:00)
// Booking 500: intake on 2026-05-21, P on 2026-05-22 -> 18000s (5h)
$pdo->exec("INSERT INTO booking_customer_intake (id, booking_id, submitted_at) VALUES
    (1, 100, '2026-05-22 09:00:00'),
    (2, 200, '2026-05-22 09:00:00'),
    (3, 400, '2026-05-22 09:00:00'),
    (4, 500, '2026-05-21 23:00:00')
");
$pdo->exec("INSERT INTO booking_status_log (id, booking_id, from_status, to_status, created_at) VALUES
    (1, 100, 'PBC', 'P', '2026-05-22 10:15:00'),
    /* B200 intentionally omitted */
    (2, 300, 'PBC', 'P', '2026-05-22 11:00:00'),
    (3, 400, 'PBC', 'P', '2026-05-22 11:00:00'),
    (4, 400, 'PBC', 'P', '2026-05-22 10:00:00'),
    (5, 500, 'PBC', 'P', '2026-05-22 04:00:00'),
    /* Distractor 1: the PBC milestone on B100 should be ignored now that the
       metric runs to PENDING PAYMENT (P), not BC confirmation (PBC). */
    (6, 100, 'SAD', 'PBC', '2026-05-22 09:30:00'),
    /* Distractor 2: a CREATION row for B100 entering P (from_status NULL)
       timestamped BEFORE the submission — must be excluded, else the gap goes
       negative. */
    (7, 100, NULL,  'P',  '2026-05-22 08:00:00')
");

function lookup_response_seconds(PDO $pdo, $booking_id)
{
    $submitted = $pdo->prepare("SELECT submitted_at FROM booking_customer_intake WHERE booking_id = :id");
    $submitted->execute([':id' => $booking_id]);
    $sub = $submitted->fetchColumn();
    if ($sub === false || $sub === null) {
        return null;
    }

    $payment = $pdo->prepare("SELECT created_at FROM booking_status_log
                          WHERE booking_id = :id AND to_status = 'P' AND from_status IS NOT NULL
                          ORDER BY created_at ASC LIMIT 1");
    $payment->execute([':id' => $booking_id]);
    $fin = $payment->fetchColumn();
    if ($fin === false || $fin === null) {
        return null;
    }
    return compute_response_seconds($sub, $fin);
}

assert_eq('B100 response = 4500s',            4500, lookup_response_seconds($pdo, 100));
assert_eq('B200 (no P log) response = null',   null, lookup_response_seconds($pdo, 200));
assert_eq('B300 (no intake) response = null',  null, lookup_response_seconds($pdo, 300));
assert_eq('B400 picks earliest P = 3600s',    3600, lookup_response_seconds($pdo, 400));
assert_eq('B500 spans days, = 18000s',       18000, lookup_response_seconds($pdo, 500));

echo "\nAll assertions passed.\n";
