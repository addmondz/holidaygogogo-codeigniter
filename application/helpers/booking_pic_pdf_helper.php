<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Canonical cutoff lives in constants.php (LEAD_CONVERSION_TC2_CUTOFF_DATE).
// Fallback for when this helper is loaded outside the CodeIgniter bootstrap
// (e.g. the standalone test runner).
if (!defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')) {
    define('LEAD_CONVERSION_TC2_CUTOFF_DATE', '2026-06-01');
}

if (!function_exists('pdf_show_booking_pic_for_date')) {
    /**
     * Whether a booking PDF should use the split TC layout introduced on
     * 2026-06-01: a "Booking PIC" line for TC1 (booking.SalesAgent) plus a
     * separate "Sales Agent" line for TC2 (booking.SalesAgent2).
     *
     * Bookings created on/after the cutoff use the split layout; earlier
     * bookings keep the single "Sales Agent" line for TC1. Empty/malformed
     * dates fall back to false so legacy records render the original layout.
     * Mirrors the TC1/TC2 attribution in lead_conversion_credit_helper.php.
     */
    function pdf_show_booking_pic_for_date($insert_date)
    {
        if (empty($insert_date)) {
            return false;
        }
        $doc_ts = strtotime($insert_date);
        $cutoff_ts = strtotime(LEAD_CONVERSION_TC2_CUTOFF_DATE);
        if ($doc_ts === false || $cutoff_ts === false) {
            return false;
        }
        return $doc_ts >= $cutoff_ts;
    }
}
