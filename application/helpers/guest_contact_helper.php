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

if (!function_exists('guest_list_travel_date_filter_value')) {
    /**
     * Build the travel_date filter value ("DD/MM/YYYY - DD/MM/YYYY") for a
     * booking's travel window, so clicking a rendered travel date on the Guest
     * List reloads it filtered to that window — grouping everyone travelling
     * then together. The output is the exact daterangepicker format the filter
     * expects and round-trips through guest_list_parse_date_range().
     *
     * When only one side is known the range collapses to that single day (the
     * parser requires a two-sided range). Empty / zero dates yield '' (no
     * usable filter).
     *
     * @param string $start_ymd Booking StartDate as Y-m-d (or '' / 0000-00-00).
     * @param string $end_ymd   Booking EndDate as Y-m-d (or '' / 0000-00-00).
     * @return string "DD/MM/YYYY - DD/MM/YYYY", or '' when neither date is set.
     */
    function guest_list_travel_date_filter_value($start_ymd, $end_ymd)
    {
        $norm = function ($d) {
            $d = trim((string) $d);
            if ($d === '' || $d === '0000-00-00') {
                return '';
            }
            $ts = strtotime($d);
            return $ts ? date('d/m/Y', $ts) : '';
        };

        $start = $norm($start_ymd);
        $end   = $norm($end_ymd);

        if ($start === '' && $end === '') {
            return '';
        }
        if ($start === '') {
            $start = $end;
        }
        if ($end === '') {
            $end = $start;
        }
        return $start . ' - ' . $end;
    }
}

if (!function_exists('guest_list_split_team_leaders')) {
    /**
     * Split the GROUP_CONCAT'd booking customer names — the "booking name as per
     * BC form", i.e. the team leader of each booking a guest belongs to — into a
     * clean, de-duplicated, display-ready list. Blank / whitespace-only names are
     * dropped and duplicates collapsed (case-insensitively, first-seen order).
     *
     * Backs the Guest List "Team Leader" column so every team member row shows
     * the leader name(s) from their booking(s).
     *
     * @param string $concat Raw GROUP_CONCAT value, '||'-separated.
     * @return string[] Trimmed, de-duplicated, non-empty names in first-seen order.
     */
    function guest_list_split_team_leaders($concat)
    {
        $out  = array();
        $seen = array();
        foreach (explode('||', (string) $concat) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $name;
        }
        return $out;
    }
}

if (!function_exists('guest_list_team_leader_clause')) {
    /**
     * Build the row-level WHERE fragment for the Guest List "Team Leader" filter,
     * a search on the booking customer name (b.Customer) — the group leader named
     * on the BC form. Returns null when no team_leader is given.
     *
     * Two modes so the same param serves both entry points:
     *   - Typed into the filter box → partial LIKE ("John" also finds "Johnny").
     *   - Clicked on a Team Leader column name (team_leader_exact set) → EXACT
     *     equality, so clicking "John Tan" shows only that leader's team members,
     *     never "Johnny Wong".
     *
     * @param array $get The request GET params.
     * @return array{sql:string,param:string}|null
     */
    function guest_list_team_leader_clause($get)
    {
        $name = isset($get['team_leader']) ? trim((string) $get['team_leader']) : '';
        if ($name === '') {
            return null;
        }
        $exact = isset($get['team_leader_exact']) && trim((string) $get['team_leader_exact']) !== '';
        if ($exact) {
            return array('sql' => " AND b.Customer = ? ", 'param' => $name);
        }
        return array('sql' => " AND b.Customer LIKE ? ", 'param' => '%' . $name . '%');
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
            'team_leader',
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

if (!function_exists('guest_list_branches_to_run')) {
    /**
     * Decide which branch(es) a Guest List page reads. The listing is now split
     * into two pages, each locked to ONE source:
     *   - mode 'guest' → the Guest List page (booking guests only)
     *   - mode 'ghl'   → the GHL Leads page (GHL leads only)
     *
     * The active filters can still drop the page's own branch to empty when they
     * can never match it (e.g. a booking-only Destination filter on the GHL page,
     * or Guest Role = Lead on the Guest List page) — reusing the same suppression
     * predicates the merged listing used, so the two pages stay consistent.
     *
     * @param string $mode 'guest' or 'ghl' (anything else falls back to 'guest').
     * @param array  $get  The request GET params.
     * @return array{bookings:bool,ghl:bool}
     */
    function guest_list_branches_to_run($mode, $get)
    {
        $mode = ($mode === 'ghl') ? 'ghl' : 'guest';
        return array(
            'bookings' => ($mode === 'guest') && !guest_list_bookings_suppressed_by_filters($get),
            'ghl'      => ($mode === 'ghl')   && !guest_list_ghl_suppressed_by_filters($get),
        );
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
