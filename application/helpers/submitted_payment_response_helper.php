<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Submitted -> Payment Response Helper
 *
 * Pure SQL builders behind the "Submitted -> Payment Time" summary card
 * (Booking::ajax_summary_cards). The card measures how long, after a customer
 * submits their intake, the booking takes to reach the payment stage:
 *
 *   START : the customer submits the intake -> booking_customer_intake.submitted_at
 *   END   : the booking first reaches P ("PENDING PAYMENT") -> earliest TRANSITION into P
 *
 * The START anchor is intentionally the same `submitted_at` the existing
 * "Intake -> BC Response Time" card uses (see customer_intake_helper.php), so
 * the two response-time metrics are standardised on one starting point — this
 * card just extends the window to the payment milestone instead of PBC.
 *
 * Only bookings that have BOTH a submission and a P transition are counted
 * (inner joins), so an intake still working its way through SAD/PB/PBC simply
 * isn't in the average yet. The window is on the START anchor (submitted_at),
 * mirroring the intake-response card, so the metric is "intakes submitted in
 * this period and the time they took to reach payment".
 *
 * Kept free of CodeIgniter/DB so it can be unit tested under SQLite :memory:
 * (see tests/helpers/SubmittedPaymentResponseSqlTest.php). Uses UNIX_TIMESTAMP
 * so a SQLite UDF can stand in for MySQL's native function under test.
 *
 * Depends on lead_conversion_credit_helper for the TC1/TC2 credited-slot
 * attribution (load it before these in the controller), so the TC variant and
 * the "Best agent" footer agree with the other credited-slot summary cards.
 */

if (!function_exists('submitted_payment_pending_window_sql_fragment')) {
    /**
     * Subquery: earliest `booking_status_log` row per booking that TRANSITIONS
     * into P ("PENDING PAYMENT"). The from_status guard skips any CREATION row
     * (to_status='P', from_status NULL) so only a genuine advance into payment
     * counts as the end anchor.
     *
     * @return string
     */
    function submitted_payment_pending_window_sql_fragment()
    {
        return "(
            SELECT booking_id, MIN(created_at) AS first_p_at
            FROM booking_status_log
            WHERE to_status = 'P' AND from_status IS NOT NULL
            GROUP BY booking_id
        )";
    }
}

if (!function_exists('submitted_payment_avg_response_sql')) {
    /**
     * SQL for the "Submitted -> Payment Time" summary card.
     *
     * Returns one row with `avg_seconds` and `n`. Bind two placeholders for the
     * window on the submission anchor (`submitted_at >= ? AND submitted_at <
     * ?`). When `$with_credit_clause` is true, two extra placeholders are
     * appended for the admin_id (bound twice, matching
     * `lead_conversion_credit_booking_clause`).
     *
     * @param bool $with_credit_clause Apply the TC1/TC2 credit filter so the
     *                                 result is scoped to a single admin.
     * @return string
     */
    function submitted_payment_avg_response_sql($with_credit_clause = false)
    {
        $pay_join = submitted_payment_pending_window_sql_fragment();
        $sql = "SELECT
                    AVG(UNIX_TIMESTAMP(p.first_p_at) - UNIX_TIMESTAMP(ci.submitted_at)) AS avg_seconds,
                    COUNT(*) AS n
                FROM booking_customer_intake ci
                JOIN booking b ON b.BookingID = ci.booking_id
                JOIN {$pay_join} p ON p.booking_id = ci.booking_id
                WHERE ci.submitted_at >= ? AND ci.submitted_at < ?
                  AND b.Status != 'N'";
        if ($with_credit_clause) {
            // lead_conversion_credit_booking_clause() returns its own pair of
            // placeholders and references `booking.SalesAgent`/`SalesAgent2`;
            // swap the qualifier so the clause matches our `b` alias.
            $credit = lead_conversion_credit_booking_clause();
            $credit = str_replace('booking.', 'b.', $credit);
            $sql .= " AND " . $credit;
        }
        return $sql;
    }
}

if (!function_exists('submitted_payment_best_agent_sql')) {
    /**
     * SQL for the "Best:" footer on the Submitted -> Payment Time card. Groups
     * by the credited admin (TC1 pre-cutoff, TC2 on/after), keeps only agents
     * with at least 2 qualifying bookings (min-sample guard shared with the
     * other cards), and returns the agent with the lowest (fastest) average.
     *
     * Bind two placeholders for the window. Returns one row with `AdminID`,
     * `avg_seconds`, `n` — or zero rows when nobody qualifies.
     *
     * @return string
     */
    function submitted_payment_best_agent_sql()
    {
        $pay_join   = submitted_payment_pending_window_sql_fragment();
        $agent_expr = lead_conversion_credit_agent_expr('b');
        return "SELECT
                    {$agent_expr} AS AdminID,
                    AVG(UNIX_TIMESTAMP(p.first_p_at) - UNIX_TIMESTAMP(ci.submitted_at)) AS avg_seconds,
                    COUNT(*) AS n
                FROM booking_customer_intake ci
                JOIN booking b ON b.BookingID = ci.booking_id
                JOIN {$pay_join} p ON p.booking_id = ci.booking_id
                WHERE ci.submitted_at >= ? AND ci.submitted_at < ?
                  AND b.Status != 'N'
                  AND {$agent_expr} IS NOT NULL
                GROUP BY {$agent_expr}
                HAVING n >= 2
                ORDER BY avg_seconds ASC
                LIMIT 1";
    }
}
