<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Customer Dedup Helper
 *
 * Pure logic backing the "possible duplicate customer" check. Duplicate
 * customers are detected by PHONE only — customers are considered the same
 * person when their phone numbers share the same last-9-digit key, regardless
 * of the format entered ("0122983045" / "122983045" / "+60 122983045").
 *
 * SCOPE: this is for the CUSTOMER table only — it does NOT look at guest_list
 * or ghl_contacts. It is kept free of CodeIgniter/DB so it can be unit tested
 * under SQLite/plain PHP (see tests/helpers/CustomerPhoneDedupTest.php).
 *
 * The last-9-digit key intentionally matches the format stored in
 * customer.phone_norm (populated by Customer_Model on every write) so the DB
 * lookup and this in-memory filter agree.
 *
 * Business rule (confirmed 2026-08-07):
 *   - Same normalised phone      -> duplicate.
 *   - Same NAME, different phone  -> NOT a duplicate (real namesakes allowed).
 *   - Empty / digitless phone     -> no match (never block a blank phone).
 */

if (!function_exists('customer_phone_dedup_key')) {
    /**
     * Strip every non-digit and return the last 9 digits. Returns '' when there
     * are no digits at all (a blank phone must never match anything).
     *
     * @param string $phone Raw user-entered number (may contain +, -, spaces).
     * @return string Last 9 digits, or '' when none.
     */
    function customer_phone_dedup_key($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return '';
        }
        return strlen($digits) <= 9 ? $digits : substr($digits, -9);
    }
}

if (!function_exists('customer_filter_phone_duplicates')) {
    /**
     * Return the rows whose phone matches $phone by normalised key. Name is
     * deliberately ignored so genuine namesakes with different phones are not
     * flagged. A blank/digitless $phone yields no matches.
     *
     * @param array  $rows        Customer rows (each an assoc array or object).
     * @param string $phone       Phone to test against.
     * @param string $phone_field Field holding the stored phone on each row.
     * @return array Matching rows, keys reindexed 0..n.
     */
    function customer_filter_phone_duplicates(array $rows, $phone, $phone_field = 'phone_number')
    {
        $key = customer_phone_dedup_key($phone);
        if ($key === '') {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            $value = is_array($row)
                ? (isset($row[$phone_field]) ? $row[$phone_field] : null)
                : (isset($row->$phone_field) ? $row->$phone_field : null);

            if (customer_phone_dedup_key($value) === $key) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('customer_group_names_differ')) {
    /**
     * Whether a duplicate group's rows carry more than one distinct name (case /
     * whitespace-insensitive). Drives the ⚠️ "verify same person" badge — a group
     * whose names differ may be family sharing a phone, not a true duplicate.
     *
     * @param array $rows Customer rows (assoc arrays or objects).
     * @return bool
     */
    function customer_group_names_differ(array $rows)
    {
        $names = array();
        foreach ($rows as $row) {
            $name = is_array($row)
                ? (isset($row['name']) ? $row['name'] : '')
                : (isset($row->name) ? $row->name : '');
            $names[strtoupper(trim((string) $name))] = true;
        }
        return count($names) > 1;
    }
}

if (!function_exists('customer_default_keeper_id')) {
    /**
     * Suggested keeper for a duplicate group: the record with the MOST bookings,
     * tie-broken by having a real CustomerCode, then the lowest (oldest)
     * CustomerID. The Owner can still override in the UI.
     *
     * @param array $rows Rows with CustomerID, booking_count, CustomerCode.
     * @return int|null CustomerID of the suggested keeper, or null if no rows.
     */
    function customer_default_keeper_id(array $rows)
    {
        $best = null;
        foreach ($rows as $row) {
            $r = (object) (is_array($row) ? $row : (array) $row);
            $r->CustomerID   = (int) (isset($r->CustomerID) ? $r->CustomerID : 0);
            $r->booking_count = (int) (isset($r->booking_count) ? $r->booking_count : 0);
            $r->has_code     = !empty($r->CustomerCode) ? 1 : 0;

            if ($best === null
                || $r->booking_count > $best->booking_count
                || ($r->booking_count === $best->booking_count && $r->has_code > $best->has_code)
                || ($r->booking_count === $best->booking_count && $r->has_code === $best->has_code && $r->CustomerID < $best->CustomerID)) {
                $best = $r;
            }
        }
        return $best ? $best->CustomerID : null;
    }
}
