<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Guest Contact Helper
 *
 * Pure logic backing the Guest List inline contact-number edit
 * (Guests::Update_Contact). Kept free of CodeIgniter/DB so it can be unit
 * tested under SQLite :memory: (see tests/helpers/GuestContactUpdateTest.php).
 *
 * The normalized key MUST match the guest_list / ghl_contacts `dedup_key`
 * generated column, which is:
 *   COALESCE(RIGHT(REGEXP_REPLACE(Mobile,'[^0-9]',''), 9), LOWER(Email))
 * (see application/sql/20260529_Add_Guest_Listing_Dedup_Key_Column.sql).
 * guest_contact_normalize_key() reproduces the phone branch in PHP so the
 * duplicate check can compare against the precomputed dedup_key column.
 */

if (!function_exists('guest_contact_normalize_key')) {
    /**
     * Strip every non-digit and return the last 9 digits — the phone branch of
     * the dedup_key. Returns '' when there are no digits at all.
     *
     * @param string $mobile Raw user-entered number (may contain +, -, spaces).
     * @return string Last 9 digits, or '' when none.
     */
    function guest_contact_normalize_key($mobile)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $mobile);
        if ($digits === '' || $digits === null) {
            return '';
        }
        return strlen($digits) <= 9 ? $digits : substr($digits, -9);
    }
}

if (!function_exists('guest_contact_validate_mobile')) {
    /**
     * Validate a user-entered contact number before it is saved back to
     * guest_list. Allows digits plus the usual formatting characters
     * (+, -, spaces, parentheses) and requires enough digits to be a real
     * phone number.
     *
     * @param string $mobile Raw input.
     * @return array{ok:bool,error:string}
     */
    function guest_contact_validate_mobile($mobile)
    {
        $mobile = trim((string) $mobile);

        if ($mobile === '') {
            return array('ok' => false, 'error' => 'Contact number is required.');
        }

        if (preg_match('/[^0-9+\-()\s]/', $mobile)) {
            return array('ok' => false, 'error' => 'Contact number contains invalid characters.');
        }

        $digits = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($digits) < 7) {
            return array('ok' => false, 'error' => 'Contact number is too short.');
        }
        if (strlen($digits) > 15) {
            return array('ok' => false, 'error' => 'Contact number is too long.');
        }

        return array('ok' => true, 'error' => '');
    }
}

if (!function_exists('guest_contact_format_display')) {
    /**
     * Render a contact number for display WITH its international calling code.
     *
     * Booking guests store a local Mobile (e.g. "0169546738") and a separate
     * calling code ("+60"); the leading trunk "0" is dropped so the result is
     * the proper international form "+60 169546738". GHL leads already store a
     * full E.164 phone and carry an empty calling code, which is passed through
     * unchanged.
     *
     * @param string $calling_code e.g. "+60" (country_code.CountryCode), or '' for an already-complete number.
     * @param string $local        The local/raw number as stored.
     * @return string Display string, or '' when there is no local number.
     */
    function guest_contact_format_display($calling_code, $local)
    {
        $local        = trim((string) $local);
        $calling_code = trim((string) $calling_code);

        if ($local === '') {
            return '';
        }
        if ($calling_code === '') {
            return $local;
        }
        if ($local[0] === '0') {
            $local = substr($local, 1);
        }
        if ($local === '') {
            return $calling_code;
        }
        return $calling_code . ' ' . $local;
    }
}

if (!function_exists('guest_contact_wa_digits')) {
    /**
     * Build the digits-only international number for a wa.me link.
     *
     * Mirrors guest_contact_format_display(): drops the local trunk "0" and
     * prepends the calling code's digits. When the calling code is empty the
     * local number already carries its code (GHL leads), so its digits are
     * returned as-is.
     *
     * @param string $calling_code e.g. "+60", or '' for an already-complete number.
     * @param string $local        The local/raw number as stored.
     * @return string Digits only, or '' when there is no local number.
     */
    function guest_contact_wa_digits($calling_code, $local)
    {
        $local_digits = preg_replace('/[^0-9]/', '', (string) $local);
        if ($local_digits === '') {
            return '';
        }
        $calling_code = trim((string) $calling_code);
        if ($calling_code === '') {
            return $local_digits;
        }
        $local_digits = ltrim($local_digits, '0');
        $cc_digits    = preg_replace('/[^0-9]/', '', $calling_code);
        return $cc_digits . $local_digits;
    }
}

if (!function_exists('guest_list_parse_date_range')) {
    /**
     * Parse a daterangepicker value ("DD/MM/YYYY - DD/MM/YYYY") into a
     * normalized [start, end] pair of Y-m-d strings. Returns null when the
     * input is empty or not a two-sided range. Shared by every date filter on
     * the Guest List so booking-side and GHL-side parsing stay identical.
     *
     * @param string $raw Raw range string from the request.
     * @return array{0:string,1:string}|null
     */
    function guest_list_parse_date_range($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $range = explode(' - ', $raw);
        if (count($range) !== 2) {
            return null;
        }
        $start = date('Y-m-d', strtotime(str_replace('/', '-', $range[0])));
        $end   = date('Y-m-d', strtotime(str_replace('/', '-', $range[1])));
        return array($start, $end);
    }
}

if (!function_exists('guest_list_ghl_suppressed_by_filters')) {
    /**
     * GHL leads carry only a name, contact, and lead-captured date — they have
     * no sales agent, source, customer type, nationality, gender, language, or
     * travel dates. When the Guest List is filtered by any of those
     * booking-only attributes a GHL lead can never match, so the GHL branch is
     * dropped entirely. (The booking-date and name filters are NOT booking-only:
     * they apply to GHL leads via the lead-captured date / contact name.)
     *
     * Guest Role is a special case: "Team Leader"/"Team Member" are booking
     * roles a lead can never hold, so they suppress GHL; "Lead" is the GHL role
     * itself, so it does NOT (it selects them — see
     * guest_list_bookings_suppressed_by_filters()).
     *
     * @param array $get The request GET params.
     * @return bool True when GHL leads must be suppressed.
     */
    function guest_list_ghl_suppressed_by_filters($get)
    {
        $booking_only = array(
            'travel_date', 'sales_agent', 'source',
            'customer_type', 'nationality', 'gender', 'language',
            'booking_number', 'destination', 'pax_min', 'pax_max',
        );
        foreach ($booking_only as $k) {
            if (isset($get[$k]) && trim((string) $get[$k]) !== '') {
                return true;
            }
        }
        $role = isset($get['role']) ? trim((string) $get['role']) : '';
        if ($role === 'Team Leader' || $role === 'Team Member') {
            return true;
        }
        return false;
    }
}

if (!function_exists('guest_list_bookings_suppressed_by_filters')) {
    /**
     * Mirror of guest_list_ghl_suppressed_by_filters() for the booking branch.
     * A booking guest is never role "Lead" (that role belongs to GHL leads), so
     * filtering Guest Role = Lead drops the booking branch and leaves only
     * leads. No other filter is GHL-only, so this is the lone case.
     *
     * @param array $get The request GET params.
     * @return bool True when booking guests must be suppressed.
     */
    function guest_list_bookings_suppressed_by_filters($get)
    {
        $role = isset($get['role']) ? trim((string) $get['role']) : '';
        return $role === 'Lead';
    }
}

if (!function_exists('guest_contact_duplicate_key_sql')) {
    /**
     * Existence query: does $new_key already belong to a DIFFERENT person —
     * either an active booking guest or a GHL lead — than $current_key?
     *
     * Returns one column `dup` (1/0). Bind params in order:
     *   [ $new_key, $current_key, $new_key, $current_key ]
     *
     * Shared by Guests_Model::Contact_Key_Belongs_To_Other and the unit test so
     * both exercise the same predicate. Uses only portable SQL (EXISTS) so it
     * runs identically on MySQL (prod) and SQLite (tests).
     *
     * @return string
     */
    function guest_contact_duplicate_key_sql()
    {
        return "SELECT (
            EXISTS(
                SELECT 1 FROM guest_list gl
                JOIN booking b ON b.BookingID = gl.BookingID
                WHERE gl.Status = 'Y' AND b.Status != 'N' AND b.CancelStatus = 'N'
                    AND gl.dedup_key = ? AND gl.dedup_key <> ?
            )
            OR EXISTS(
                SELECT 1 FROM ghl_contacts gc
                WHERE gc.dedup_key = ? AND gc.dedup_key <> ?
            )
        ) AS dup";
    }
}
