<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Sales team working window. Edit this block to change duty hours.
// Days: 1=Mon ... 7=Sun (matches PHP date('N')).
if(!defined('DUTY_HOURS_DAYS'))       define('DUTY_HOURS_DAYS', '1,2,3,4,5,6'); // Mon-Sat
if(!defined('DUTY_HOURS_START_HOUR')) define('DUTY_HOURS_START_HOUR', 9);
if(!defined('DUTY_HOURS_END_HOUR'))   define('DUTY_HOURS_END_HOUR', 18);        // exclusive

if(!function_exists('is_within_duty_hours')) {
    /**
     * Returns true if the given MySQL DATETIME string falls inside the sales
     * team's duty hours (server local time — server is MYT).
     *
     *   is_within_duty_hours('2026-05-20 09:30:00') => true
     *   is_within_duty_hours('2026-05-20 19:00:00') => false  (after 18:00)
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
