<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer Intake Helper
 *
 * Helpers for the customer-intake link feature: response-time computation,
 * formatting, and URL generation.
 */

if (!function_exists('compute_intake_pax_totals')) {
    /**
     * Sum the adult / child / baby counts across a set of customer-intake rooms.
     * Each room is an associative array with `adult_count` (int) and the
     * comma-separated `child_ages` / `baby_ages` strings produced by the
     * Customer_Intake controller.
     *
     * Pure (no DB), so the model's downstream-materialisation step can be unit
     * tested without bootstrapping CodeIgniter.
     *
     * @param array $rooms List of intake-room arrays.
     * @return array { adult: int, child: int, baby: int }
     */
    function compute_intake_pax_totals(array $rooms)
    {
        $adult = 0;
        $child = 0;
        $baby  = 0;
        foreach ($rooms as $room) {
            if (!is_array($room)) { continue; }
            if (isset($room['adult_count'])) {
                $adult += max(0, (int) $room['adult_count']);
            }
            $child += _intake_count_ages(isset($room['child_ages']) ? $room['child_ages'] : null);
            $baby  += _intake_count_ages(isset($room['baby_ages'])  ? $room['baby_ages']  : null);
        }
        return array(
            'adult' => $adult,
            'child' => $child,
            'baby'  => $baby,
        );
    }
}

if (!function_exists('_intake_count_ages')) {
    /**
     * Count entries in a comma-separated age list, tolerating null/whitespace.
     * Internal helper to compute_intake_pax_totals(); not part of the public API.
     */
    function _intake_count_ages($input)
    {
        if ($input === null || $input === '') {
            return 0;
        }
        if (is_array($input)) {
            $parts = $input;
        } else {
            $parts = explode(',', (string) $input);
        }
        $count = 0;
        foreach ($parts as $p) {
            if (trim((string) $p) !== '') { $count++; }
        }
        return $count;
    }
}

if (!function_exists('compute_response_seconds')) {
    /**
     * Pure subtraction between two MySQL DATETIME strings, returning the gap in
     * seconds. Returns null when either input is missing or unparseable. Split
     * out from calculate_submitted_to_payment_seconds() so the maths can be
     * unit tested without a database.
     *
     * @param string|null $submitted_at MySQL DATETIME (e.g. '2026-05-22 09:00:00')
     * @param string|null $finalised_at MySQL DATETIME
     * @return int|null Seconds elapsed, or null if either side is missing.
     */
    function compute_response_seconds($submitted_at, $finalised_at)
    {
        if (empty($submitted_at) || empty($finalised_at)) {
            return null;
        }
        $t1 = strtotime($submitted_at);
        $t2 = strtotime($finalised_at);
        if ($t1 === false || $t2 === false) {
            return null;
        }
        return (int) ($t2 - $t1);
    }
}

if (!function_exists('calculate_submitted_to_payment_seconds')) {
    /**
     * Compute the response time (in seconds) for a booking:
     * gap between the customer intake submission and the earliest
     * booking_status_log entry advancing the booking TO P ("PENDING PAYMENT").
     *
     * This is the per-booking twin of the "Submitted -> Payment Time" summary
     * card (submitted_payment_avg_response_sql), so the inline "Response:" value
     * on the booking list / detail banner reconciles row-for-row with the card.
     *
     * Only TRANSITIONS into P count (from_status IS NOT NULL) so a booking that
     * happened to be created directly at P (a creation row, from_status NULL)
     * isn't mistaken for an advance into payment.
     *
     * @param int $booking_id
     * @return int|null Seconds elapsed, or null when either timestamp is missing
     *                  (e.g. the booking hasn't reached PENDING PAYMENT yet).
     */
    function calculate_submitted_to_payment_seconds($booking_id)
    {
        $CI =& get_instance();
        $CI->load->database();

        $submitted_row = $CI->db
            ->select('submitted_at')
            ->from('booking_customer_intake')
            ->where('booking_id', (int) $booking_id)
            ->get()->row();
        if (empty($submitted_row) || empty($submitted_row->submitted_at)) {
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

        return compute_response_seconds($submitted_row->submitted_at, $payment_row->created_at);
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

if (!function_exists('customer_intake_response_window_sql_fragment')) {
    /**
     * Subquery that returns the earliest `booking_status_log` row per booking
     * that TRANSITIONS into PBC (`to_status='PBC' AND from_status IS NOT NULL`).
     * The from_status guard skips the draft CREATION row (PBC, from_status NULL)
     * that predates the customer's submission. Reused by both the average-response
     * SQL and the best-agent SQL so the join shape is identical.
     *
     * @return string
     */
    function customer_intake_response_window_sql_fragment()
    {
        return "(
            SELECT booking_id, MIN(created_at) AS first_pbc_at
            FROM booking_status_log
            WHERE to_status = 'PBC' AND from_status IS NOT NULL
            GROUP BY booking_id
        )";
    }
}

if (!function_exists('customer_intake_avg_response_sql')) {
    /**
     * SQL for the "Intake -> BC Response Time (Month)" summary card.
     *
     * Returns one row with `avg_seconds` and `n`. Bind two placeholders for
     * the window (`submitted_at >= ? AND submitted_at < ?`). When
     * `$with_credit_clause` is true, two extra placeholders are appended for
     * the admin_id (bound twice, matching `lead_conversion_credit_booking_clause`).
     *
     * Uses UNIX_TIMESTAMP rather than TIMESTAMPDIFF so a SQLite UDF can stand
     * in for the function in unit tests — MySQL has UNIX_TIMESTAMP natively.
     *
     * @param bool $with_credit_clause Apply the TC1/TC2 credit filter so the
     *                                 result is scoped to a single admin.
     * @return string
     */
    function customer_intake_avg_response_sql($with_credit_clause = false)
    {
        $pbc_join = customer_intake_response_window_sql_fragment();
        $sql = "SELECT
                    AVG(UNIX_TIMESTAMP(pbc.first_pbc_at) - UNIX_TIMESTAMP(ci.submitted_at)) AS avg_seconds,
                    COUNT(*) AS n
                FROM booking_customer_intake ci
                JOIN booking b ON b.BookingID = ci.booking_id
                JOIN {$pbc_join} pbc ON pbc.booking_id = ci.booking_id
                WHERE ci.submitted_at >= ? AND ci.submitted_at < ?
                  AND b.Status != 'N'";
        if ($with_credit_clause) {
            // lead_conversion_credit_booking_clause() returns its own pair of
            // placeholders and references `booking.SalesAgent`/`SalesAgent2`,
            // so it lines up with the `JOIN booking b` above via the FK.
            $credit = lead_conversion_credit_booking_clause();
            // Swap the qualifier so the clause matches our `b` alias.
            $credit = str_replace('booking.', 'b.', $credit);
            $sql .= " AND " . $credit;
        }
        return $sql;
    }
}

if (!function_exists('customer_intake_best_agent_sql')) {
    /**
     * SQL for the "Best:" footer on the response-time card. Groups by the
     * credited admin (TC1 pre-cutoff, TC2 on/after), filters to agents with
     * at least 2 qualifying bookings (mirrors the Conversion Rate card's
     * min-sample guard), and returns the agent with the lowest average.
     *
     * Bind two placeholders for the window. Returns one row with `AdminID`,
     * `avg_seconds`, `n` — or zero rows when nobody qualifies.
     *
     * @return string
     */
    function customer_intake_best_agent_sql()
    {
        $pbc_join = customer_intake_response_window_sql_fragment();
        $agent_expr = lead_conversion_credit_agent_expr('b');
        return "SELECT
                    {$agent_expr} AS AdminID,
                    AVG(UNIX_TIMESTAMP(pbc.first_pbc_at) - UNIX_TIMESTAMP(ci.submitted_at)) AS avg_seconds,
                    COUNT(*) AS n
                FROM booking_customer_intake ci
                JOIN booking b ON b.BookingID = ci.booking_id
                JOIN {$pbc_join} pbc ON pbc.booking_id = ci.booking_id
                WHERE ci.submitted_at >= ? AND ci.submitted_at < ?
                  AND b.Status != 'N'
                  AND {$agent_expr} IS NOT NULL
                GROUP BY {$agent_expr}
                HAVING n >= 2
                ORDER BY avg_seconds ASC
                LIMIT 1";
    }
}

if (!function_exists('generate_customer_intake_url')) {
    /**
     * Build the public customer-intake URL for a booking token.
     *
     * @param string $token booking.Token
     * @return string
     */
    function generate_customer_intake_url($token)
    {
        if (empty($token)) {
            return '';
        }
        return base_url('customer-intake/' . urlencode($token));
    }
}
