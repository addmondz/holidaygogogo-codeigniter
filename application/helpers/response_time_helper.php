<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Response Time Helper
 *
 * Per-booking response-time computation + formatting behind the
 * "Draft -> Payment Time" metric:
 *
 *   START : the booking is saved as draft -> earliest booking_status_log row
 *           advancing the booking TO SAD ("SAVE AS DRAFT")
 *   END   : the booking first reaches P ("PENDING PAYMENT") -> earliest
 *           TRANSITION into P (from_status NOT NULL)
 *
 * The pure helpers (compute_response_seconds, format_response_duration) are kept
 * DB-free so they can be unit tested without bootstrapping CodeIgniter. The
 * aggregate "(Month)" summary cards live in submitted_payment_response_helper.php
 * and stay anchored on the same SAD -> P window so the inline per-booking value
 * reconciles row-for-row with the card.
 */

if (!function_exists('compute_response_seconds')) {
    /**
     * Pure subtraction between two MySQL DATETIME strings, returning the gap in
     * seconds. Returns null when either input is missing or unparseable. Split
     * out from calculate_submitted_to_payment_seconds() so the maths can be
     * unit tested without a database.
     *
     * @param string|null $started_at  MySQL DATETIME (e.g. '2026-05-22 09:00:00')
     * @param string|null $finalised_at MySQL DATETIME
     * @return int|null Seconds elapsed, or null if either side is missing.
     */
    function compute_response_seconds($started_at, $finalised_at)
    {
        if (empty($started_at) || empty($finalised_at)) {
            return null;
        }
        $t1 = strtotime($started_at);
        $t2 = strtotime($finalised_at);
        if ($t1 === false || $t2 === false) {
            return null;
        }
        return (int) ($t2 - $t1);
    }
}

if (!function_exists('calculate_submitted_to_payment_seconds')) {
    /**
     * Compute the response time (in seconds) for a booking: gap between the
     * booking being saved as draft (earliest booking_status_log row TO SAD) and
     * the earliest row advancing the booking TO P ("PENDING PAYMENT").
     *
     * This is the per-booking twin of the "Draft -> Payment Time" summary card
     * (submitted_payment_avg_response_sql), so the inline "Response:" value on
     * the booking list / detail reconciles row-for-row with the card.
     *
     * Only TRANSITIONS into P count (from_status IS NOT NULL) so a booking that
     * happened to be created directly at P (a creation row, from_status NULL)
     * isn't mistaken for an advance into payment.
     *
     * @param int $booking_id
     * @return int|null Seconds elapsed, or null when either timestamp is missing
     *                  (e.g. the booking wasn't saved as draft, or hasn't reached
     *                  PENDING PAYMENT yet).
     */
    function calculate_submitted_to_payment_seconds($booking_id)
    {
        $CI =& get_instance();
        $CI->load->database();

        $draft_row = $CI->db
            ->select('MIN(created_at) AS started_at')
            ->from('booking_status_log')
            ->where('booking_id', (int) $booking_id)
            ->where('to_status', 'SAD')
            ->get()->row();
        if (empty($draft_row) || empty($draft_row->started_at)) {
            return null;
        }

        $payment_row = $CI->db
            ->select('created_at')
            ->from('booking_status_log')
            ->where('booking_id', (int) $booking_id)
            ->where('to_status', 'P')
            ->where('from_status IS NOT NULL', null, false)
            ->order_by('created_at', 'ASC')
            ->limit(1)
            ->get()->row();
        if (empty($payment_row) || empty($payment_row->created_at)) {
            return null;
        }

        return compute_response_seconds($draft_row->started_at, $payment_row->created_at);
    }
}

if (!function_exists('format_response_duration')) {
    /**
     * Format a duration in seconds as a compact human-readable string.
     *
     *   null            -> '-'
     *   negative        -> '-'   (defensive: clock skew shouldn't surface to UI)
     *   0..59           -> 'Xs'
     *   60..3599        -> 'Xm'
     *   3600+           -> 'Xh Ym'
     *
     * @param int|null $seconds
     * @return string
     */
    function format_response_duration($seconds)
    {
        if ($seconds === null || !is_numeric($seconds) || $seconds < 0) {
            return '-';
        }
        $seconds = (int) $seconds;
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60) . 'm';
        }
        $hours = (int) floor($seconds / 3600);
        $mins  = (int) floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $mins . 'm';
    }
}
