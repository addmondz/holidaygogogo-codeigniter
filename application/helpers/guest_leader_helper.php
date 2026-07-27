<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Guest Leader Helper
 *
 * Pure logic backing the "auto-propagate customer info into the Guest List"
 * feature: when a booking is created, the group leader (first ADULT row) is
 * seeded from the booking's customer details so the Guest List / room
 * management form opens pre-filled instead of blank.
 *
 * Kept free of CodeIgniter/DB so it can be unit tested under SQLite :memory:
 * (see tests/helpers/GuestLeaderSeedFieldsTest.php).
 *
 * Field casing mirrors Guest_List_Model::Create_Guest(): names/emails are
 * stored UPPERCASE, mobiles are kept verbatim.
 */

if (!function_exists('guest_leader_seed_fields')) {
    /**
     * Build the guest_list column overrides used to seed the leader row from
     * the booking customer. Only non-empty values are returned, so a blank
     * booking field never overwrites a guest_list column with an empty string.
     *
     * @param string      $name           Booking customer name (booking.Customer).
     * @param string      $mobile         Booking customer mobile (booking.Mobile).
     * @param string|int  $country_code_id Booking mobile country code (booking.CountryCodeID).
     * @param string      $email          Customer email (customer.PrimaryEmail), optional.
     * @return array Map of guest_list columns => values (may be empty).
     */
    function guest_leader_seed_fields($name, $mobile, $country_code_id, $email = '')
    {
        $fields = array();

        $name = trim((string) $name);
        if ($name !== '') {
            $fields['Name'] = strtoupper($name);
        }

        $mobile = trim((string) $mobile);
        if ($mobile !== '') {
            $fields['Mobile'] = $mobile;
        }

        if (!empty($country_code_id)) {
            $fields['CountryCodeID'] = $country_code_id;
        }

        $email = trim((string) $email);
        if ($email !== '') {
            $fields['Email'] = strtoupper($email);
        }

        return $fields;
    }
}
