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
     * An optional `?day=YYYY-MM-DD` narrows the scope further: when a valid day
     * is given, every "(Month)" range collapses to that SINGLE day (month_start
     * == month_end == the day) while the "(Year)" range still follows the day's
     * year. The day drives its own month/year, so the picker stays in sync even
     * if the month param disagrees. `is_day` lets callers relabel the cards.
     *
     * @param string|null $param  Raw `month` query value, e.g. "2026-06".
     * @param string|null $today  Reference date "Y-m-d"; defaults to now.
     * @param string|null $day    Raw `day` query value, e.g. "2026-06-15".
     * @return array{
     *   year:int, month:int,
     *   month_start:string, month_end:string,
     *   year_start:string,  year_end:string,
     *   value:string, label:string,
     *   is_day:bool, day:string
     * }
     */
    function summary_resolve_month($param, $today = null, $day = null)
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

        // Optional day filter. A valid day drives its own month/year so the
        // picker follows it, and collapses the "(Month)" range to that day.
        $is_day  = false;
        $day_val = '';
        if (is_string($day) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($day), $d)) {
            $dy = (int) $d[1];
            $dm = (int) $d[2];
            $dd = (int) $d[3];
            // checkdate rejects impossible days (e.g. Feb 30); bound the year too.
            if ($dy >= 2000 && $dy <= 2100 && checkdate($dm, $dd, $dy)) {
                $is_day  = true;
                $day_val = sprintf('%04d-%02d-%02d', $dy, $dm, $dd);
                $year    = $dy;
                $month   = $dm;
            }
        }

        // First/last day of the selected month. Day-28 anchor + 't' avoids any
        // overflow when seeding from a 31-day reference date.
        $first = sprintf('%04d-%02d-01', $year, $month);
        // Month range collapses to the single day when a day filter is active.
        $month_start = $is_day ? $day_val : $first;
        $month_end   = $is_day ? $day_val : date('Y-m-t', strtotime($first));

        $year_start = sprintf('%04d-01-01', $year);
        $year_end   = sprintf('%04d-12-31', $year);

        $month_names = array(
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        );

        $label = $is_day
            ? ((int) date('j', strtotime($day_val)) . ' ' . $month_names[$month] . ' ' . $year)
            : ($month_names[$month] . ' ' . $year);

        return array(
            'year'        => $year,
            'month'       => $month,
            'month_start' => $month_start,
            'month_end'   => $month_end,
            'year_start'  => $year_start,
            'year_end'    => $year_end,
            'value'       => sprintf('%04d-%02d', $year, $month),
            'label'       => $label,
            'is_day'      => $is_day,
            'day'         => $day_val,
        );
    }
}

if (!function_exists('summary_resolve_owner_period')) {
    /**
     * Resolve the OWNER dashboard's global Day / Week / Month / Year toggle into
     * the single calendar range the whole per-agent matrix is scoped to
     * (Booking::owner_agent_matrix).
     *
     * Invalid / missing / unknown input falls back to $today's MONTH so a bad
     * query string can never break the dashboard. $today is injectable so the
     * resolver is deterministic under test; production passes date('Y-m-d').
     *
     * @param string|null $param  Raw `owner_period` query value: day|week|month|year.
     * @param string|null $today  Reference date "Y-m-d"; defaults to now.
     * @return array{
     *   period:string, start_date:string, end_date:string, label:string
     * }
     */
    function summary_resolve_owner_period($param, $today = null)
    {
        if ($today === null) {
            $today = date('Y-m-d');
        }

        $period = is_string($param) ? strtolower(trim($param)) : '';
        if (!in_array($period, array('day', 'week', 'month', 'year'), true)) {
            $period = 'month'; // default
        }

        $ts = strtotime($today);

        switch ($period) {
            case 'day':
                $start = $today;
                $end   = $today;
                $label = 'Today';
                break;

            case 'week':
                // ISO week: Monday..Sunday containing $today. 'monday this week'
                // resolves to the same Monday whether $today is mid-week or the
                // trailing Sunday.
                $start = date('Y-m-d', strtotime('monday this week', $ts));
                $end   = date('Y-m-d', strtotime('sunday this week', $ts));
                $label = 'This Week';
                break;

            case 'year':
                $start = date('Y-01-01', $ts);
                $end   = date('Y-12-31', $ts);
                $label = date('Y', $ts);
                break;

            case 'month':
            default:
                // Day-1 anchor + 't' avoids any overflow when seeding from a
                // 31-day reference date.
                $start = date('Y-m-01', $ts);
                $end   = date('Y-m-t', $ts);
                $label = date('F Y', $ts);
                break;
        }

        return array(
            'period'     => $period,
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => $label,
        );
    }
}
