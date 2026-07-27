<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * GHL Message Log Helper
 *
 * Pure logic backing the "View message log" feature on the Guest List and
 * Booking listing. A contact's WhatsApp history is already synced into the
 * `ghl_messages` table (from_number / to_number hold the phone). These helpers
 * decide which phones HAVE a stored conversation (so the icon only shows when a
 * log exists) and shape each stored message into a chat bubble for the modal.
 *
 * Kept free of CodeIgniter/DB so it can be unit tested under SQLite :memory:
 * (see tests/helpers/GhlMessageLogViewTest.php).
 *
 * Phone identity: every listing produces a digits-only, country-coded number
 * (Guest List via guest_contact_wa_digits(); Booking via CountryCode . Mobile),
 * and GHL stores E.164 ("+60...."). So a message matches a listing row when the
 * stored number, stripped of non-digits, equals the row's digits.
 */

if (!function_exists('ghl_message_log_normalize_number')) {
    /**
     * Strip every non-digit from a phone number. Returns '' when there are none.
     *
     * @param string $raw Any phone string ("+60 12-345 6789", "60123456789", …).
     * @return string Digits only.
     */
    function ghl_message_log_normalize_number($raw)
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        return $digits === null ? '' : $digits;
    }
}

if (!function_exists('ghl_message_log_phone_candidates')) {
    /**
     * The stored-number forms to match a listing phone against, so an index on
     * ghl_messages.from_number / to_number can be used (exact IN, no leading
     * wildcard). GHL stores E.164 with a leading "+", but some rows may lack it,
     * so both variants are returned.
     *
     * @param string $raw The listing row's phone (digits or formatted).
     * @return string[] e.g. ["60123456789", "+60123456789"]; [] when no digits.
     */
    function ghl_message_log_phone_candidates($raw)
    {
        $digits = ghl_message_log_normalize_number($raw);
        if ($digits === '') {
            return array();
        }
        return array($digits, '+' . $digits);
    }
}

if (!function_exists('ghl_message_log_match_phones')) {
    /**
     * Given the stored message rows (each with from_number / to_number) and the
     * list of a page's phone numbers, return which page phones have at least one
     * stored message. Comparison is on normalized (digits-only) equality.
     *
     * @param array<int,array<string,mixed>> $stored_rows Rows with from_number/to_number keys.
     * @param string[] $page_phones The listing rows' phones (any format).
     * @return string[] The normalized digits of the page phones that matched
     *                  (unique, first-seen order).
     */
    function ghl_message_log_match_phones($stored_rows, $page_phones)
    {
        $have = array();
        foreach ((array) $stored_rows as $row) {
            foreach (array('from_number', 'to_number') as $col) {
                if (!isset($row[$col])) {
                    continue;
                }
                $d = ghl_message_log_normalize_number($row[$col]);
                if ($d !== '') {
                    $have[$d] = true;
                }
            }
        }

        $out  = array();
        $seen = array();
        foreach ((array) $page_phones as $phone) {
            $d = ghl_message_log_normalize_number($phone);
            if ($d === '' || isset($seen[$d]) || !isset($have[$d])) {
                continue;
            }
            $seen[$d] = true;
            $out[]    = $d;
        }
        return $out;
    }
}

if (!function_exists('ghl_message_log_shape_message')) {
    /**
     * Shape one stored ghl_messages row into a chat bubble for the modal.
     *
     * @param array<string,mixed> $row Keys: direction, body, message_type,
     *        agent_name, contact_name, date_added.
     * @return array{side:string,author:string,body:string,time:string,type:string}
     *         side 'out' = sent by us (right bubble), 'in' = from the contact.
     */
    function ghl_message_log_shape_message($row)
    {
        $direction = strtolower(trim((string) (isset($row['direction']) ? $row['direction'] : '')));
        $is_out    = ($direction === 'outbound');

        $agent   = trim((string) (isset($row['agent_name']) ? $row['agent_name'] : ''));
        $contact = trim((string) (isset($row['contact_name']) ? $row['contact_name'] : ''));
        $author  = $is_out
            ? ($agent !== '' ? $agent : 'Agent')
            : ($contact !== '' ? $contact : 'Customer');

        $body = trim((string) (isset($row['body']) ? $row['body'] : ''));
        if ($body === '') {
            $type = trim((string) (isset($row['message_type']) ? $row['message_type'] : ''));
            $body = '[' . ($type !== '' ? $type : 'no text') . ']';
        }

        return array(
            'side'   => $is_out ? 'out' : 'in',
            'author' => $author,
            'body'   => $body,
            'time'   => ghl_message_log_format_time(isset($row['date_added']) ? $row['date_added'] : ''),
            'type'   => trim((string) (isset($row['message_type']) ? $row['message_type'] : '')),
        );
    }
}

if (!function_exists('ghl_message_log_format_time')) {
    /**
     * Format a stored datetime for a chat bubble ("7 Jul 2026, 2:03 PM"). Passes
     * an unparseable / empty value through unchanged.
     *
     * @param string $datetime A "Y-m-d H:i:s" datetime, or ''.
     * @return string
     */
    function ghl_message_log_format_time($datetime)
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($datetime);
        return $ts ? date('j M Y, g:i A', $ts) : $datetime;
    }
}
