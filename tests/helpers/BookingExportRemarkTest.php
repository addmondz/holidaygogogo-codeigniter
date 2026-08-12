<?php
/**
 * Run with: php tests/helpers/BookingExportRemarkTest.php
 *
 * Locks the REMARK column contract for the "Booking Records" download
 * (Booking::Download). Cancelled bookings must carry their cancellation reason
 * (and any free-text cancellation remark) into the REMARK cell, appended after
 * the booking's own remark, without clobbering it.
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

// 1. Active booking: remark passes through untouched.
check(
    'active booking keeps its own remark',
    'Customer prefers window seat',
    booking_export_remark(dummy_booking(array('BookingRemark' => 'Customer prefers window seat')))
);

// 2. Active booking with no remark: empty string.
check(
    'active booking with no remark stays empty',
    '',
    booking_export_remark(dummy_booking(array()))
);

// 3. Cancelled with reason + remark, and an existing booking remark: appended on a new line.
check(
    'cancelled: reason + remark appended below booking remark',
    "Paid deposit only\nCancellation Reason: Customer Request - Changed travel dates",
    booking_export_remark(dummy_booking(array(
        'BookingRemark'          => 'Paid deposit only',
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Customer Request',
        'CancellationRemark'     => 'Changed travel dates',
    )))
);

// 4. Cancelled with reason only (no cancellation remark): no trailing dash.
check(
    'cancelled: reason only, no dash suffix',
    'Cancellation Reason: Out of Budget',
    booking_export_remark(dummy_booking(array(
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Out of Budget',
    )))
);

// 5. Cancelled but no reason recorded: nothing appended.
check(
    'cancelled without a reason appends nothing',
    'Some note',
    booking_export_remark(dummy_booking(array(
        'BookingRemark' => 'Some note',
        'CancelStatus'  => 'Y',
    )))
);

// 6. Cancelled, no booking remark: cancellation line stands alone (no leading newline).
check(
    'cancelled with empty booking remark has no leading blank line',
    'Cancellation Reason: Duplicate - Double booked',
    booking_export_remark(dummy_booking(array(
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Duplicate',
        'CancellationRemark'     => 'Double booked',
    )))
);

// 7. Whitespace-only cancellation remark is treated as absent.
check(
    'whitespace-only cancellation remark is ignored',
    'Cancellation Reason: Customer Request',
    booking_export_remark(dummy_booking(array(
        'CancelStatus'           => 'Y',
        'CancellationReasonName' => 'Customer Request',
        'CancellationRemark'     => '   ',
    )))
);

echo "\n";
if ($failures === 0) {
    echo "All BookingExportRemark tests passed.\n";
    exit(0);
}
echo "$failures assertion(s) failed.\n";
exit(1);
