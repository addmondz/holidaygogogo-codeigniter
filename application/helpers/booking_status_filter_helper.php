<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Status Filter Helper
 *
 * Single source of truth for the booking-list status filter, shared by the
 * on-screen list (Booking_Model::apply_booking_filters) and the download
 * spreadsheets (Booking_Model::Read_Bookings_With_Guest_Lists).
 *
 * The Status filter on /Booking is a Bootstrap multi-select that submits a
 * comma-joined string (e.g. status=OG,Y). Each side previously had its own
 * copy of the per-status SQL; the download copy did naive equality checks
 * (status == 'A' ...) which never matched a comma-joined value, so the
 * default CancelStatus='N' guard was skipped and cancelled rows leaked into
 * the spreadsheet. This helper avoids the divergence by computing the WHERE
 * fragment from one place.
 */

if (!function_exists('booking_status_filter_default_where')) {
    function booking_status_filter_default_where()
    {
        return "CancelStatus = 'N' AND AfterSalesService = 'PENDING'";
    }
}

if (!function_exists('payment_overdue_cutoff_date')) {
    /**
     * Reference date for the "payment overdue" comparison `deadline < cutoff`.
     *
     * Business rule: a payment due TODAY becomes overdue once the clock passes
     * 3:00pm. Before 3pm the cutoff is today (only deadlines strictly before
     * today are overdue); from 3pm onward the cutoff rolls forward to tomorrow,
     * which pulls today's deadlines into the overdue set. This keeps the
     * dashboard Payment Overdue card and the booking list's PO status filter in
     * agreement (both derive their cutoff from here).
     *
     * $now is injectable (a "Y-m-d H:i:s" string or a unix timestamp) so the
     * rule can be unit-tested deterministically; production passes nothing and
     * uses the current time.
     *
     * @param string|int|null $now
     * @return string  Cutoff date as 'Y-m-d'.
     */
    function payment_overdue_cutoff_date($now = null)
    {
        $ts = ($now === null)
            ? time()
            : (is_numeric($now) ? (int) $now : strtotime((string) $now));
        $hour = (int) date('G', $ts); // 24-hour, no leading zero
        if ($hour >= 15) {
            return date('Y-m-d', strtotime('+1 day', $ts));
        }
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('booking_status_filter_per_status_clauses')) {
    /**
     * Returns the WHERE-clause fragments (to be AND-ed) for a single status
     * code from the multi-select. Unknown codes return an empty array.
     */
    function booking_status_filter_per_status_clauses($status, $today, $overdue_cutoff = null)
    {
        // Overdue (PO) compares against the 3pm-aware cutoff; falls back to
        // $today so existing two-arg callers keep the legacy "< today" rule.
        $po_cut = ($overdue_cutoff !== null && $overdue_cutoff !== '') ? $overdue_cutoff : $today;
        switch ($status) {
            case 'A':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status != 'N'",
                ];
            case 'C':
                return [
                    "CancelStatus = 'Y'",
                    "booking.Status != 'N'",
                ];
            case 'Y':
                return [
                    "CancelStatus = 'N'",
                    "AfterSalesService = 'COMPLETE'",
                    "booking.Status = 'Y'",
                ];
            case 'OG':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'OG'",
                ];
            case 'PP':
                return [
                    "CancelStatus = 'N'",
                    "FullPaymentDeadline >= '" . $today . "'",
                    "booking.Status = 'PP'",
                ];
            case 'PO':
                // Mirrors apply_booking_filters: a P/PP row only counts as PO
                // when there's still outstanding balance (NetTotal > approved
                // credits) -- without this, fully-paid PP rows past their
                // deadline display as "PARTIAL PAYMENT" but still appear under
                // the PO filter.
                $approved_credit_sql = "COALESCE((SELECT SUM(p.Credit) FROM payment p"
                    . " WHERE p.BookingID = booking.BookingID"
                    . " AND p.Status = 'Y' AND p.Credit > 0"
                    . " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0)";
                return [
                    "CancelStatus = 'N'",
                    "(((booking.FullPaymentDeadline < '" . $po_cut . "' AND booking.Status IN ('P','PP'))"
                    . " OR (booking.DepositDeadline < '" . $po_cut . "' AND booking.Status = 'P'))"
                    . " AND (booking.NetTotal - " . $approved_credit_sql . ") > 0)",
                ];
            case 'PGL':
                return [
                    "CancelStatus = 'N'",
                    "LockStatus = 'N'",
                    "booking.Status IN ('PGL', 'PTV')",
                ];
            case 'PBC':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'PBC'",
                ];
            case 'PB':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'PB'",
                ];
            case 'SAD':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'SAD'",
                ];
            case 'P':
                return [
                    "CancelStatus = 'N'",
                    "(DepositDeadline >= '" . $today . "' OR (DepositDeadline IS NULL AND FullPaymentDeadline >= '" . $today . "'))",
                    "booking.Status = 'P'",
                ];
            case 'PR':
                return [
                    "CancelStatus = 'N'",
                    "AfterSalesService = 'PENDING'",
                    "booking.Status = 'Y'",
                ];
            case 'PT':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'PT'",
                ];
            case 'PTV':
                return [
                    "CancelStatus = 'N'",
                    "LockStatus = 'Y'",
                    "booking.Status IN ('PGL', 'PTV')",
                ];
            case 'PBO':
                return [
                    "CancelStatus = 'N'",
                    "booking.Status = 'PBO'",
                ];
            default:
                return [];
        }
    }
}

if (!function_exists('booking_status_filter_full_where')) {
    /**
     * Builds the full WHERE fragment for a status-filter input value.
     * - Empty input: default branch (CancelStatus='N' AND AfterSalesService='PENDING').
     * - Single status: one parenthesized AND group.
     * - Multi status (comma-separated): OR-joined AND groups.
     * - Whitespace around tokens is tolerated.
     * - All-unknown tokens fall back to the default branch so cancelled rows
     *   never leak.
     */
    function booking_status_filter_full_where($status_param, $today, $overdue_cutoff = null)
    {
        if ($status_param === null || $status_param === '') {
            return booking_status_filter_default_where();
        }
        $statuses = array_map('trim', explode(',', $status_param));
        $or_parts = [];
        foreach ($statuses as $s) {
            if ($s === '') {
                continue;
            }
            $clauses = booking_status_filter_per_status_clauses($s, $today, $overdue_cutoff);
            if (!empty($clauses)) {
                $or_parts[] = '(' . implode(' AND ', $clauses) . ')';
            }
        }
        if (empty($or_parts)) {
            return booking_status_filter_default_where();
        }
        return '(' . implode(' OR ', $or_parts) . ')';
    }
}
