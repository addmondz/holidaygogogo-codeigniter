<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Sales team working window. Edit this block to change duty hours.
// Days: 1=Mon ... 7=Sun (matches PHP date('N')).
if(!defined('DUTY_HOURS_DAYS'))       define('DUTY_HOURS_DAYS', '1,2,3,4,5,6'); // Mon-Sat
if(!defined('DUTY_HOURS_START_HOUR')) define('DUTY_HOURS_START_HOUR', 8);
if(!defined('DUTY_HOURS_END_HOUR'))   define('DUTY_HOURS_END_HOUR', 22);        // exclusive

if(!function_exists('is_within_duty_hours')) {
    /**
     * Returns true if the given MySQL DATETIME string falls inside the sales
     * team's duty hours (server local time — server is MYT).
     *
     *   is_within_duty_hours('2026-05-20 08:30:00') => true
     *   is_within_duty_hours('2026-05-20 22:00:00') => false  (end exclusive)
     *   is_within_duty_hours('2026-05-17 10:00:00') => false  (Sunday)
     *   is_within_duty_hours(null) => false
     */
    function is_within_duty_hours($mysql_datetime) {
        if(empty($mysql_datetime)) return false;
        $ts = strtotime((string)$mysql_datetime);
        if($ts === false) return false;

        $allowed_days = array_map('intval', explode(',', DUTY_HOURS_DAYS));
        $dow  = (int)date('N', $ts);
        $hour = (int)date('G', $ts);

        if(!in_array($dow, $allowed_days, true)) return false;
        if($hour <  DUTY_HOURS_START_HOUR) return false;
        if($hour >= DUTY_HOURS_END_HOUR)   return false;
        return true;
    }
}

if(!function_exists('calculate_duty_response_seconds')) {
    /**
     * Returns elapsed seconds between two datetimes, counting only time inside
     * the configured duty-hours window. Off-days and off-hours contribute 0.
     */
    function calculate_duty_response_seconds($start_datetime, $end_datetime) {
        if(empty($start_datetime) || empty($end_datetime)) return null;

        $startTs = strtotime((string)$start_datetime);
        $endTs = strtotime((string)$end_datetime);
        if($startTs === false || $endTs === false || $endTs < $startTs) return null;

        $allowedDays = array_map('intval', explode(',', DUTY_HOURS_DAYS));
        $total = 0;
        $cursor = strtotime(date('Y-m-d 00:00:00', $startTs));
        $endDay = strtotime(date('Y-m-d 00:00:00', $endTs));

        while($cursor !== false && $cursor <= $endDay) {
            $dow = (int)date('N', $cursor);
            if(in_array($dow, $allowedDays, true)) {
                $windowStart = strtotime(date('Y-m-d', $cursor) . ' ' . sprintf('%02d:00:00', DUTY_HOURS_START_HOUR));
                $windowEnd = strtotime(date('Y-m-d', $cursor) . ' ' . sprintf('%02d:00:00', DUTY_HOURS_END_HOUR));

                $overlapStart = max($startTs, $windowStart);
                $overlapEnd = min($endTs, $windowEnd);
                if($overlapEnd > $overlapStart) {
                    $total += ($overlapEnd - $overlapStart);
                }
            }

            $cursor = strtotime('+1 day', $cursor);
        }

        return (int)$total;
    }
}
