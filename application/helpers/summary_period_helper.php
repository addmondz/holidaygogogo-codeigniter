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
     * The `?day=YYYY-MM-DD` picker defaults to today when absent/invalid, so
     * `day` is always a concrete date the front-end can show. Viewing TODAY is
     * the whole-month default (`is_day` false, month range stays the whole
     * month). Any OTHER valid day collapses every "(Month)" range to that SINGLE
     * day (month_start == month_end == the day) while the "(Year)" range still
     * follows the day's year, and drives its own month/year so the picker stays
     * in sync even if the month param disagrees. `is_day` (true only for a
     * specific non-today day) lets callers relabel / gate the Clear control.
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

        // Day filter. Defaults to today so the picker always shows a concrete
        // date. Viewing TODAY is the live whole-month default: is_day stays false
        // and the "(Month)" range keeps the whole month. Any OTHER valid day sets
        // is_day, drives its own month/year (so the picker and Year card follow
        // it), and collapses the "(Month)" range to that single day. Invalid /
        // missing input falls back to today.
        $is_day  = false;
        $day_val = $today;
        if (is_string($day) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($day), $d)) {
            $dy = (int) $d[1];
            $dm = (int) $d[2];
            $dd = (int) $d[3];
            // checkdate rejects impossible days (e.g. Feb 30); bound the year too.
            if ($dy >= 2000 && $dy <= 2100 && checkdate($dm, $dd, $dy)) {
                $day_val = sprintf('%04d-%02d-%02d', $dy, $dm, $dd);
                // Only a day OTHER than today collapses the month cards; today is
                // the whole-month default.
                if ($day_val !== $today) {
                    $is_day = true;
                    $year   = $dy;
                    $month  = $dm;
                }
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

if (!function_exists('summary_agent_triplet_ranges')) {
    /**
     * Resolve the Today / Week / Month triplet ranges for the sales-agent
     * summary cards around an anchor day.
     *
     * The sales-agent card set (New Leads, Daily Handle, Avg Reply Time,
     * Outbound) shows three rolling columns. When the day picker (?day=) is
     * active the controller passes the picked day as the anchor, so:
     *   - Today  = the picked day
     *   - Week   = Monday..Sunday of the picked day's week
     *   - Month  = 1st..last day of the picked day's month
     * With no day picked the controller passes today, keeping the triplet live.
     *
     * Pure date math (no CI/DB) so it can be unit tested under SQLite :memory:
     * (see tests/helpers/SummaryAgentTripletRangesTest.php).
     *
     * @param string $anchor_day  "Y-m-d" the triplet is centred on.
     * @return array{
     *   day:string,
     *   week_start:string, week_end:string,
     *   month_start:string, month_end:string
     * }
     */
    function summary_agent_triplet_ranges($anchor_day)
    {
        $ts = strtotime($anchor_day);

        return array(
            'day'         => date('Y-m-d', $ts),
            // ISO week: Monday..Sunday containing the anchor. 'monday this week'
            // resolves to the same Monday mid-week or on the trailing Sunday.
            'week_start'  => date('Y-m-d', strtotime('monday this week', $ts)),
            'week_end'    => date('Y-m-d', strtotime('sunday this week', $ts)),
            // Day-1 anchor + 't' avoids overflow when seeding from a 31-day date.
            'month_start' => date('Y-m-01', $ts),
            'month_end'   => date('Y-m-t', $ts),
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
        if (!in_array($period, array('yesterday', 'day', 'week', 'month', 'year', 'lastyear'), true)) {
            $period = 'month'; // default
        }

        $ts = strtotime($today);

        // $base is the column-granularity the owner matrix uses to decide which
        // columns apply (Booking::owner_agent_matrix's $is_dwm/$is_my/$is_year).
        // The two extra toggles reuse an existing granularity: "Yesterday" acts
        // like a single Day, "Same period last year" like a Year.
        $base = $period;

        switch ($period) {
            case 'yesterday':
                $start = date('Y-m-d', strtotime('-1 day', $ts));
                $end   = $start;
                $label = 'Yesterday';
                $base  = 'day';
                break;

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

            case 'lastyear':
                // "Same period last year": this year's to-date span shifted back
                // exactly one year (Jan 1 .. today, one year earlier), so the
                // owner can compare against a like-for-like window. Reuses the
                // sales cards' prior-year math.
                $prior = summary_prior_year_window(
                    date('Y-01-01', $ts), date('Y-12-31', $ts), $today
                );
                $start = $prior['start'];
                $end   = $prior['end'];
                $label = 'Same Period Last Year (' . date('Y', strtotime('-1 year', $ts)) . ')';
                $base  = 'year';
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
            'base'       => $base,
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => $label,
        );
    }
}

if (!function_exists('summary_prior_year_window')) {
    /**
     * Resolve the "same period last year" comparison window for a sales card.
     *
     * The sales cards' current figure is effectively "to date": the actual is
     * summed over the whole selected period, but no bookings exist in the
     * future, so a current month/year figure only covers up to today. To keep
     * the year-over-year comparison apples-to-apples we shift the SAME to-date
     * span back exactly one year:
     *   - effective end = min(period end, today)  (clamp the future off)
     *   - last-year window = [start - 1yr, effective end - 1yr]
     *
     * Examples (today = 2026-07-03):
     *   month card, current July  -> current span 2026-07-01..2026-07-03,
     *                                last year     2025-07-01..2025-07-03
     *   year card, current 2026   -> current span 2026-01-01..2026-07-03,
     *                                last year     2025-01-01..2025-07-03
     *   a fully-past month (May)  -> full month vs full same month last year
     *
     * Shifting back a year keeps the month/day and subtracts 1 from the year;
     * Feb 29 (no such day the prior year) clamps to Feb 28 so the window stays
     * valid. Pure date math (no CI/DB) so it unit tests under SQLite :memory:
     * (see tests/helpers/SummaryPriorYearWindowTest.php).
     *
     * @param string $start  Current period start "Y-m-d".
     * @param string $end    Current period end   "Y-m-d".
     * @param string $today  Reference date       "Y-m-d".
     * @return array{start:string, end:string}
     */
    function summary_prior_year_window($start, $end, $today)
    {
        // Clamp the current window to today so a partway-through month/year is
        // compared against the same number of days last year, not the full one.
        $eff_end = ($end < $today) ? $end : $today;

        return array(
            'start' => summary_shift_back_one_year($start),
            'end'   => summary_shift_back_one_year($eff_end),
        );
    }
}

if (!function_exists('summary_shift_back_one_year')) {
    /**
     * Subtract exactly one year from a "Y-m-d" date, keeping the month/day.
     * Feb 29 clamps to Feb 28 (the prior year has no 29th). Pure helper for
     * summary_prior_year_window(); not a general date library.
     *
     * @param string $date  "Y-m-d"
     * @return string       "Y-m-d" one year earlier
     */
    function summary_shift_back_one_year($date)
    {
        $y = (int) substr($date, 0, 4);
        $m = (int) substr($date, 5, 2);
        $d = (int) substr($date, 8, 2);
        $py = $y - 1;

        if (!checkdate($m, $d, $py)) {
            // Only reachable for Feb 29 -> clamp to last day of Feb that year.
            $d = (int) date('t', strtotime(sprintf('%04d-%02d-01', $py, $m)));
        }

        return sprintf('%04d-%02d-%02d', $py, $m, $d);
    }
}
