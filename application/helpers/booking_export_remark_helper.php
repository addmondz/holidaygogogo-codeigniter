<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking Records export — REMARK / cancellation column composers.
 *
 * The "Booking Records" spreadsheet (Booking::Download) prints the booking's
 * free-text remark plus, for cancelled bookings, the cancellation reason and
 * its free-text remark. These live in three SEPARATE columns so the ops team
 * can filter/sort on each independently:
 *
 *   - REMARK              -> the booking's own free-text remark (unchanged).
 *   - CANCELLATION REASON -> the reason name, only for cancelled bookings.
 *   - CANCELLATION REMARK -> the free-text cancellation remark, only for
 *                            cancelled bookings.
 *
 * Cancellation columns stay blank for non-cancelled bookings.
 *
 * @param object $booking Row from Read_Bookings_With_Guest_Lists carrying
 *                        BookingRemark, CancelStatus, CancellationReasonName,
 *                        CancellationRemark.
 */

/**
 * The booking's own free-text remark (REMARK column).
 *
 * @return string
 */
function booking_export_remark($booking)
{
    return isset($booking->BookingRemark) ? (string) $booking->BookingRemark : '';
}

/**
 * Cancellation reason name (CANCELLATION REASON column). Blank unless the
 * booking is cancelled and a reason is recorded.
 *
 * @return string
 */
function booking_export_cancellation_reason($booking)
{
    $cancelled = isset($booking->CancelStatus) && $booking->CancelStatus == 'Y';
    if (!$cancelled) {
        return '';
    }
    return isset($booking->CancellationReasonName) ? trim((string) $booking->CancellationReasonName) : '';
}

/**
 * Free-text cancellation remark (CANCELLATION REMARK column). Blank unless the
 * booking is cancelled and a remark is recorded.
 *
 * @return string
 */
function booking_export_cancellation_remark($booking)
{
    $cancelled = isset($booking->CancelStatus) && $booking->CancelStatus == 'Y';
    if (!$cancelled) {
        return '';
    }
    return isset($booking->CancellationRemark) ? trim((string) $booking->CancellationRemark) : '';
}
