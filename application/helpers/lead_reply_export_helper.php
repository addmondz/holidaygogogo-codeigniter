<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for the Lead Reply Activity Excel export.
 *
 * The on-screen dashboard is a DAILY report (one date, per-owner rows). The
 * export instead accepts a date RANGE and lays the days out as rows, with one
 * column group per owner. Each owner group carries the same five metrics shown
 * on the dashboard: New Lead Picked Up, Lead Responded, Transfer Out, Today
 * Handling, and Avg Response Time. Each day is still computed with the exact
 * daily definition -- the controller calls the daily model query once per day
 * and hands the per-day rows here to be stitched into the owner x day matrix.
 *
 * Everything here is DB-free so it can be unit-tested without a database.
 */

if ( ! function_exists('lead_reply_export_duration_label'))
{
    /**
     * Format a duration in seconds the same way the dashboard's Avg Response
     * Time column does (mirror of Report::format_duration_label), so the export
     * total row reads identically. DB-free for unit testing.
     */
    function lead_reply_export_duration_label($seconds)
    {
        if ($seconds === null || $seconds === '') {
            return '-';
        }

        $seconds = (int) $seconds;

        if ($seconds < 60) {
            return $seconds . ' sec';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . ' min';
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);

            if ($minutes == 0) {
                return $hours . ' hr';
            }

            return $hours . ' hr ' . $minutes . ' min';
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);

        if ($hours == 0) {
            return $days . ' day';
        }

        return $days . ' day ' . $hours . ' hr';
    }
}

if ( ! function_exists('lead_reply_export_dates'))
{
    /**
     * Expand an inclusive 'Y-m-d' range into the list of days to export.
     *
     * - Reversed ranges (start after end) are swapped so the caller can't get
     *   an empty export by picking the dates the "wrong" way round.
     * - Capped at $maxDays days to stop a runaway range spawning hundreds of
     *   per-day queries. When the cap bites, the EARLIEST days are kept (the
     *   window ends on $end) and the caller is expected to surface the cap.
     *
     * @return array list of 'Y-m-d' strings (empty when either date is invalid)
     */
    function lead_reply_export_dates($start, $end, $maxDays = 92)
    {
        $startTs = strtotime((string) $start);
        $endTs   = strtotime((string) $end);
        if ($startTs === false || $endTs === false) {
            return array();
        }

        $startDay = strtotime(date('Y-m-d', $startTs));
        $endDay   = strtotime(date('Y-m-d', $endTs));
        if ($startDay > $endDay) {
            $tmp = $startDay; $startDay = $endDay; $endDay = $tmp;
        }

        $dates = array();
        for ($ts = $startDay; $ts <= $endDay; $ts = strtotime('+1 day', $ts)) {
            $dates[] = date('Y-m-d', $ts);
        }

        $maxDays = max(1, (int) $maxDays);
        if (count($dates) > $maxDays) {
            // Keep the most recent $maxDays days (window ends on $end).
            $dates = array_slice($dates, -$maxDays);
        }

        return $dates;
    }
}

if ( ! function_exists('lead_reply_export_build_matrix'))
{
    /**
     * Stitch per-day rows into an owner x day matrix.
     *
     * @param array $dates      ordered list of 'Y-m-d' export days
     * @param array $perDayRows map of 'Y-m-d' => list of formatted daily rows,
     *                          each row having owner_user_id, owner_name,
     *                          new_leads_picked_up, lead_responded,
     *                          transfer_out_leads, helped_reply_leads,
     *                          today_handling_leads,
     *                          avg_response_time_seconds, avg_response_time_label
     * @return array {
     *     owners: list of [owner_user_id, owner_name, total_picked_up,
     *                      total_responded, total_transfer_out, total_helped,
     *                      total_handling, avg_response_time_label] sorted by
     *                      total_responded desc then owner_name asc,
     *     lookup: owner_user_id => ('Y-m-d' => [picked_up, responded,
     *                      transfer_out, helped, handling, avg_response_time_label])
     * }
     *
     * Cell metric order mirrors the on-screen dashboard columns exactly:
     * New Lead Picked Up, Lead Responded, Transfer Out, Helped Reply,
     * Today Handling, Avg Response Time. The per-owner Avg Response Time is
     * weighted by each
     * day's Lead Responded count (days with no measured time are ignored),
     * matching the dashboard's total-row formula.
     */
    function lead_reply_export_build_matrix($dates, $perDayRows)
    {
        $names  = array();   // owner_user_id => owner_name
        $lookup = array();   // owner_user_id => date => [picked, resp, out, helped, hand, avgLabel]
        $totalPickedUp    = array();
        $totalResponded   = array();
        $totalTransferOut = array();
        $totalHelped      = array();
        $totalHandling    = array();
        $weightedSeconds  = array();   // owner_user_id => sum(avgSeconds * responded)
        $weightedLeads    = array();   // owner_user_id => sum(responded) over measured days

        foreach ($dates as $date) {
            $rows = isset($perDayRows[$date]) && is_array($perDayRows[$date]) ? $perDayRows[$date] : array();
            foreach ($rows as $row) {
                $ownerId = (string) $row['owner_user_id'];
                if ($ownerId === '') {
                    continue;
                }

                $pickedUp    = (int) $row['new_leads_picked_up'];
                $responded   = (int) $row['lead_responded'];
                $transferOut = (int) $row['transfer_out_leads'];
                $helped      = isset($row['helped_reply_leads']) ? (int) $row['helped_reply_leads'] : 0;
                $handling    = (int) $row['today_handling_leads'];
                $avgSeconds  = isset($row['avg_response_time_seconds']) && $row['avg_response_time_seconds'] !== null
                    ? (int) $row['avg_response_time_seconds'] : null;
                $avgLabel    = isset($row['avg_response_time_label']) ? (string) $row['avg_response_time_label'] : '-';

                if (!isset($names[$ownerId])) {
                    $names[$ownerId] = isset($row['owner_name']) && $row['owner_name'] !== ''
                        ? $row['owner_name'] : $ownerId;
                    $lookup[$ownerId] = array();
                    $totalPickedUp[$ownerId]    = 0;
                    $totalResponded[$ownerId]   = 0;
                    $totalTransferOut[$ownerId] = 0;
                    $totalHelped[$ownerId]      = 0;
                    $totalHandling[$ownerId]    = 0;
                    $weightedSeconds[$ownerId]  = 0;
                    $weightedLeads[$ownerId]    = 0;
                }

                $lookup[$ownerId][$date] = array($pickedUp, $responded, $transferOut, $helped, $handling, $avgLabel);
                $totalPickedUp[$ownerId]    += $pickedUp;
                $totalResponded[$ownerId]   += $responded;
                $totalTransferOut[$ownerId] += $transferOut;
                $totalHelped[$ownerId]      += $helped;
                $totalHandling[$ownerId]    += $handling;
                if ($avgSeconds !== null && $responded > 0) {
                    $weightedSeconds[$ownerId] += $avgSeconds * $responded;
                    $weightedLeads[$ownerId]   += $responded;
                }
            }
        }

        $owners = array();
        foreach ($names as $ownerId => $ownerName) {
            $avgSeconds = $weightedLeads[$ownerId] > 0
                ? (int) round($weightedSeconds[$ownerId] / $weightedLeads[$ownerId]) : null;
            $owners[] = array(
                'owner_user_id'           => $ownerId,
                'owner_name'              => $ownerName,
                'total_picked_up'         => $totalPickedUp[$ownerId],
                'total_responded'         => $totalResponded[$ownerId],
                'total_transfer_out'      => $totalTransferOut[$ownerId],
                'total_helped'            => $totalHelped[$ownerId],
                'total_handling'          => $totalHandling[$ownerId],
                'avg_response_time_label' => lead_reply_export_duration_label($avgSeconds),
            );
        }

        usort($owners, function ($a, $b) {
            if ($a['total_responded'] !== $b['total_responded']) {
                return $b['total_responded'] - $a['total_responded'];
            }
            return strcasecmp($a['owner_name'], $b['owner_name']);
        });

        return array('owners' => $owners, 'lookup' => $lookup);
    }
}

if ( ! function_exists('lead_reply_export_filename'))
{
    /**
     * Build the .xlsx download filename for a given export window.
     */
    function lead_reply_export_filename($startDate, $endDate)
    {
        $start = date('Ymd', strtotime((string) $startDate));
        $end   = date('Ymd', strtotime((string) $endDate));
        if ($start === $end) {
            return 'LEAD_REPLY_ACTIVITY_' . $start . '.xlsx';
        }
        return 'LEAD_REPLY_ACTIVITY_' . $start . '_' . $end . '.xlsx';
    }
}
