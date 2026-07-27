<?php
/**
 * Run with: php tests/helpers/BookingDownloadStatusFilterTest.php
 *
 * Locks the multi-select contract for the booking status filter so that the
 * download spreadsheets ("Booking Records" and "Guest List") stay in lockstep
 * with the on-screen list.
 *
 * Bug shape this guards against:
 *   The Booking list page renders Status as a Bootstrap multi-select that
 *   submits a comma-joined string (e.g. status=OG,Y). The download path
 *   previously used naive equality checks (status == 'A', == 'C', ...) which
 *   never match a comma-joined string -- so the if-block stayed truthy,
 *   the default CancelStatus='N' branch was skipped, and cancelled bookings
 *   like "GOH SIEW LEE" leaked into the spreadsheet.
 *
 * This test exercises the shared SQL helper that both call sites now consume,
 * by running its WHERE fragment against an in-memory SQLite booking table.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_status_filter_helper.php';

$today = '2026-05-20';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Customer TEXT,
    Status TEXT,
    CancelStatus TEXT,
    AfterSalesService TEXT,
    LockStatus TEXT,
    DepositDeadline TEXT,
    FullPaymentDeadline TEXT,
    InsertDate TEXT
)");

// Seed rows that cover the statuses exercised by the multi-select UI.
// Convention: BookingID -> intended status label.
$pdo->exec("INSERT INTO booking VALUES
    (1, 'GOH SIEW LEE', 'P',   'Y', 'PENDING',  'N', '2026-05-25', '2026-06-10', '2026-04-15'), /* CANCELLED */
    (2, 'Alice OG',     'OG',  'N', 'PENDING',  'N',  NULL,        '2026-06-10', '2026-04-16'), /* ON-GOING */
    (3, 'Bob Done',     'Y',   'N', 'COMPLETE', 'Y',  NULL,        '2026-04-01', '2026-04-17'), /* COMPLETED */
    (4, 'Carol PP',     'PP',  'N', 'PENDING',  'N', '2026-05-15', '2026-06-15', '2026-04-18'), /* PARTIAL PAYMENT */
    (5, 'Dan PR',       'Y',   'N', 'PENDING',  'N',  NULL,        '2026-04-01', '2026-04-19'), /* PENDING REVIEW (Y + PENDING) */
    (6, 'Eve PBC',      'PBC', 'N', 'PENDING',  'N', '2026-05-25', '2026-06-10', '2026-04-20'), /* PENDING BC */
    (7, 'Frank Pending P', 'P', 'N', 'PENDING', 'N', '2026-05-25', '2026-06-10', '2026-04-21')  /* PENDING PAYMENT (deadline >= today) */
");

$assertions = [];

function run_filter_query(PDO $pdo, $status_param, $today)
{
    $where = booking_status_filter_full_where($status_param, $today);
    $sql = "SELECT BookingID FROM booking WHERE " . $where . " ORDER BY BookingID";
    $rows = [];
    foreach ($pdo->query($sql) as $r) {
        $rows[] = (int) $r['BookingID'];
    }
    return $rows;
}

// 1) REGRESSION: multi-status excluding 'C' must not return cancelled rows.
//    This is the exact bug shape the user hit -- selecting all statuses except
//    CANCELLED and ALL STATUSES from the multi-select.
$ids = run_filter_query($pdo, 'OG,Y,PP,PR,PBC,P', $today);
$assertions['multi-select OG,Y,PP,PR,PBC,P excludes cancelled (GOH SIEW LEE)'] =
    !in_array(1, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes OG'] = in_array(2, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes COMPLETED'] = in_array(3, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes PP'] = in_array(4, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes PR (Y+PENDING)'] = in_array(5, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes PBC'] = in_array(6, $ids, true);
$assertions['multi-select OG,Y,PP,PR,PBC,P includes pending P'] = in_array(7, $ids, true);

// 2) Single-status still works (back-compat: same behavior as the old equality form).
$ids = run_filter_query($pdo, 'OG', $today);
$assertions['single status OG returns only OG row'] = $ids === [2];

// 3) 'C' returns only cancelled rows (parity with on-screen list).
$ids = run_filter_query($pdo, 'C', $today);
$assertions['single status C returns only cancelled GOH SIEW LEE'] = $ids === [1];

// 4) Multi-select WITH 'C' included also surfaces cancelled rows.
$ids = run_filter_query($pdo, 'C,OG', $today);
$assertions['multi-select C,OG returns cancelled + OG'] = $ids === [1, 2];

// 5) 'A' (ALL STATUSES) returns everything non-cancelled and non-deleted.
$ids = run_filter_query($pdo, 'A', $today);
$assertions['ALL STATUSES (A) excludes cancelled'] = !in_array(1, $ids, true);
$assertions['ALL STATUSES (A) includes all non-cancelled rows'] = $ids === [2, 3, 4, 5, 6, 7];

// 6) Empty status falls back to default: CancelStatus='N' AND AfterSalesService='PENDING'.
//    Rows 2 (OG, PENDING), 4 (PP, PENDING), 5 (Y, PENDING -> PR), 6 (PBC, PENDING), 7 (P, PENDING).
//    Excludes 1 (cancelled) and 3 (COMPLETE).
$ids = run_filter_query($pdo, '', $today);
$assertions['empty status -> default branch excludes cancelled'] = !in_array(1, $ids, true);
$assertions['empty status -> default branch excludes COMPLETE'] = !in_array(3, $ids, true);
$assertions['empty status -> default branch returns only PENDING rows'] = $ids === [2, 4, 5, 6, 7];

// 7) Unknown status code falls through harmlessly -- still excludes cancelled
//    (falls back to default branch when no fragments are produced).
$ids = run_filter_query($pdo, 'ZZZZ', $today);
$assertions['unknown status falls back to default (no cancelled leak)'] = !in_array(1, $ids, true);

// 8) Combination with 'A' + a more specific status still excludes cancelled
//    (each branch in the OR carries its own CancelStatus='N' clause).
$ids = run_filter_query($pdo, 'A,OG', $today);
$assertions['multi-select A,OG excludes cancelled'] = !in_array(1, $ids, true);

// 9) Whitespace around tokens is tolerated (Bootstrap selectpicker can serialize with spaces).
$ids = run_filter_query($pdo, ' OG , Y ', $today);
$assertions['whitespace around tokens is tolerated'] = $ids === [2, 3];

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
