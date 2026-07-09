<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Canonical cutoff lives in constants.php (LEAD_CONVERSION_TC2_CUTOFF_DATE).
// Fallback for when this helper is loaded outside the CodeIgniter bootstrap
// (e.g. the standalone test runner).
if (!defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')) {
    define('LEAD_CONVERSION_TC2_CUTOFF_DATE', '2026-06-01');
}

if (!function_exists('pdf_show_booking_pic_for_date')) {
    /**
     * Whether a booking PDF should use the split TC layout introduced on
     * 2026-06-01: a "Booking PIC" line for TC1 (booking.SalesAgent) plus a
     * separate "Sales Agent" line for TC2 (booking.SalesAgent2).
     *
     * Bookings created on/after the cutoff use the split layout; earlier
     * bookings keep the single "Sales Agent" line for TC1. Empty/malformed
     * dates fall back to false so legacy records render the original layout.
     * Mirrors the TC1/TC2 attribution in lead_conversion_credit_helper.php.
     */
    function pdf_show_booking_pic_for_date($insert_date)
    {
        if (empty($insert_date)) {
            return false;
        }
        $doc_ts = strtotime($insert_date);
        $cutoff_ts = strtotime(LEAD_CONVERSION_TC2_CUTOFF_DATE);
        if ($doc_ts === false || $cutoff_ts === false) {
            return false;
        }
        return $doc_ts >= $cutoff_ts;
    }
}

if (!function_exists('booking_pic_display')) {
    /**
     * Decide which agent(s) print on the "Booking PIC" line of the Booking
     * Confirmation / Travel Voucher, and the label to use.
     *
     * Two modes:
     *  - Manual (either tick flag is set): staff tick Sales Agent 1 and/or
     *    Sales Agent 2 on the booking form. Every ticked agent that has a name
     *    prints, and the label is always "Booking PIC". Both can print at once.
     *  - Legacy fallback (both flags NULL — bookings saved before this feature):
     *    keep the old single-agent layout — show Sales Agent 1 only, labelled
     *    "Booking PIC" or "Sales Agent" per the 2026-06-01 date cutoff.
     *
     * Mobiles are expected already country-code formatted by the caller.
     *
     * @param array $data keys: sales_agent_is_pic, sales_agent_2_is_pic,
     *        sales_agent_name, sales_agent_mobile, sales_agent_2_name,
     *        sales_agent_2_mobile, insert_date
     * @return array ['label' => string, 'entries' => array<['name','mobile']>,
     *                'text' => string]  ('text' is entries joined by <br>, or '-')
     */
    function booking_pic_display($data)
    {
        // '' / null => unset; anything else casts to a 0/1 tick.
        $normalize = function ($flag) {
            if ($flag === null || $flag === '') {
                return null;
            }
            return ((int) $flag) !== 0 ? 1 : 0;
        };

        $flag1 = $normalize(isset($data['sales_agent_is_pic'])   ? $data['sales_agent_is_pic']   : null);
        $flag2 = $normalize(isset($data['sales_agent_2_is_pic']) ? $data['sales_agent_2_is_pic'] : null);

        $name1   = isset($data['sales_agent_name'])     ? $data['sales_agent_name']     : '';
        $mobile1 = isset($data['sales_agent_mobile'])   ? $data['sales_agent_mobile']   : '';
        $name2   = isset($data['sales_agent_2_name'])   ? $data['sales_agent_2_name']   : '';
        $mobile2 = isset($data['sales_agent_2_mobile']) ? $data['sales_agent_2_mobile'] : '';

        $entries = array();

        if ($flag1 === null && $flag2 === null) {
            // Legacy fallback: Sales Agent 1 only, date-driven label.
            $label = pdf_show_booking_pic_for_date(isset($data['insert_date']) ? $data['insert_date'] : '')
                ? 'Booking PIC' : 'Sales Agent';
            if ($name1 !== '') {
                $entries[] = array('name' => $name1, 'mobile' => $mobile1);
            }
        } else {
            // Manual mode: every ticked agent (with a name) prints.
            $label = 'Booking PIC';
            if ($flag1 === 1 && $name1 !== '') {
                $entries[] = array('name' => $name1, 'mobile' => $mobile1);
            }
            if ($flag2 === 1 && $name2 !== '') {
                $entries[] = array('name' => $name2, 'mobile' => $mobile2);
            }
        }

        $parts = array();
        foreach ($entries as $e) {
            $parts[] = $e['name'] . ' (' . $e['mobile'] . ')';
        }
        $text = !empty($parts) ? implode('<br>', $parts) : '-';

        return array('label' => $label, 'entries' => $entries, 'text' => $text);
    }
}
