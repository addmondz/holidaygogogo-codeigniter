<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure layout logic behind the "Leads By Hour" report (Report/Leads_By_Hour).
 *
 * The report answers "what time of day do new leads get picked up?". The SQL
 * groups picked-up leads (ghl_lead_ownership, owner = assignee) by the DATE +
 * HOUR they were picked up and only returns hours that actually had leads, so
 * these DB-free helpers:
 *   - expand the picked range into every day (so empty days still show),
 *   - stitch the sparse (date, hour, count) rows into a date x 24-hour grid,
 *   - carry per-day totals, per-hour totals and a grand total,
 *   - label each hour in 12-hour clock form (12 AM, 9 AM, 12 PM, 11 PM).
 */

if ( ! function_exists('leads_by_hour_hour_label'))
{
    /**
     * 12-hour clock label for an hour-of-day (0..23). e.g. 0 => "12 AM",
     * 9 => "9 AM", 12 => "12 PM", 23 => "11 PM".
     */
    function leads_by_hour_hour_label($hour)
    {
        $hour = (int) $hour;
        if ($hour < 0 || $hour > 23) {
            return '';
        }
        $suffix = $hour < 12 ? 'AM' : 'PM';
        $display = $hour % 12;
        if ($display === 0) {
            $display = 12;
        }
        return $display . ' ' . $suffix;
    }
}

if ( ! function_exists('leads_by_hour_expand_dates'))
{
    /**
     * Inclusive ordered list of Y-m-d dates between $start and $end. A reversed
     * range is swapped (not emptied); invalid input returns an empty array so
     * the caller treats it as "nothing to show". $cap bounds the day count to
     * keep the grid sane (window ends on $end, keeps the most recent days).
     */
    function leads_by_hour_expand_dates($start, $end, $cap = 366)
    {
        $startTs = strtotime((string) $start);
        $endTs = strtotime((string) $end);
        if ($startTs === false || $endTs === false) {
            return array();
        }

        if ($startTs > $endTs) {
            $tmp = $startTs;
            $startTs = $endTs;
            $endTs = $tmp;
        }

        $dates = array();
        $cursor = strtotime(date('Y-m-d', $startTs));
        $last = strtotime(date('Y-m-d', $endTs));
        while ($cursor <= $last) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        $cap = (int) $cap;
        if ($cap > 0 && count($dates) > $cap) {
            $dates = array_slice($dates, -$cap);
        }

        return $dates;
    }
}

if ( ! function_exists('leads_by_hour_build_matrix'))
{
    /**
     * Build the date x hour grid.
     *
     * @param array $rows  sparse rows, each an array/object with keys
     *                     lead_date (Y-m-d), hour_of_day (0..23), lead_count.
     * @param array $dates ordered Y-m-d dates every row of the grid must show
     *                     (from leads_by_hour_expand_dates()).
     * @return array {
     *   hours:       [ {hour:int, label:string} x24 ],
     *   rows:        [ {date, date_label, counts:[int x24], total:int} ],
     *   hour_totals: [int x24],
     *   grand_total: int,
     * }
     */
    function leads_by_hour_build_matrix($rows, $dates)
    {
        $hours = array();
        for ($h = 0; $h < 24; $h++) {
            $hours[] = array('hour' => $h, 'label' => leads_by_hour_hour_label($h));
        }

        // One zero-filled bucket row per requested date, keyed for O(1) fill.
        $byDate = array();
        foreach ((array) $dates as $date) {
            $byDate[$date] = array_fill(0, 24, 0);
        }

        foreach ((array) $rows as $row) {
            $row = (array) $row;
            $date = isset($row['lead_date']) ? (string) $row['lead_date'] : '';
            $hour = isset($row['hour_of_day']) ? (int) $row['hour_of_day'] : -1;
            $count = isset($row['lead_count']) ? (int) $row['lead_count'] : 0;

            if ($hour < 0 || $hour > 23 || !isset($byDate[$date])) {
                continue;
            }
            $byDate[$date][$hour] += $count;
        }

        $hourTotals = array_fill(0, 24, 0);
        $grandTotal = 0;
        $outRows = array();
        foreach ($byDate as $date => $counts) {
            $rowTotal = 0;
            for ($h = 0; $h < 24; $h++) {
                $rowTotal += $counts[$h];
                $hourTotals[$h] += $counts[$h];
            }
            $grandTotal += $rowTotal;
            $outRows[] = array(
                'date' => $date,
                'date_label' => date('d M Y (D)', strtotime($date)),
                'counts' => $counts,
                'total' => $rowTotal,
            );
        }

        // Show the most recent day at the top of the grid.
        $outRows = array_reverse($outRows);

        return array(
            'hours' => $hours,
            'rows' => $outRows,
            'hour_totals' => $hourTotals,
            'grand_total' => $grandTotal,
        );
    }
}
