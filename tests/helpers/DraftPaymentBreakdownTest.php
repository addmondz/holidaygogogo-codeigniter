<?php
/**
 * Run with: php tests/helpers/DraftPaymentBreakdownTest.php
 *
 * Locks the per-booking "Draft -> Payment" breakdown behind the inline card.
 * The single SAD -> P gap is now split into three readable segments, each
 * measured between the FIRST time the booking reached each milestone:
 *
 *   1) Draft -> Pending BC               : first SAD        -> first reach PB
 *   2) Pending BC -> Pending BC Confirm. : first reach PB   -> first reach PBC
 *   3) Pending BC Confirm. -> Pending Pay: first reach PBC  -> first reach P
 *
 * compute_draft_payment_segments() is a pure subtraction over four DATETIME
 * milestones (reusing compute_response_seconds), so a missing milestone makes
 * only the segments that touch it null - the others still report. This is
 * verified directly. The SQL shape that feeds it (earliest row reaching each
 * status) is verified against an in-memory SQLite copy of booking_status_log,
 * the same pattern as ResponseTimeSecondsTest.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/response_time_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- 1) Pure segment subtraction ------------------------------------------

// Full happy path: SAD 09:00 -> PB 09:30 -> PBC 10:00 -> P 10:15
$full = compute_draft_payment_segments(
    '2026-05-22 09:00:00', '2026-05-22 09:30:00',
    '2026-05-22 10:00:00', '2026-05-22 10:15:00'
);
assert_eq('full: draft_to_pb = 1800s', 1800, $full['draft_to_pb']);
assert_eq('full: pb_to_pbc = 1800s',   1800, $full['pb_to_pbc']);
assert_eq('full: pbc_to_p = 900s',      900, $full['pbc_to_p']);

// Skipped PB (went SAD -> PBC directly): the two segments touching PB are
// null, but PBC -> P still reports.
$skip_pb = compute_draft_payment_segments(
    '2026-05-22 09:00:00', null,
    '2026-05-22 10:00:00', '2026-05-22 10:15:00'
);
assert_eq('skip PB: draft_to_pb null', null,  $skip_pb['draft_to_pb']);
assert_eq('skip PB: pb_to_pbc null',   null,  $skip_pb['pb_to_pbc']);
assert_eq('skip PB: pbc_to_p = 900s',   900,  $skip_pb['pbc_to_p']);

// Not yet at payment: pbc_to_p null, earlier segments still report.
$no_pay = compute_draft_payment_segments(
    '2026-05-22 09:00:00', '2026-05-22 09:30:00',
    '2026-05-22 10:00:00', null
);
assert_eq('no pay: draft_to_pb = 1800s', 1800, $no_pay['draft_to_pb']);
assert_eq('no pay: pb_to_pbc = 1800s',   1800, $no_pay['pb_to_pbc']);
assert_eq('no pay: pbc_to_p null',       null, $no_pay['pbc_to_p']);

// -- 2) SQL shape against in-memory SQLite --------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking_status_log (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at TEXT
)");

// Booking 100: full flow SAD 09:00 -> PB 09:30 -> PBC 10:00 -> P 10:15.
//   Includes a CREATION row entering PB (from_status NULL) at 08:00 that must
//   be ignored, and a later second PB row that must lose to the first.
$pdo->exec("INSERT INTO booking_status_log (id, booking_id, from_status, to_status, created_at) VALUES
    (1, 100, NULL,  'PB',  '2026-05-22 08:00:00'),
    (2, 100, 'SAD', 'SAD', '2026-05-22 09:00:00'),
    (3, 100, 'SAD', 'PB',  '2026-05-22 09:30:00'),
    (4, 100, 'PB',  'PBC', '2026-05-22 10:00:00'),
    (5, 100, 'PBC', 'PBC', '2026-05-22 10:30:00'),
    (6, 100, 'PBC', 'P',   '2026-05-22 10:15:00')
");

// Earliest TRANSITION (from_status not null) into a given status.
function first_reach(PDO $pdo, $booking_id, $status)
{
    $stmt = $pdo->prepare("SELECT created_at FROM booking_status_log
        WHERE booking_id = :id AND to_status = :st AND from_status IS NOT NULL
        ORDER BY created_at ASC LIMIT 1");
    $stmt->execute([':id' => $booking_id, ':st' => $status]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : $v;
}
// SAD is an anchor, so it uses MIN over any SAD row (creation or transition).
function first_sad(PDO $pdo, $booking_id)
{
    $stmt = $pdo->prepare("SELECT MIN(created_at) FROM booking_status_log
        WHERE booking_id = :id AND to_status = 'SAD'");
    $stmt->execute([':id' => $booking_id]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : $v;
}

$seg = compute_draft_payment_segments(
    first_sad($pdo, 100),
    first_reach($pdo, 100, 'PB'),
    first_reach($pdo, 100, 'PBC'),
    first_reach($pdo, 100, 'P')
);
assert_eq('SQL B100: draft_to_pb = 1800s', 1800, $seg['draft_to_pb']);
assert_eq('SQL B100: pb_to_pbc = 1800s',   1800, $seg['pb_to_pbc']);
assert_eq('SQL B100: pbc_to_p = 900s',      900, $seg['pbc_to_p']);

echo "\nAll assertions passed.\n";
