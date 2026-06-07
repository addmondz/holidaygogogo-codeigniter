<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Summary Period Helper
 *
 * Pure logic backing the Booking summary cards' month filter
 * (Booking::ajax_summary_cards). Given the `?month=YYYY-MM` request parameter,
 * it resolves the calendar ranges every "(Month)" and "(Year)" TC card is
 * scoped to. Kept free of CodeIgniter/DB so it can be unit tested under
 * SQLite :memory: (see tests/helpers/SummaryResolveMonthTest.php).
 *
 * The month filter re-scopes ALL the TC "(Month)" cards together (BC Created,
 * Total Sales, Cancellation, Conversion) plus the new "Year Sales vs Target"
 * card, so a single resolver feeds every range the controller needs.
 */

if (!function_exists('summary_resolve_month')) {
    /**
     * Resolve the selected reporting period from a `YYYY-MM` string.
     *
     * Invalid / missing / out-of-range input falls back to $today's month so a
     * bad query string can never break the dashboard. $today is injectable so
     * the resolver is deterministic under test; production passes date('Y-m-d').
     *
     * @param string|null $param  Raw `month` query value, e.g. "2026-06".
     * @param string|null $today  Reference date "Y-m-d"; defaults to now.
     * @return array{
     *   year:int, month:int,
     *   month_start:string, month_end:string,
     *   year_start:string,  year_end:string,
     *   value:string, label:string
     * }
     */
    function summary_resolve_month($param, $today = null)
    {
        if ($today === null) {
            $today = date('Y-m-d');
        }

        $year  = (int) date('Y', strtotime($today));
        $month = (int) date('n', strtotime($today));

        if (is_string($param) && preg_match('/^(\d{4})-(\d{2})$/', trim($param), $m)) {
            $py = (int) $m[1];
            $pm = (int) $m[2];
            // Bound to a sane window so a hand-typed query can't reach year 0.
            if ($py >= 2000 && $py <= 2100 && $pm >= 1 && $pm <= 12) {
                $year  = $py;
                $month = $pm;
            }
        }

        // First/last day of the selected month. Day-28 anchor + 't' avoids any
        // overflow when seeding from a 31-day reference date.
        $first = sprintf('%04d-%02d-01', $year, $month);
        $month_start = $first;
        $month_end   = date('Y-m-t', strtotime($first));

        $year_start = sprintf('%04d-01-01', $year);
        $year_end   = sprintf('%04d-12-31', $year);

        $month_names = array(
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        );

        return array(
            'year'        => $year,
            'month'       => $month,
            'month_start' => $month_start,
            'month_end'   => $month_end,
            'year_start'  => $year_start,
            'year_end'    => $year_end,
            'value'       => sprintf('%04d-%02d', $year, $month),
            'label'       => $month_names[$month] . ' ' . $year,
        );
    }
}
