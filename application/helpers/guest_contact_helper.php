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

if (!function_exists('guest_field_validate_name')) {
    /**
     * Validate a First Name inline-edited on the Guest List dashboard before it
     * is written back to guest_list.Name. Required and length-capped; otherwise
     * permissive (names carry spaces, apostrophes, dots, non-Latin letters).
     *
     * @param string $name Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed name.
     */
    function guest_field_validate_name($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return array('ok' => false, 'error' => 'Name is required.', 'value' => '');
        }
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($len > 100) {
            return array('ok' => false, 'error' => 'Name is too long.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $name);
    }
}

if (!function_exists('guest_field_validate_email')) {
    /**
     * Validate an Email inline-edited on the Guest List dashboard before it is
     * written back to guest_list.Email. An empty value is allowed (clears the
     * email); a non-empty value must be a well-formed address.
     *
     * @param string $email Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed email ('' when cleared).
     */
    function guest_field_validate_email($email)
    {
        $email = trim((string) $email);
        if ($email === '') {
            return array('ok' => true, 'error' => '', 'value' => '');
        }
        if (strlen($email) > 255) {
            return array('ok' => false, 'error' => 'Email is too long.', 'value' => '');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'Enter a valid email address.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $email);
    }
}

if (!function_exists('guest_field_validate_language')) {
    /**
     * Validate a Language inline-edited on the Guest List dashboard before it is
     * written back to booking.ChatLanguage / customer.ChatLanguage. The value
     * must be one of the allowed codes (the ENUM set, e.g. CN/EN/ML), so a free
     * value can never reach the ChatLanguage columns.
     *
     * @param string   $value   Raw input.
     * @param string[] $allowed Allowed language codes.
     * @return array{ok:bool,error:string,value:string}
     */
    function guest_field_validate_language($value, $allowed)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return array('ok' => false, 'error' => 'Select a language.', 'value' => '');
        }
        if (!is_array($allowed) || !in_array($value, $allowed, true)) {
            return array('ok' => false, 'error' => 'Invalid language.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $value);
    }
}

if (!function_exists('guest_field_validate_altname')) {
    /**
     * Validate an Alt Name inline-edited on the Guest/Customer dashboard before
     * it is written back to customer.AltName. Alt Name is a secondary/alternate
     * name (nickname, English name, etc.) and is optional: an empty value is
     * allowed and clears the field; a non-empty value is trimmed and length
     * capped to the customer.AltName column width.
     *
     * @param string $value Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed alt name ('' when cleared).
     */
    function guest_field_validate_altname($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return array('ok' => true, 'error' => '', 'value' => '');
        }
        $len = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($len > 255) {
            return array('ok' => false, 'error' => 'Alt Name is too long.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $value);
    }
}

if (!function_exists('guest_remark_validate_date')) {
    /**
     * Validate a plain calendar date on a Guest List remark (Campaign Date /
     * Follow Date) before it is stored in guest_remarks. Accepts the HTML5 date
     * form ("YYYY-MM-DD") and normalizes it to a MySQL DATE string. When
     * $required is false an empty value is allowed and returns value '' (the
     * caller stores NULL); a non-empty value must still parse.
     *
     * @param string $raw      Raw input.
     * @param bool   $required  Reject an empty value when true.
     * @param string $label     Field name used in the error messages.
     * @return array{ok:bool,error:string,value:string} value is 'Y-m-d' or ''.
     */
    function guest_remark_validate_date($raw, $required = true, $label = 'Date')
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            if ($required) {
                return array('ok' => false, 'error' => $label . ' is required.', 'value' => '');
            }
            return array('ok' => true, 'error' => '', 'value' => '');
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return array('ok' => false, 'error' => 'Enter a valid ' . strtolower($label) . '.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => date('Y-m-d', $ts));
    }
}

if (!function_exists('guest_remark_validate_destination')) {
    /**
     * Validate the optional Destination select on a Guest List remark. Empty is
     * allowed and returns value 0 (the caller stores NULL). Otherwise the value
     * must be a positive integer category id — the modal only ever submits ids
     * from the destination list, so this rejects anything non-numeric.
     *
     * @param string|int $raw Raw input (a category id, or '' for none).
     * @return array{ok:bool,error:string,value:int} value 0 means "no destination".
     */
    function guest_remark_validate_destination($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array('ok' => true, 'error' => '', 'value' => 0);
        }
        if (!ctype_digit($raw) || (int) $raw < 1) {
            return array('ok' => false, 'error' => 'Choose a valid destination.', 'value' => 0);
        }
        return array('ok' => true, 'error' => '', 'value' => (int) $raw);
    }
}

if (!function_exists('guest_remark_validate_remark')) {
    /**
     * Validate the text of a Guest List remark before it is stored in
     * guest_remarks.Remark. Required and length-capped; otherwise permissive.
     *
     * @param string $raw Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed remark.
     */
    function guest_remark_validate_remark($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array('ok' => false, 'error' => 'Remark is required.', 'value' => '');
        }
        $len = function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw);
        if ($len > 1000) {
            return array('ok' => false, 'error' => 'Remark is too long.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $raw);
    }
}

if (!function_exists('lead_status_log_validate_status')) {
    /**
     * Validate the Lead Status value on a Manual Lead status-log entry before it
     * is stored in lead_status_log.LeadStatus. Required, length-capped to the
     * lead_status.Name column width (50). The value is a preset status NAME chosen
     * from the settings picklist; free values are accepted (the picklist can
     * change over time) but must be non-empty.
     *
     * @param string $raw Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed status.
     */
    function lead_status_log_validate_status($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array('ok' => false, 'error' => 'Status is required.', 'value' => '');
        }
        $len = function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw);
        if ($len > 50) {
            return array('ok' => false, 'error' => 'Status is too long.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $raw);
    }
}

if (!function_exists('lead_status_log_validate_note')) {
    /**
     * Validate the optional Note on a Manual Lead status-log entry before it is
     * stored in lead_status_log.Note. Empty is allowed and returns value '' (the
     * caller stores NULL); otherwise it is trimmed and length-capped.
     *
     * @param string $raw Raw input.
     * @return array{ok:bool,error:string,value:string} value is the trimmed note or ''.
     */
    function lead_status_log_validate_note($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array('ok' => true, 'error' => '', 'value' => '');
        }
        $len = function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw);
        if ($len > 1000) {
            return array('ok' => false, 'error' => 'Note is too long.', 'value' => '');
        }
        return array('ok' => true, 'error' => '', 'value' => $raw);
    }
}

if (!function_exists('guest_contact_parse_multi')) {
    /**
     * Split a merged row's packed phone list into display-ready entries.
     *
     * A Guest List row can now merge several records that share the same
     * Name + IdentificationNumber but carry DIFFERENT phone numbers (see
     * Guests_Model merge_key). The model packs every distinct phone of the
     * group as "callingcode<US>mobile" units joined by a record separator, e.g.
     *   "+60\x1f0169546738\x1e+60\x1f0198887777"
     * so the listing can show both numbers on the one row.
     *
     * Entries are de-duplicated by their WhatsApp digits (so the same number
     * stored with different formatting — "0169546738" vs "016-954-6738" — shows
     * once, satisfying "duplicate mobile numbers need not display"); blank
     * numbers are dropped; first-seen order is kept.
     *
     * @param string $packed Packed list ("code\x1fmobile" units, \x1e-separated).
     * @return array<int,array{calling_code:string,mobile:string}>
     */
    function guest_contact_parse_multi($packed)
    {
        $out  = array();
        $seen = array();
        foreach (explode("\x1e", (string) $packed) as $unit) {
            if ($unit === '') {
                continue;
            }
            $parts        = explode("\x1f", $unit, 2);
            $calling_code = trim(isset($parts[0]) ? $parts[0] : '');
            $mobile       = trim(isset($parts[1]) ? $parts[1] : '');
            if ($mobile === '') {
                continue;
            }
            $wa  = guest_contact_wa_digits($calling_code, $mobile);
            $key = $wa !== '' ? $wa : ($calling_code . '|' . $mobile);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = array('calling_code' => $calling_code, 'mobile' => $mobile);
        }
        return $out;
    }
}

if (!function_exists('guest_list_merge_key')) {
    /**
     * Compute the Guest List display-merge key for one contact row — the PHP
     * mirror of Guests_Model::Merge_Key_Expr() (kept in lock-step by
     * GuestListMergeKeyTest, the way guest_contact_normalize_key mirrors the
     * dedup_key column).
     *
     * When a real identification number is present the key is
     * "ic:<lower(name)>|<UPPER(alphanumeric ic)>", so two records with the same
     * person's Name + IC collapse into one listing row even when their phone
     * numbers (and therefore their dedup_key) differ. When there is no IC the
     * row keeps its own dedup_key, so nothing merges that we are not sure is the
     * same person.
     *
     * @param string $name      The row's display name.
     * @param string $ic        guest_list.IdentificationNumber (may be blank/formatted).
     * @param string $dedup_key The row's existing phone/email dedup key.
     * @return string
     */
    function guest_list_merge_key($name, $ic, $dedup_key)
    {
        $ic_norm = preg_replace('/[^0-9A-Za-z]/', '', (string) $ic);
        if ($ic_norm !== '' && $ic_norm !== null) {
            $name  = trim((string) $name);
            $lname = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            return 'ic:' . $lname . '|' . strtoupper($ic_norm);
        }
        return (string) $dedup_key;
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

if (!function_exists('guest_list_birthday_clause')) {
    /**
     * Build a recurring-birthday SQL clause on gl.DateOfBirth that matches the
     * month/day regardless of birth year — for the Guest List "Birthday"
     * dropdown. Unlike the DOB "born between" range (which pins a full Y-m-d
     * window), this ignores the year so it surfaces guests whose birthday falls
     * now / this month / in a chosen month.
     *
     * Accepted values:
     *   'today'      → birthday is today   (MONTH & DAY both = today's)
     *   'this_month' → birthday this month (MONTH = current month)
     *   '1'..'12'    → birthday in that specific calendar month
     * Any other / empty value returns null (no filter applied).
     *
     * DateOfBirth is a DATE column: NULL / '0000-00-00' give MONTH() = 0, which
     * never equals a real 1-12 month, so placeholder birthdays never match.
     *
     * @param string $raw Raw dropdown value from the request.
     * @return array{sql:string,params:array}|null
     */
    function guest_list_birthday_clause($raw, $column = 'gl.DateOfBirth')
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if ($raw === 'today') {
            return array(
                'sql'    => " AND MONTH({$column}) = MONTH(CURDATE()) AND DAY({$column}) = DAY(CURDATE()) ",
                'params' => array(),
            );
        }
        if ($raw === 'this_month') {
            return array(
                'sql'    => " AND MONTH({$column}) = MONTH(CURDATE()) ",
                'params' => array(),
            );
        }
        if (ctype_digit($raw)) {
            $month = (int) $raw;
            if ($month >= 1 && $month <= 12) {
                return array(
                    'sql'    => " AND MONTH({$column}) = ? ",
                    'params' => array($month),
                );
            }
        }
        return null;
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

if (!function_exists('guest_list_multi_values')) {
    /**
     * Normalize a filter value that may arrive as a single string OR an array
     * (a multi-select filter submits `name[]=a&name[]=b`) into a clean list of
     * trimmed, non-empty, de-duplicated strings. Returns [] when nothing usable.
     *
     * Shared by every multi-select dropdown on the Guest List so the model
     * (IN clause), the suppression predicates, and the tests all read the raw
     * request value the same way.
     *
     * @param mixed $raw Request value: string, array of strings, or null.
     * @return string[] Trimmed, non-empty, first-seen-order unique values.
     */
    function guest_list_multi_values($raw)
    {
        if ($raw === null) {
            return array();
        }
        if (!is_array($raw)) {
            $raw = array($raw);
        }
        $out  = array();
        $seen = array();
        foreach ($raw as $v) {
            $v = trim((string) $v);
            if ($v === '' || isset($seen[$v])) {
                continue;
            }
            $seen[$v] = true;
            $out[]    = $v;
        }
        return $out;
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

if (!function_exists('guest_list_team_leader_links')) {
    /**
     * Parse the GROUP_CONCAT'd "BookingID:LeaderName" pairs into a display-ready
     * list of leader links. Each pair maps a leader name (booking.Customer, the
     * name on the BC form) to the BookingID it led. Because a guest is grouped
     * across all their bookings, the same leader can appear on more than one
     * booking, so pairs are grouped by leader name (first-seen order,
     * case-insensitive) and their BookingIDs collected together.
     *
     * Backs the Guest List "Team Leader" column: clicking a leader reloads the
     * list filtered to those exact BookingIDs, so it shows only that booking's
     * team members — not every booking the leader has ever run.
     *
     * @param string $concat Raw GROUP_CONCAT value, '||'-separated "id:name" pairs.
     * @return array<int,array{name:string,booking_ids:string[]}> First-seen order.
     */
    function guest_list_team_leader_links($concat)
    {
        $out     = array();
        $by_name = array();
        foreach (explode('||', (string) $concat) as $pair) {
            $sep = strpos($pair, ':');
            if ($sep === false) {
                continue; // no BookingID prefix -> unusable
            }
            $id   = trim(substr($pair, 0, $sep));
            $name = trim(substr($pair, $sep + 1));
            if ($id === '' || $name === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (!isset($by_name[$key])) {
                $by_name[$key] = count($out);
                $out[]         = array('name' => $name, 'booking_ids' => array($id));
            } elseif (!in_array($id, $out[$by_name[$key]]['booking_ids'], true)) {
                $out[$by_name[$key]]['booking_ids'][] = $id;
            }
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
            'customer_type',
            'booking_number', 'destination', 'pax_min', 'pax_max',
            'team_leader', 'booking_id', 'birthday', 'guest_type',
            // Remark-based filters: a GHL lead has no guest remarks, so a
            // Campaign/Follow date range can never match one.
            'campaign_date', 'follow_date',
            // Customer-master filters: a GHL lead has no linked customer row, so
            // a Customer Code / Date Creation filter can never match one.
            'customer_code', 'create_date',
        );
        foreach ($booking_only as $k) {
            if (isset($get[$k]) && count(guest_list_multi_values($get[$k])) > 0) {
                return true;
            }
        }
        // The "Customer type" value segments target the customer master (see
        // guest_list_customer_segment_sql); a GHL lead has no bookings / sales /
        // travel gap / cancel status, so any of them drops the GHL branch.
        if (guest_list_any_filter_set($get, guest_list_customer_only_segment_keys())) {
            return true;
        }
        // Guest Role is multi-select: a lead only ever holds the "Lead" role, so
        // any role filter that does NOT include "Lead" can never match a lead.
        $roles = isset($get['role']) ? guest_list_multi_values($get['role']) : array();
        if (!empty($roles) && !in_array('Lead', $roles, true)) {
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
        // Tag / Race live only on GHL leads, so either drops the booking branch.
        if (guest_list_any_filter_set($get, guest_list_leads_only_segment_keys())) {
            return true;
        }
        // The "Customer type" value segments target the customer master only, so a
        // booking-guest row can never satisfy one — drop the booking branch too.
        if (guest_list_any_filter_set($get, guest_list_customer_only_segment_keys())) {
            return true;
        }
        // Multi-select role: a booking guest is never a "Lead", so the booking
        // branch drops out only when the role filter is set and selects LEAD
        // exclusively (no "Team Leader" / "Team Member" alongside it).
        $roles = isset($get['role']) ? guest_list_multi_values($get['role']) : array();
        return !empty($roles)
            && !in_array('Team Leader', $roles, true)
            && !in_array('Team Member', $roles, true);
    }
}

if (!function_exists('guest_list_leader_fallback_suppressed_by_filters')) {
    /**
     * The "leader fallback" branch synthesizes a Team Leader row straight from a
     * booking's own contact (booking.Mobile / customer.phone_number + name) for
     * bookings whose guest list was never filled in — so every booking's leader
     * stays pickable for a campaign even before anyone types the guest details.
     *
     * Such a row exists ONLY at the booking level: it has no per-guest
     * nationality, gender, date of birth, e-mail or pax breakdown. So any of
     * those guest-only filters can never match it and must drop the branch
     * (otherwise the count would include leaders the filter should have removed).
     * Booking-level filters (sales agent, source, dates, destination, …) are fine
     * — the leader carries them — so they do NOT suppress it.
     *
     * Guest Role: the branch is leaders-only, so a role filter that does not
     * include "Team Leader" (Team Member only, or Lead only) drops it too.
     *
     * @param array $get The request GET params.
     * @return bool True when the leader fallback must be suppressed.
     */
    function guest_list_leader_fallback_suppressed_by_filters($get)
    {
        // A leader-fallback row exists only where the guest list was never
        // filled, so it can never carry a per-guest remark either — Campaign /
        // Follow date ranges must drop it just like the guest-only filters.
        $guest_only = array('nationality', 'gender', 'dob', 'birthday', 'email', 'pax_min', 'pax_max', 'campaign_date', 'follow_date');
        foreach ($guest_only as $k) {
            if (isset($get[$k]) && count(guest_list_multi_values($get[$k])) > 0) {
                return true;
            }
        }
        // Tag / Race live only on GHL leads, so either drops the leader-fallback
        // (a synthesized booking-leader row carries neither).
        if (guest_list_any_filter_set($get, guest_list_leads_only_segment_keys())) {
            return true;
        }
        // The "Customer type" value segments target the customer master only, so a
        // synthesized leader row can never satisfy one — drop the fallback too.
        if (guest_list_any_filter_set($get, guest_list_customer_only_segment_keys())) {
            return true;
        }
        // "Has email address" keeps only rows carrying an email; a synthesized
        // leader row has none (its Email column is always NULL), so drop it.
        if (guest_list_flag_on($get, 'has_email')) {
            return true;
        }
        $roles = isset($get['role']) ? guest_list_multi_values($get['role']) : array();
        if (!empty($roles) && !in_array('Team Leader', $roles, true)) {
            return true;
        }
        // A synthesized leader row is always an ADULT (the booking's own contact),
        // so a Guest Type filter that does not include ADULT can never match it.
        $types = isset($get['guest_type']) ? guest_list_multi_values($get['guest_type']) : array();
        if (!empty($types) && !in_array('ADULT', $types, true)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('guest_list_branches_to_run')) {
    /**
     * Decide which branch(es) a caller reads. The two standalone pages each lock
     * to ONE source; the campaign guest picker can read both at once:
     *   - mode 'guest'  → the Guest List page (booking guests only)
     *   - mode 'ghl'    → the GHL Leads page (GHL-synced leads only)
     *   - mode 'manual' → the Manual Leads page (hand-entered leads only) — same
     *                     GHL branch as 'ghl', narrowed to lead_source='manual'
     *                     by guest_list_ghl_lead_source_scope().
     *   - mode 'all'    → campaign picker (booking guests + leader fallback + leads)
     *
     * The active filters can still drop a branch to empty when they can never
     * match it (e.g. a booking-only Destination filter on the GHL branch, or
     * Guest Role = Lead on the booking branch) — reusing the same suppression
     * predicates the merged listing used, so every caller stays consistent.
     *
     * @param string $mode 'guest', 'ghl', 'manual' or 'all' (anything else → 'guest').
     * @param array  $get  The request GET params.
     * @return array{bookings:bool,ghl:bool}
     */
    function guest_list_branches_to_run($mode, $get)
    {
        $mode = in_array($mode, array('ghl', 'manual', 'all'), true) ? $mode : 'guest';
        // 'ghl' and 'manual' are both lead-only pages built on the GHL branch;
        // only the Guest List page ('guest'/fallback) reads the booking branch.
        $ghl_only = in_array($mode, array('ghl', 'manual'), true);
        return array(
            'bookings' => (!$ghl_only)        && !guest_list_bookings_suppressed_by_filters($get),
            'ghl'      => ($mode !== 'guest') && !guest_list_ghl_suppressed_by_filters($get),
        );
    }
}

if (!function_exists('guest_list_ghl_lead_source_scope')) {
    /**
     * The extra WHERE fragment that splits the shared GHL branch into its two
     * lead pages, plus per-creator privacy on the Manual Leads page:
     *   - mode 'ghl'    → synced leads only; hide hand-entered rows
     *                     ("moved out" to the Manual Leads page).
     *   - mode 'manual' → hand-entered rows only; and unless the viewer is a
     *                     view-all role (Owner 10 / Team Lead 25 / Marketing 60),
     *                     restrict to the leads THEY created (gc.created_by = ?).
     *   - anything else ('all' campaign picker) → no filter (keeps both sources).
     *
     * Pure (no DB/session): the caller passes the viewer's level + admin id, so
     * this unit-tests in isolation and runs identically on MySQL and SQLite.
     *
     * @param string   $mode     'ghl', 'manual' or other.
     * @param int|null $level    the viewer's role level.
     * @param int|null $admin_id the viewer's admin id.
     * @return array{0:string,1:array} [sql_fragment, bound_params]
     */
    function guest_list_ghl_lead_source_scope($mode, $level, $admin_id)
    {
        if ($mode === 'ghl') {
            // Manual leads live on their own page now — exclude them here.
            return array(" AND (gc.lead_source IS NULL OR gc.lead_source <> 'manual') ", array());
        }

        if ($mode === 'manual') {
            $sql = " AND gc.lead_source = 'manual' ";
            // Owner / Team Lead / Marketing see every manual lead; everyone else
            // sees only the ones they created.
            if (!in_array((int) $level, array(10, 25, 60), true)) {
                $sql .= " AND gc.created_by = ? ";
                return array($sql, array((int) $admin_id));
            }
            return array($sql, array());
        }

        return array('', array());
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

// ===========================================================================
// Campaign segmentation filters (Campaign guest picker).
//
// These back the extra segmentation dropdowns on the Campaign guest picker
// (Campaign/Search_Guests → Guests_Model). Each is a PURE helper so the
// SQL-building logic is unit-testable under SQLite :memory: without booting
// CodeIgniter (see tests/helpers/CampaignSegmentationFilterTest.php).
//
// Two families of new filters:
//   - Leads-only:  Tag, Race           (data lives only on ghl_contacts)
//   - Booking-only: purchased 2x+, lifetime value, booking-lead timeline,
//                   consecutive-years, family-with-kids, cancelled BC
// The suppression predicates below drop the branch(es) a filter can never
// match, exactly like the existing booking/GHL-only filters do.
// ===========================================================================

if (!function_exists('guest_list_purchase_count_min')) {
    /**
     * Minimum distinct-booking count for the "purchased N× and above" segment.
     * Returns the int floor (>= 2) or null when unset / below 2 / non-numeric,
     * so a stray value can never emit a HAVING that filters nothing.
     *
     * @param mixed $raw Raw dropdown value ('2','3',… ).
     * @return int|null
     */
    function guest_list_purchase_count_min($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        $n = (int) $raw;
        return $n >= 2 ? $n : null;
    }
}

if (!function_exists('guest_list_parse_bucket_bounds')) {
    /**
     * Parse a numeric "bucket" value into a [min, max] pair, min inclusive and
     * max EXCLUSIVE so adjacent buckets never double-count a boundary value.
     * Shared by the Lifetime Booking Value buckets (<10k, 10k-20k, …) and the
     * Booking-Lead timeline (0-1, 1-2, … months).
     *
     * Accepted forms:
     *   'a-b' → array(a, b)      a floor (inclusive), b ceiling (exclusive)
     *   'b+'  → array(b, null)   open-ended upper bound
     *   'a-'  → array(a, null)   (same as 'a+')
     * Any other / empty / reversed (min > max) value returns null (no filter).
     *
     * @param mixed $raw Raw dropdown value.
     * @return array{0:float,1:float|null}|null
     */
    function guest_list_parse_bucket_bounds($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        // Open-ended: "50000+" or "50000-"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*[+\-]$/', $raw, $m)) {
            return array((float) $m[1], null);
        }

        // Range: "10000-20000"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $raw, $m)) {
            $min = (float) $m[1];
            $max = (float) $m[2];
            if ($max <= $min) {
                return null;
            }
            return array($min, $max);
        }

        return null;
    }
}

if (!function_exists('guest_list_flag_on')) {
    /**
     * Read a boolean toggle from the request the same way everywhere: a param is
     * "on" only when present and equal to '1' (checkbox / hidden input). Anything
     * else (absent, '0', '', 'false') is off.
     *
     * @param array  $get The request GET params.
     * @param string $key The toggle key.
     * @return bool
     */
    function guest_list_flag_on($get, $key)
    {
        return isset($get[$key]) && trim((string) $get[$key]) === '1';
    }
}

if (!function_exists('guest_list_cancel_predicate')) {
    /**
     * The booking cancel-status predicate. Normally the listing HIDES cancelled
     * bookings (CancelStatus = 'N'); the "Cancelled BC" segment flips it to show
     * ONLY cancelled ones (CancelStatus != 'N') so a campaign can target guests
     * whose booking was cancelled. $col is a trusted, code-supplied column name.
     *
     * @param array  $get The request GET params.
     * @param string $col The cancel-status column (e.g. 'b.CancelStatus').
     * @return string SQL boolean fragment (no leading AND).
     */
    function guest_list_cancel_predicate($get, $col)
    {
        return guest_list_flag_on($get, 'cancelled')
            ? "{$col} != 'N'"
            : "{$col} = 'N'";
    }
}

if (!function_exists('guest_list_customer_only_segment_keys')) {
    /**
     * The campaign "Customer type" value segments. These target the CUSTOMER
     * master (one row per customer — see guest_list_customer_segment_sql), not the
     * booking-guest listing. A booking-guest row, a synthesized leader-fallback
     * row and a GHL lead can none of them satisfy a per-customer purchase-count /
     * lifetime-value / booking-lead / kids / consecutive-years / cancelled-BC
     * measurement, so any of these drops EVERY branch on the booking+GHL UNION
     * path; the filters apply on the separate customer query instead.
     *
     * @return string[]
     */
    function guest_list_customer_only_segment_keys()
    {
        return array('min_purchases', 'ltv', 'booking_lead', 'consecutive_years', 'family_kids', 'cancelled');
    }
}

if (!function_exists('guest_list_customer_segment_sql')) {
    /**
     * Build the customer-path WHERE fragment for the campaign "Customer type"
     * value segments (Purchase Count, Lifetime Booking Value, Booking-to-Travel
     * Lead, Family with kids, Purchased 2 consecutive years, Has cancelled BC).
     *
     * Each segment is a correlated subquery over that one customer's own bookings
     * (b.CustomerID = c.CustomerID), so it filters the customer master row `c` in
     * Build_Customer_Branch. The "Has cancelled BC" toggle flips the booking set
     * every OTHER segment measures over — active (CancelStatus = 'N') to cancelled
     * (CancelStatus <> 'N'), mirroring the old guest-path flip — and on its own
     * requires the customer to own at least one cancelled booking.
     *
     * Returns a SQL string (each clause already prefixed with AND, safe to append
     * straight onto the WHERE) plus the ordered bound params. Both are empty when
     * no segment is active.
     *
     * @param array $get The request GET params.
     * @return array{sql:string,params:array}
     */
    function guest_list_customer_segment_sql($get)
    {
        $sql    = '';
        $params = array();

        $cancelled = guest_list_flag_on($get, 'cancelled');
        // The booking set every value segment below is measured over.
        $set = "FROM booking b WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND "
             . ($cancelled ? "b.CancelStatus <> 'N'" : "b.CancelStatus = 'N'");

        // Has cancelled BC: the customer must own at least one cancelled booking.
        if ($cancelled) {
            $sql .= " AND EXISTS (SELECT 1 FROM booking b
                WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND b.CancelStatus <> 'N') ";
        }

        // Purchased N× and above: distinct bookings in the set.
        $min = guest_list_purchase_count_min(isset($get['min_purchases']) ? $get['min_purchases'] : '');
        if ($min !== null) {
            $sql     .= " AND (SELECT COUNT(DISTINCT b.BookingID) {$set}) >= ? ";
            $params[] = $min;
        }

        // Lifetime Booking Value bucket: total NetTotal across the set
        // (min inclusive, max exclusive).
        $ltv = guest_list_parse_bucket_bounds(isset($get['ltv']) ? $get['ltv'] : '');
        if ($ltv !== null) {
            $sql     .= " AND (SELECT COALESCE(SUM(COALESCE(b.NetTotal, 0)), 0) {$set}) >= ? ";
            $params[] = $ltv[0];
            if ($ltv[1] !== null) {
                $sql     .= " AND (SELECT COALESCE(SUM(COALESCE(b.NetTotal, 0)), 0) {$set}) < ? ";
                $params[] = $ltv[1];
            }
        }

        // Booking-to-Travel Lead bucket (months): ANY one booking in the set was
        // created that far ahead of its travel start.
        $lead = guest_list_parse_bucket_bounds(isset($get['booking_lead']) ? $get['booking_lead'] : '');
        if ($lead !== null) {
            $sql     .= " AND EXISTS (SELECT 1 {$set} AND TIMESTAMPDIFF(MONTH, b.InsertDate, b.StartDate) >= ? ";
            $params[] = $lead[0];
            if ($lead[1] !== null) {
                $sql     .= " AND TIMESTAMPDIFF(MONTH, b.InsertDate, b.StartDate) < ? ";
                $params[] = $lead[1];
            }
            $sql .= ") ";
        }

        // Family with kids: ANY booking in the set carries a child or infant.
        if (guest_list_flag_on($get, 'family_kids')) {
            $sql .= " AND EXISTS (SELECT 1 {$set} AND (COALESCE(b.Children, 0) > 0 OR COALESCE(b.Infant, 0) > 0)) ";
        }

        // Purchased 2 consecutive years+: OR each booking year into a bitmask
        // (base 2000); two set bits one position apart mean two adjacent calendar
        // years exist. The aggregate lives in HAVING over the single implicit group.
        if (guest_list_flag_on($get, 'consecutive_years')) {
            $mask = "BIT_OR(1 << (YEAR(b.InsertDate) - 2000))";
            $sql .= " AND EXISTS (SELECT 1 {$set} AND YEAR(b.InsertDate) >= 2000
                HAVING ({$mask} & ({$mask} << 1)) <> 0) ";
        }

        return array('sql' => $sql, 'params' => $params);
    }
}

if (!function_exists('guest_list_leads_only_segment_keys')) {
    /**
     * The new campaign-segment filters that only a GHL/manual LEAD can satisfy —
     * Tag and Race live only on ghl_contacts, so either drops the booking branch
     * and the leader-fallback branch.
     *
     * @return string[]
     */
    function guest_list_leads_only_segment_keys()
    {
        return array('tags', 'race');
    }
}

if (!function_exists('guest_list_any_filter_set')) {
    /**
     * True when ANY of $keys is present in $get with at least one usable value
     * (multi-select aware; a bare toggle counts when equal to '1').
     *
     * @param array    $get  The request GET params.
     * @param string[] $keys Keys to test.
     * @return bool
     */
    function guest_list_any_filter_set($get, $keys)
    {
        foreach ($keys as $k) {
            if (!isset($get[$k])) {
                continue;
            }
            // Bucket / count / toggle values arrive as scalars; multi-values
            // handles both scalars and arrays and drops blanks. A literal '0'
            // is the "off" state of a toggle (cancelled=0, family_kids=0) — and
            // none of these segment values are ever a bare '0' when active — so
            // it must not count as set.
            $values = array_filter(
                guest_list_multi_values($get[$k]),
                function ($v) { return $v !== '0'; }
            );
            if (count($values) > 0) {
                return true;
            }
        }
        return false;
    }
}
