<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking Records export — REMARK column composer.
 *
 * The "Booking Records" spreadsheet (Booking::Download) prints the booking's
 * free-text remark in the REMARK column. For cancelled bookings the ops team
 * also wants the cancellation reason there, so the export stays self-contained
 * (no need to open each booking to learn why it was cancelled).
 *
 * Rule:
 *   - Non-cancelled booking            -> just the BookingRemark.
 *   - Cancelled (CancelStatus = 'Y')   -> BookingRemark + a "Cancellation
 *     Reason: <name> - <remark>" line. The remark suffix is only added when a
 *     free-text CancellationRemark exists. If the booking has no BookingRemark,
 *     the cancellation line stands alone (no leading blank line).
 *
 * @param object $booking Row from Read_Bookings_With_Guest_Lists carrying
 *                        BookingRemark, CancelStatus, CancellationReasonName,
 *                        CancellationRemark.
 * @return string Composed REMARK cell value.
 */
function booking_export_remark($booking)
{
    $remark = isset($booking->BookingRemark) ? (string) $booking->BookingRemark : '';

    $cancelled = isset($booking->CancelStatus) && $booking->CancelStatus == 'Y';
    $reason    = isset($booking->CancellationReasonName) ? trim((string) $booking->CancellationReasonName) : '';

    if ($cancelled && $reason !== '') {
        $line = 'Cancellation Reason: ' . $reason;
        $extra = isset($booking->CancellationRemark) ? trim((string) $booking->CancellationRemark) : '';
        if ($extra !== '') {
            $line .= ' - ' . $extra;
        }
        $remark = $remark !== '' ? $remark . "\n" . $line : $line;
    }

    return $remark;
}
