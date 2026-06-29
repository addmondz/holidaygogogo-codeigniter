<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pure helpers for the Lead Reply Activity Excel export.
 *
 * The on-screen dashboard is a DAILY report (one date, per-owner rows). The
 * export instead accepts a date RANGE and lays the days out as column groups:
 * one row per owner, and for every day in the range a (Responded, Transfer Out,
 * Handling) trio of columns. Each day is still computed with the exact daily
 * definition -- the controller calls the daily model query once per day and
 * hands the per-day rows here to be stitched into the owner x day matrix.
 *
 * Everything here is DB-free so it can be unit-tested without a database.
 */

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
     *                          lead_responded, transfer_out_leads,
     *                          today_handling_leads
     * @return array {
     *     owners: list of [owner_user_id, owner_name, total_responded,
     *                      total_transfer_out] sorted by total_responded desc
     *                      then owner_name asc,
     *     lookup: owner_user_id => ('Y-m-d' => [responded, transfer_out, handling])
     * }
     */
    function lead_reply_export_build_matrix($dates, $perDayRows)
    {
        $names  = array();   // owner_user_id => owner_name
        $lookup = array();   // owner_user_id => date => [resp, out, hand]
        $totalResponded   = array();
        $totalTransferOut = array();

        foreach ($dates as $date) {
            $rows = isset($perDayRows[$date]) && is_array($perDayRows[$date]) ? $perDayRows[$date] : array();
            foreach ($rows as $row) {
                $ownerId = (string) $row['owner_user_id'];
                if ($ownerId === '') {
                    continue;
                }

                $responded   = (int) $row['lead_responded'];
                $transferOut = (int) $row['transfer_out_leads'];
                $handling    = (int) $row['today_handling_leads'];

                if (!isset($names[$ownerId])) {
                    $names[$ownerId] = isset($row['owner_name']) && $row['owner_name'] !== ''
                        ? $row['owner_name'] : $ownerId;
                    $lookup[$ownerId] = array();
                    $totalResponded[$ownerId]   = 0;
                    $totalTransferOut[$ownerId] = 0;
                }

                $lookup[$ownerId][$date] = array($responded, $transferOut, $handling);
                $totalResponded[$ownerId]   += $responded;
                $totalTransferOut[$ownerId] += $transferOut;
            }
        }

        $owners = array();
        foreach ($names as $ownerId => $ownerName) {
            $owners[] = array(
                'owner_user_id'      => $ownerId,
                'owner_name'         => $ownerName,
                'total_responded'    => $totalResponded[$ownerId],
                'total_transfer_out' => $totalTransferOut[$ownerId],
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
