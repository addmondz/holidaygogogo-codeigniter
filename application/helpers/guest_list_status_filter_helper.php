<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('guest_list_submitted_where')) {
    /**
     * Single source of truth for the "Guest List Submitted" predicate, shared by
     * the dashboard summary card count (Booking::ajax_summary_cards) and the
     * drill-down listing (?guest_list_status=submitted) so the two can never
     * drift (the card once showed 11 while the list showed 9).
     *
     * A submitted guest list is counted regardless of the booking's after-sales
     * state: a COMPLETE booking can still have a guest list the customer
     * submitted, so there is deliberately NO AfterSalesService clause here. The
     * listing path must therefore suppress its default AfterSalesService='PENDING'
     * landing scope when this filter is active (see Booking_Model::apply_status_filter).
     *
     * Columns are qualified with `booking.` so the fragment is valid both in the
     * single-table card COUNT query and in the JOIN-heavy listing query.
     *
     * @return string A WHERE fragment (no leading AND/WHERE).
     */
    function guest_list_submitted_where()
    {
        return "booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'"
             . " AND booking.CancelStatus = 'N'"
             . " AND booking.Status != 'N'"
             . " AND booking.is_submitted = 1"
             . " AND booking.LockStatus = 'N'";
    }
}
