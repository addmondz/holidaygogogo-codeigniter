<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Customer Name Search Helper
 *
 * Single source of truth for the whitespace-tolerant customer-name LIKE used by:
 *   - Customer list page (Customer_Model::Read_Customers1 / Count_Customers /
 *     Read_Customers_For_Export) -- prefix search
 *   - Booking list "customer" filter (Booking_Model::apply_booking_filters) --
 *     substring search across booking.Customer, customer.name and guest_list
 *
 * Bug shape this guards against:
 *   Stored customer names imported from CSV / GHL frequently keep leading or
 *   trailing whitespace. The previous index-safe `LIKE 'q%'` (and the booking
 *   filter's `LIKE '%q%'`) never matched those rows when the user typed the
 *   term without the same whitespace -- and vice versa. The helper wraps the
 *   compared column in TRIM(...) so the comparison is whitespace-agnostic on
 *   both the input side and the data side. Callers should still trim() the
 *   raw user input before passing it in (and skip the call when empty).
 */

if (!function_exists('customer_name_trim_like_fragment')) {
    /**
     * Build a raw SQL fragment of the form `TRIM(<field>) LIKE '<pattern>'`.
     *
     * @param string $field          Column expression to wrap in TRIM(). Trust the caller -- this is
     *                               concatenated into SQL, so only pass static identifiers.
     * @param string $escaped_input  User input AFTER trim() AND $db->escape_like_str(). The caller
     *                               owns escaping so SQL injection / wildcard injection cannot enter
     *                               through this helper.
     * @param string $direction      'after' = `q%` (prefix, index-shape preserved on the input side),
     *                               'both'  = `%q%` (substring),
     *                               'before'= `%q`  (suffix).
     */
    function customer_name_trim_like_fragment($field, $escaped_input, $direction = 'after')
    {
        if ($direction === 'both') {
            $pattern = "%{$escaped_input}%";
        } elseif ($direction === 'before') {
            $pattern = "%{$escaped_input}";
        } else {
            $pattern = "{$escaped_input}%";
        }
        return "TRIM({$field}) LIKE '{$pattern}'";
    }
}

if (!function_exists('customer_name_apply_trim_like')) {
    /**
     * Convenience wrapper that applies the fragment to a CI query builder.
     * Returns true when the clause was applied, false when the input was
     * empty after trimming (so the caller can skip dependent state like
     * `$ignore = 1` on the booking filter).
     *
     * @param object $db          CI database query builder ($this->db).
     * @param string $field       Column expression.
     * @param mixed  $raw_input   The unfiltered $this->input->get(...) value.
     * @param string $direction   'after' | 'both' | 'before' (see fragment helper).
     * @param string $combine     'and' (default) or 'or' -- how to attach the clause.
     */
    function customer_name_apply_trim_like($db, $field, $raw_input, $direction = 'after', $combine = 'and')
    {
        $q = trim((string) $raw_input);
        if ($q === '') {
            return false;
        }
        $like = $db->escape_like_str($q);
        $fragment = customer_name_trim_like_fragment($field, $like, $direction);
        if ($combine === 'or') {
            $db->or_where($fragment, null, false);
        } else {
            $db->where($fragment, null, false);
        }
        return true;
    }
}
