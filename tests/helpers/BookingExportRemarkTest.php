<?php
/**
 * Run with: php tests/helpers/BookingExportRemarkTest.php
 *
 * Locks the REMARK / CANCELLATION column contract for the "Booking Records"
 * download (Booking::Download). The booking's own remark, the cancellation
 * reason and the cancellation remark each live in their OWN column:
 *   - REMARK              -> BookingRemark only.
 *   - CANCELLATION REASON -> reason name, cancelled bookings only.
 *   - CANCELLATION REMARK -> free-text remark, cancelled bookings only.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_export_remark_helper.php';

$failures = 0;
function check($label, $expected, $actual)
{
    global $failures;
    if ($expected === $actual) {
        echo "PASS: $label\n";
    } else {
        $failures++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

// Dummy booking factory.
function dummy_booking($fields)
{
    $defaults = array(
        'BookingRemark'          => '',
        'CancelStatus'           => 'N',
        'CancellationReasonName' => null,
        'CancellationRemark'     => null,
    );
    return (object) array_merge($defaults, $fields);
}

// --- REMARK column: always just the booking's own remark ---

check(
    'active booking keeps its own remark',
    'Customer prefers window seat',
    booking_export_remark(dummy_booking(array('BookingRemark' => 'Customer prefers window seat')))
);

check(
    'active booking with no remark stays empty',
    '',
    booking_export_remark(dummy_booking(array()))
);

check(
    'cancelled booking REMARK column is NOT polluted with cancellation info',
    'Paid deposit only',
    booking_export_remark(dummy_booking(array(
        'BookingRemark'          => 'Paid deposit only',
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Customer Request',
        'CancellationRemark'     => 'Changed travel dates',
    )))
);

// --- CANCELLATION REASON column ---

check(
    'non-cancelled booking has blank cancellation reason',
    '',
    booking_export_cancellation_reason(dummy_booking(array(
        'CancellationReasonName' => 'Should Not Show',
    )))
);

check(
    'cancelled booking exposes reason name',
    'Out of Budget',
    booking_export_cancellation_reason(dummy_booking(array(
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Out of Budget',
    )))
);

check(
    'cancelled without a reason gives blank reason',
    '',
    booking_export_cancellation_reason(dummy_booking(array(
        'CancelStatus' => 'Y',
    )))
);

// --- CANCELLATION REMARK column ---

check(
    'non-cancelled booking has blank cancellation remark',
    '',
    booking_export_cancellation_remark(dummy_booking(array(
        'CancellationRemark' => 'Should Not Show',
    )))
);

check(
    'cancelled booking exposes free-text remark',
    'Changed travel dates',
    booking_export_cancellation_remark(dummy_booking(array(
        'CancelStatus'       => 'Y',
        'CancellationRemark' => 'Changed travel dates',
    )))
);

check(
    'cancelled with reason but no remark gives blank remark',
    '',
    booking_export_cancellation_remark(dummy_booking(array(
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Duplicate',
    )))
);

check(
    'whitespace-only cancellation remark is trimmed to blank',
    '',
    booking_export_cancellation_remark(dummy_booking(array(
        'CancelStatus'       => 'Y',
        'CancellationRemark' => '   ',
    )))
);

echo "\n";
if ($failures === 0) {
    echo "All BookingExportRemark tests passed.\n";
    exit(0);
}
echo "$failures assertion(s) failed.\n";
exit(1);
