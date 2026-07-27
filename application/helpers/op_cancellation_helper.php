<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * OP "Cancellations (Month)" card helpers.
 *
 * Backs the view-only monthly cancellation card shown to OP (40) and OP TEAM
 * LEAD (45) on the booking summary. The card lets them pick any month and see,
 * for the whole OP team's bookings, how many were cancelled and the rate.
 *
 * The counting rule matches the TC "Cancellation Rate (Month)" card exactly:
 *   - Only confirmed bookings (BOOKING CONFIRMATION), not drafts/quotations.
 *   - Rows cancelled as a DUPLICATE are dropped from BOTH the population and the
 *     cancelled count (see cancellation_rate_helper) — they are data-entry
 *     copies, not lost sales.
 *   - Scoped by booking.SalesAgent to the OP team, like every other OP card.
 *   - Counted by when the booking was CREATED (InsertDate), not when cancelled.
 *
 * Pure (no CI/DB) so the SQL and rate math can be unit tested under SQLite
 * :memory: (see tests/helpers/OpMonthlyCancellationCardTest.php).
 */

if (!function_exists('op_monthly_cancellation_sql')) {
    /**
     * Build the aggregate SQL for one month's OP-team cancellations.
     *
     * Returns three columns:
     *   - total       : the cancellation-rate population (confirmed BCs,
     *                   duplicates excluded) created in the month.
     *   - cancelled   : genuine cancellations within that population.
     *   - revenue_lost: SUM(NetTotal) of those genuine cancellations — the
     *                   booking value lost to cancellation (duplicates excluded,
     *                   so it never counts data-entry copies).
     *
     * Two bound placeholders remain for the month window (start, end) so the
     * caller passes CAST-able 'Y-m-d' dates. $sa_in_clause is an already-built
     * "booking.SalesAgent IN (...)" predicate (admin ids are ints from the admin
     * table, so the OP block inlines them safely) — pass '1=1' to skip scoping.
     *
     * @param string $sa_in_clause SalesAgent scope predicate, e.g. "booking.SalesAgent IN (3,4,5)".
     * @return string SQL with two positional placeholders (month start, month end).
     */
    function op_monthly_cancellation_sql($sa_in_clause = '1=1')
    {
        if (!function_exists('cancellation_rate_exclude_duplicate_clause')) {
            require_once __DIR__ . '/cancellation_rate_helper.php';
        }

        return "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN booking.CancelStatus = 'Y' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN booking.CancelStatus = 'Y' THEN booking.NetTotal ELSE 0 END) AS revenue_lost
                FROM booking
                WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
                  AND booking.Status != 'N'
                  AND " . cancellation_rate_exclude_duplicate_clause('booking') . "
                  AND ({$sa_in_clause})
                  AND DATE(booking.InsertDate) BETWEEN ? AND ?";
    }
}

if (!function_exists('op_cancellation_rate')) {
    /**
     * Cancellation rate as a percentage, rounded to one decimal.
     *
     * Matches the owner/TC cancellation cards' rounding. A zero (or negative)
     * population yields 0 so an empty month never divides by zero.
     *
     * @param int|float $cancelled genuine cancellations
     * @param int|float $total     population (duplicates already excluded)
     * @return float rate 0..100, one decimal place
     */
    function op_cancellation_rate($cancelled, $total)
    {
        $total = (float) $total;
        if ($total <= 0) {
            return 0.0;
        }
        return round(((float) $cancelled / $total) * 100, 1);
    }
}
